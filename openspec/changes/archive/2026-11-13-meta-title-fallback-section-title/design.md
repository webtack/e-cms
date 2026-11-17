# Design

## Context

`App\Theme\PageMeta::make()` builds the `title` as: the node's `meta_title` → the field chosen in "Meta data" → the node's `title` → the type's own `meta_title` (read through `typeValues()`, base language, only for held fields). `PublicData::make()` then falls back to the site name. The public title of a `content` type is resolved by `App\Theme\TypeSection::title()`: `section_title` in the reading language (`PresentContext::$lang`), then `default`, then `ContentType::$name`; it is memoised per request and returns `name` without a read for `page` and `block` types. Docs: `docs/public-site.md` (Page meta data), `docs/theme-templates.md`, `docs/content-model.md`.

## Goals / Non-Goals

**Goals:**
- The list page of a `content` type shows its Section title in `<title>` when the type has no `meta_title`.

**Non-Goals:**
- No change to the priority of the node's own sources (`meta_title`, chosen field, `title`).
- No use of the administrative `name` as a title source.
- No change to `meta_description`, `meta_image`, the admin UI, or themes.

## Decisions

- **Position: step 4, after `meta_title`.** The Section title replaces only the empty type-level `meta_title`. A node with its own `title` still wins, so node pages stay the same; the effect is on the type's list page (and on nodes with no `title`).
- **Reuse `TypeSection`, add `section(ContentType): ?string`.** It returns the Section title (reading language, then base) or `null`, with no `name` fallback. `title()` is rewritten on top of it (`section($type) ?? $type->name`), keeping one read path and the per-request memo. `PageMeta` gets `TypeSection` injected. Alternative: read `section_title` through `typeValues()` in `PageMeta`; rejected because that reads only the base language and would duplicate the language fallback.
- **No `name` fallback.** The request names the Section title only; using `name` would change pages of types that have no titles from the site name to the type name. Such types keep the site name.
- **`page`/`block` types** hold no section values; `section()` returns `null` for them without a read, so their behavior is unchanged.
- **Language.** The type's `meta_title` stays base-language; the Section-title fallback follows the reading language, matching the page heading the visitor sees.

## Risks / Trade-offs

- A type with `section_title` but no `meta_title` changes its `<title>` from the site name to the Section title; this is the requested behavior.
- Mixing a base-language `meta_title` with a reading-language fallback is slightly uneven, but a set `meta_title` always wins, so the administrator keeps control.
