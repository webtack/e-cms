# Design

## Context

See `proposal.md` for the motivation. The relevant state of the code:

- A menu is placed by `@menu('key')`. `MenuRenderer::render()` takes the visible tree from `MenuTree::tree()`, drops invalid addresses in `safe()`, and renders `menus/menu--{key}` or `menus/menu` through `ThemeManager::render()`. The menu template gets only `$menu`, `$key`, `$items`; the shared page variables (`$site`, `$meta`, ...) are **not** available inside a menu. `MenuRenderer` knows nothing about the request.
- Public pages are built by `PublicNodeController` through `PublicController::respond()`, which calls `PublicData::make($extra, $recordTitle)` and `ThemeManager::render()`. `$extra` holds `node`, `type`, `values` for a record or a page, and `type` plus `paginator` for a list; the home page and the 404 pass nothing of the kind. Everything `make()` returns is a variable of the page template, of the layout it extends, and of the partials they include.
- `preprocess` hooks run only inside `ThemeManager::render()` for the name of the rendered template (`content.details`, `page`, ...). There is no hook for data computed in `PublicData`.
- `ContentType` has `parent_type`, `slug` (public list address, several segments allowed) and `name`, which is the administrative name. The public title of a `content` type is its system value `section_title` (edited on the "Display" tab, owned by the type, translatable; `docs/content-model.md`, `docs/admin-content-types.md`), read by `ContentValueReader::loadForType($type, ['section_title'], $lang)`, which does **not** fall back to the base language. No public template reads `section_title` yet: both `content/list` templates print `$type->name`. The public reading language is `PresentContext::$lang` (the one `MenuRenderer` uses). A slug must start with its parent's slug (`TypeSlugs`). A page (`kind = page`) has no hierarchy. A record's address is `/{type.slug}/{node.slug}`.
- `themes/default/views/html.blade.php` is the document shell. `themes/dcculture` has no `html` of its own and inherits it. Both themes get their layout partials through `@include('theme::...')`, so a theme replaces a partial by providing a file with the same name.
- The interface language is set by `SetLocale` for web requests and public strings use `__()`. `lang/*.json` has no "Home" key yet.

## Goals / Non-Goals

**Goals:**
- One place that knows how a request path matches menu items, used by every theme.
- One trail per page, derived from data that already exists, exposed as plain variables so a theme controls the markup completely.
- Structured data that cannot drift from the visible trail and cannot be lost by overriding a partial.

**Non-Goals:**
- Parent/child relations between pages or between records, and menu-driven trails (see Decisions).
- Breadcrumbs or active state for menus other than through the generic menu tree (no special case for `main`).
- Showing `section_description` and `section_cover` on the public site (only `section_title` is used here; the other two need their own presentation and a separate change).
- Active-state caching: the menu tree is already built per request.

## Decisions

### 1. Menu marking lives in `MenuRenderer`, in a separate matcher class

`MenuRenderer::render()` reads the request path (`request()->getPathInfo()`) and passes the tree through a new `App\Theme\MenuActive` (pure function: tree + path → tree with `is_active` / `is_trail`). The marking runs before addresses are made absolute and uses the stored `url`.

*Why here:* every theme (default, dcculture, a future one) gets the flags with no template logic, and `list.blade.php` is recursive, which makes a Blade-side helper awkward. *Alternatives:* a Blade helper per theme (duplicated, recursion-unfriendly); client-side JS (flicker, no JS fallback). A separate class keeps `MenuRenderer` small and makes the matching rules unit-testable without rendering.

Matching algorithm, one pass: normalise the request path (no query/fragment, no trailing slash except `/`); for every item with a relative stored address take its path part (before `?`/`#`), normalise it the same way, and accept it when `item === request` or (`item !== '/'` and the request starts with `item.'/'`). Keep the longest accepted path, first in tree order on ties. Then walk the tree once, setting `is_active` on the winner and `is_trail` on the nodes on the way to it (recursive search that returns whether the branch holds the winner).

An address counts as relative only when `MenuUrl::isExternal()` is `false` and it does not start with `#` or `?`.

### 2. Absolute addresses are applied last, in `safe()`'s successor

After marking, a second step replaces a stored `url` that starts with `/` but not `//` with `url($path)`. `is_external` stays as computed from the stored address (it is already in the item). The order matters: marking and `is_external` first, absolute address last.

*Alternative:* make the menu tree absolute in `MenuTree::tree()`. Rejected: `tree()` is the data API (also used by the admin and tests), and the host belongs to the request, which `MenuTree` should not know.

### 3. Breadcrumbs are computed in `PublicData`, from what the controller already passes

A `Breadcrumbs` service (`App\Theme\Breadcrumbs`) has one entry point, `for(?ContentType $type, ?ContentNode $node, ?string $title): list<array{title, url}>`. `PublicData::make()` calls it with `$extra['type']`, `$extra['node']` and the record title it already receives, so no controller changes beyond what `PublicData` reads:

| `$node` | `$type` | Result |
|---|---|---|
| set, `kind = page` | node's type | Home, title |
| set, `kind = content` | the type | Home, ancestors, type, title |
| `null` | set (a list) | Home, ancestors, type (current) |
| `null` | `null` | `[]` (home, 404) |

Ancestors are collected by following `parent_type` with `ContentType::findByKey`, one query per level with a visited set against damaged data (the same guard as `MenuItemTree`), and reversed so the root comes first. Types without a slug are skipped. Absolute URLs come from `url('/'.$slug)`. The title of every type item comes from `TypeSection` (decision 8), never from `ContentType::name`.

*Why a data variable, not a directive:* a directive would call the service from Blade and could not give the document shell the JSON-LD (which sits in `<head>`, outside the page body). A variable computed once is available in `html`, the layout and every partial. *Alternative considered:* the controller passes the trail. Rejected: three controllers (record, list, page) would each have to remember it, while `PublicData` already sees all public pages and is the single place for shared variables.

### 4. The trail source is the type hierarchy; the menu is not used

Decided with the owner. A record or list gets its trail from `parent_type`, which agrees with its URL by construction (slug prefix rule), so a page has one trail whatever the menu looks like. A page is always Home → title. A hybrid with the menu was rejected: the same page could get different trails depending on a menu edit, which search engines treat as a quality problem.

### 5. The hook is run by `PublicData` on a dedicated name

`PublicData` calls `ThemeManager::hooks()->applyPreprocess('breadcrumbs', ['breadcrumbs' => $list])` and reads the list back. Both `$breadcrumbs` and `$breadcrumbsJsonLd` are built from that result. A hook name that is not a template name is new but fits the model: `applyPreprocess()` only needs a name, and `ThemeHooks::chain()` makes `breadcrumbs` run for hooks registered on `breadcrumbs` only. `PublicData` needs `ThemeManager` already.

### 6. JSON-LD is built in PHP and printed by the document shell

`Breadcrumbs::jsonLd(list): ?string` returns `null` below two items; otherwise `json_encode` of a `BreadcrumbList` with flags `JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG`. `JSON_HEX_TAG` turns `<` and `>` into `<` / `>`, so a title can never close the `<script>`; `JSON_HEX_AMP` is added as well. The last item has no `item`, the others have the absolute URL.

`themes/default/views/html.blade.php` prints it in `<head>` with `{!! !!}` (the string is JSON, safe by the flags above). `dcculture` inherits `html`, so it needs nothing. A theme that overrides only `partials/breadcrumbs` keeps the structured data; a theme that hides the visible trail clears the list in the `breadcrumbs` hook and loses both outputs together.

*Alternatives:* a directive that prints the script after the partial (rejected with the directive); printing in the partial (a rewritten partial silently drops it).

### 7. The home item and translation

The home item's title is `__('Home')`; the key is added to `lang/en.json` and `lang/uk.json` ("Головна"). `dcculture` can still show its own static wording in its partial because it reads the list and may skip or rewrite the first item.

### 8. The public title of a type: `TypeSection`

A new scoped service `App\Theme\TypeSection` has `title(ContentType $type): string`: it reads `section_title` with `ContentValueReader::loadForType($type, ['section_title'], $lang)` in the reading language (`PresentContext::$lang`), then in `default`, then falls back to `$type->name`; an empty string counts as absent. Results are memoised per request by type key, so the list page (`$sectionTitle`) and the trail share the read. A type of kind `page` or `block` holds no section values, so it skips the read and returns `name`.

`PublicNodeController::list()` adds `'sectionTitle' => app(TypeSection::class)->title($type)` to the list template variables, and `Breadcrumbs` calls the same service for each type in the trail. Both `content/list` templates print `{{ $sectionTitle }}` where they printed `{{ $type->name }}`, and their header comments document the variable.

*Why a service and not a `ContentType` accessor:* the reading language and the fallback chain are a presentation concern of the public site; the model stays language-free, as the admin keeps using `name`. *Why not only in the template:* the trail needs the same title for every ancestor, outside any template. *Alternative:* change `loadForType` to fall back to the base language. Rejected: the documented contract is "no substitution", and the admin relies on it.

### 9. Templates

- `themes/default/views/partials/breadcrumbs.blade.php`: `nav.breadcrumbs[aria-label]` > `ol` > `li.breadcrumbs__item`; a link for items with `url`, a `span[aria-current="page"]` for the last one; prints nothing when `$breadcrumbs` is empty. Included by the `default` layout at the top of `<main>`.
- `themes/dcculture/views/partials/breadcrumbs.blade.php`: same data and a markup of the theme's design, included by `layout.blade.php` between `main-nav` and `<main>` content, with a component stylesheet in `assets/components/` loaded the way the other components are.
- `menus/menu` and `menus/list` (`default`) and `menus/menu--main` (`dcculture`) read `is_active` / `is_trail` and add `is-active` / `is-trail` and `aria-current`.
- The header comments of `html`, `layout`, the menu templates and the partials document the new variables and flags (these comments are the theme API).

## Risks / Trade-offs

- **`PublicData` now queries types for the ancestor chain, and reads `section_title` per type** → one query per level of nesting for the types, plus one value read per type of the chain (`loadForType` serves one type at a time; there is no batch read for types today). Chains are short (a few levels) and `TypeSection` memoises per request; a visited set bounds a damaged hierarchy. If chains grow, a batch read for several types is a contained follow-up in the reader.
- **The shipped list templates change their heading** from `$type->name` to the section title → a site whose types have no `section_title` shows the same name as before (the fallback), and a site that filled it in now shows the public title it entered. A theme that still prints `$type->name` is unaffected.
- **`section_title` is single-line text without HTML** → printed escaped; a value with markup would show it literally, which matches how the admin form treats the field.
- **Absolute menu addresses depend on the request host** (`url()` uses the current root, which behind a proxy needs trusted proxies configured) → this is how `og:image` is already built in `html.blade.php`, so the deployment already has to get it right.
- **Contract change for menu templates** (`url` of a relative item is absolute) → existing templates print it unchanged and keep working; a template that compared `url` with a path would break, and the two themes in the repo do not.
- **A page has the shortest possible trail** → expected until pages can have parents (non-goal).
- **Hook on a non-template name** → documented in `docs/theme-templates.md` so theme authors find it.
- **Active match on a prefix** can highlight a parent item for an unrelated child page when the menu points at a broad prefix (`/common` highlights for everything under it) → intended; the longest match limits it to the most specific item the editor created.

## Migration Plan

No data migration and no schema change. The change is additive: new variables, new flags, a new partial and a new hook name. Roll back by reverting the code; stored menus are untouched. After deploy the `dcculture` stylesheet needs `npm run build` (host) so the new component CSS is in the manifest.

## Open Questions

- Whether `section_description` and `section_cover` should be shown on list pages later (a follow-up; this change adds only the title).
- Whether `dcculture` wants the home crumb as an icon rather than the word (design detail, decided when the partial is styled; it changes no spec).
