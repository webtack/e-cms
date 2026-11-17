# Proposal

## Why

The public site has no breadcrumbs, and its menus give no sign of where the visitor is: nothing in the menu data says which item matches the current page or which parents lead to it. Breadcrumbs and an active menu trail are basic navigation aids, and breadcrumbs also strengthen internal linking and can appear in search results when published as `BreadcrumbList` structured data.

## What Changes

- **Active menu item.** The tree a menu template receives marks, for the current request path, the matching item (`is_active`) and every ancestor of it (`is_trail`). Matching is by path: an exact match, or a prefix that ends on a segment boundary (`/news` matches `/news/hello`, not `/newsletter`). When several items match, the longest address wins. Query string and fragment are ignored; external, `mailto:`, `tel:` and addressless items never match. Marking lives in the menu renderer, so every theme gets the data and only reads two booleans.
- **Absolute menu addresses.** An item whose stored `url` starts with `/` (but not `//`) is given to the template as an absolute URL (`url()`); the match against the request path and `is_external` are decided from the stored address first, so a relative item stays internal. Absolute, `mailto:`, `tel:`, `#` and `?` addresses are passed through unchanged.
- **Breadcrumbs as data, not a directive.** A `Breadcrumbs` service builds the trail for the current public page, and `PublicData::make()` adds two shared view variables: `$breadcrumbs` (a list of `title` / `url` pairs, with absolute URLs; the last item is the current page and has `url` of `null`; empty on the home page and on 404) and `$breadcrumbsJsonLd` (a ready JSON string, or `null` when there is no trail). Templates only read them; there is no Blade directive. A `preprocess('breadcrumbs')` hook lets a theme change the list, and both variables are built from the list after the hook, so the visible trail and the structured data cannot disagree.
- **Trail source (decided).** Content records and type lists follow the content-type hierarchy: home, the chain of `parent_type` (types without a slug are skipped), the type, and the record title. A `page` is always home plus its own title. The menu is not used for the trail, so a page has one trail whatever the menu looks like.
- **Public title of a type.** The title of a type on the public site is its `section_title` value (entered on the type's "Display" tab, translatable), read in the reading language, then in the base language, then `ContentType::name` as the last resort. A `TypeSection` service provides it; the breadcrumbs use it for every type in the trail, and the list page passes it to its template as `$sectionTitle`. The `content/list` templates of both themes print `$sectionTitle` instead of `$type->name`. `section_description` and `section_cover` stay out of this change.
- **JSON-LD.** `$breadcrumbsJsonLd` is a `BreadcrumbList` with absolute `item` URLs and no `item` on the last element, encoded with `JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG` so a title can never close the script tag. The base `html` template of `themes/default` prints it in `<head>` when it is not `null`; a theme that only overrides the visible breadcrumbs partial therefore keeps the markup.
- **Default theme template.** `themes/default` gets a `partials/breadcrumbs` template (an `ol` inside a labelled `nav`, `aria-current="page"` on the last item, minimal classes) so a theme can style or override it, and active and trail classes in its menu templates.
- **`dcculture` theme.** The main navigation and its dropdowns show the active and trail states (class and `aria-current`), and the theme gets a breadcrumbs partial styled to its design.

## Capabilities

### New Capabilities

- `theme-breadcrumbs`: building the breadcrumb trail of a public page from the content-type hierarchy, exposing it to templates as `$breadcrumbs` and `$breadcrumbsJsonLd`, and the `preprocess('breadcrumbs')` hook.

### Modified Capabilities

- `theme-rendering`: the menu requirement gains the active and trail marks and the absolute form of relative addresses on the items a menu template receives.
- `public-site`: the type list page gets the public title of the type (`$sectionTitle`) for its template.
- `default-theme-styling`: the `default` theme provides the breadcrumbs template, prints the JSON-LD in `<head>`, styles the active and trail states of its menu, and titles a type list with the section title.
- `dcculture-theme`: the main navigation highlights the active item and its parents, the theme shows breadcrumbs, and titles a type list with the section title.

## Impact

- **Code:** `app/Theme/MenuRenderer.php` (marking, absolute addresses), new `Breadcrumbs` and `TypeSection` services in `app/Theme/` and their calls in `PublicData` and `PublicNodeController::list()` (`$sectionTitle`), `themes/default/views/` (`html`, `partials/breadcrumbs`, `menus/menu`, `menus/list`, `content/list`), `themes/dcculture/views/` (`menus/menu--main`, breadcrumbs partial, layout, `content/list`) and its component CSS.
- **Contract change for menu templates:** `url` of a relative item is now absolute. Existing menu templates keep working because they print `url` as is.
- **Contract change for list templates:** a new variable `$sectionTitle`; the shipped list templates stop printing `$type->name`. A theme that still prints `$type->name` keeps working but shows the admin name.
- **No database changes.** The data already exists (menu item URLs and parents, type parents and slugs).
- **Tests:** PHP Feature tests for the matching rules, the absolute addresses and the trail; theme templates are consumer code and are checked with `php artisan theme:check`, not with tests.
- **Docs to update:** `docs/menus.md`, `docs/theme-templates.md`, `docs/theme-dcculture.md`, `docs/public-site.md`; a new `docs/breadcrumbs.md` with its link in `docs/README.md` if the topic outgrows `theme-templates.md`.
