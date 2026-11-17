# Design

## Context

Public routes are registered at the end of `routes/web.php`; `SetLocale` forces `config('app.locale')` for `public.*` routes; `PresentContext` is a scoped service with `lang = 'default'`; readers (`ContentValueReader`, `MenuTree`, term reads, `SiteSettings`) are strict or fall back inconsistently (site settings fall back by interface locale, the rest do not). The public path namespace is guarded by `PublicPaths` (reserved first segments). See proposal.md for scope and the specs for behavior; touched docs are listed in the proposal Impact.

## Goals / Non-Goals

**Goals:**
- One consistent reading language and fallback rule for everything the public site prints.
- Prefix routing that works with route caching, dynamic languages and the existing routes unchanged.

**Non-Goals:**
- Translated slugs, language detection by browser or cookie, a language-specific `sitemap.xml`, a switcher in the `dcculture` theme.
- Changing the strict readers used by the admin panel, writers and importers.
- Translating the name of a content type (a service name; the public title is `section_title`).

## Decisions

**Prefix is stripped by a global middleware, not by duplicated route groups.** `ResolvePublicLanguage` runs before routing (prepended in `bootstrap/app.php`), takes the first path segment, compares it to the cached list of active languages and, on a match when multilingual mode is on, removes it from the path (re-initializing the request so path info is recomputed) and records the language. Everything else (routes, route cache, controllers) keeps working on unprefixed paths. Alternative: register every public route twice with a `{lang}` constraint built from the database; rejected because it needs a database read at route registration and breaks `route:cache` when languages change.

**Cheap and safe for every request.** The list of active codes is cached and invalidated when a language or the switch changes; the middleware first rejects any segment that cannot be a code (length, character set) and does nothing when multilingual mode is off. It never strips the prefix before a service path (`admin`, `login`, `sitemap.xml`, ...), so `/en/admin` is not an admin address: after stripping would-be service paths the original path is kept and ends in the site 404. `/search` and `/sitemap` are the only public service-like pages allowed after a prefix.

**Base prefix redirect (301) in the same middleware.** `/{base}/...` redirects to the unprefixed address, keeping the query string, so there is exactly one address per page in the base language.

**Language only from the address.** No cookie and no `Accept-Language`: a crawler and a user get the same page for the same URL, and caches stay simple.

**`LocalizedUrl` (scoped) and `PresentContext::$lang`/`$fallback` are the single source of the reading language.** Controllers do not look at the request. Links are prefixed in the presentation layer, not in templates: `MenuRenderer`/`MenuTree` items, `Breadcrumbs`, `FieldRenderer` references, cards, the search form and results, the sitemap page, the paginator (a `currentPathResolver` set once the language is known), `url` values and a DOM pass over rich text that prefixes relative links starting with `/`. Links to service paths, files, absolute URLs and anchors are left alone. Alternative: `URL::forceRootUrl(...)`; rejected because the paginator and `request()->url()` do not use it and menu addresses are plain stored strings.

**Fallback is an explicit mode of the readers, on only for the public context.** `ContentValueReader::load(..., fallback: true)` reads the requested language, then reads the base language only for the fields that came back blank by their type's `isBlank()`; the number of queries stays constant (at most two per value table). Lists and composed values fall back whole because they are single stored units per field and language. `MenuTree`, the term reads, `FileDescriptions`, field labels and type values get the same flag. The strict default stays for the admin panel, writers, import and public APIs that ask for a language explicitly.

**Sorting with fallback uses `COALESCE` over two joins.** Only the public list path enables it; one extra join per sorted field, limited by `per_page`. Alternative: sort in memory; rejected for pagination.

**Search drops the language filter.** `SiteSearch` matches rows of every language of a node and groups by node; the title is read with the fallback in the reading language; the excerpt is built from the matching row, so highlighting stays correct.

**Site settings take the reading language explicitly.** Templates receive a `SiteSettings` view bound to the reading language (`forLang()`), not the interface locale, so a language without interface files still reads its translated name.

**Interface locale for public requests.** With multilingual mode off: `config('app.locale')` as now. On: the reading language if `lang/{code}.json` exists, else the base language's locale (`base_lang` if it has a file, else `config('app.locale')`). `<html lang>` always carries the reading language code.

**Alternates and canonical in the core `html` shell.** `PublicData` computes the list of languages with `translated` flags (the translated-languages query of `translate-nodes-and-types`, one query), the alternates and the canonical address; the shell prints them so every theme gets them. Pages without a single node are self-canonical and listed everywhere. Alternative: theme-level markup; rejected because it would let a theme silently drop the SEO signals.

**Field templates get the translated label through `name`.** The themes print `$field->name` as the field label (24 places in the bundled themes), and `FieldRenderer` passes the `ContentField` model itself. `FieldRenderer` hands the template a detached copy of the field whose `name` is the label in the reading language (`settings.labels[lang]`, else the stored name). Existing templates and third-party themes need no change; the copy is never saved. Alternative: a separate `$label` variable and rewriting every template; rejected because it breaks themes that are not in this repository and leaves old templates printing the untranslated name.

**The name of a content type is not translated.** It is a service name. The public title of a type is its `section_title` (read with fallback), which is already how templates and the sitemap get it; `type_ref` presentation keeps `name` as it was, an accepted limitation.

**Slug and code collisions are validated at the writers.** The existing top-level path check (`PublicPaths`) also treats all language codes and the base code as reserved; adding a language also checks existing page slugs and type first segments.

**Switcher markup only in the default theme.** `PublicData` exposes the data for every theme; `dcculture` is left for a follow-up because its header follows a mock-up.

## Risks / Trade-offs

- [Global middleware affects every request] → it exits on the first cheap check; covered by the full PHP test run and the `public-site` and `smoke` e2e specs.
- [Request re-initialization hides subtle state (e.g. path info, base URL)] → tests for routes, pagination and `url()->current()` under the prefix, with and without `route:cache`.
- [Rich text link rewriting can corrupt markup] → DOM-based, only `a[href^="/"]` that are not service paths, run on the already sanitized HTML, and covered by tests with relative, absolute, anchor and file links.
- [Duplicate content: fallback pages exist at both the prefixed and the base address] → canonical to the base address and no alternate for the untranslated language.
- [A sort with `COALESCE` cannot use the value index] → bounded by pagination; only for public lists in additional languages.
- [A theme reads the field label from somewhere other than `name`] → the documented contract is `name`; the bundled themes are checked with `grep` and a visual check.
- [A language code equal to a future page slug] → validated both ways; existing conflicts are reported when adding the language, never silently ignored.
- [Search results in a language other than the reading language show a foreign excerpt] → accepted by decision; the title stays in the reading language.
- [Wording of two scenarios inherited from earlier specs] → two scenario titles of the modified requirements ("Reading language without fallback", "The reading language is not substituted") keep their old names because the spec tooling matches by name; their bodies describe the new behavior.

## Migration Plan

No schema change. The feature is inert while multilingual mode is off. Rollout order: readers and tests first (strict behavior unchanged), then routing, then link prefixing and head links, then the switcher. Rollback is turning multilingual mode off.
