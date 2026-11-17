## Context

`PublicNodeController::list()` renders `["{type}/list", "content/list"]` with the hook name `content.list` and passes `type`, `sectionTitle`, `paginator`, `pagination`, `period`, `hasArchive`. `ContentType::children()` (by `parent_type`) and `App\Theme\TypeSection::title()` (public title: `section_title`, else `name`) already exist. The `dcculture` theme adds data to templates through hook files under `themes/dcculture/hooks/` (`home-news.php` is the pattern), registered in `themes/dcculture/hooks.php`. The `intl` extension is available in the app container. Direct children of `common`: `institutions`, `institutions_sources`, `festivals_contests`, `city_awards`, `cultural_heritage`.

## Goals / Non-Goals

**Goals:**
- Child type cards under the `common` records, in the style of record cards, sorted by public title.
- Theme-only: no core change.

**Non-Goals:**
- Child types on other types' pages, grandchildren, a core-provided `$childTypes` variable.
- Tests (theme frontend rule); new CSS.

## Decisions

1. **Data through a theme hook, not the core.** `hooks/common-children.php` registers `preprocess('content.list')`; it returns at once unless `$vars['type']->key === 'common'`. Otherwise it sets `$vars['childTypes']`, a list of `['title' => string, 'url' => string]`. Alternative (a `$childTypes` variable from the controller) was rejected by the owner: the need is specific to one type of one theme.
2. **Children = `ContentType::children()` with a non-empty slug.** Direct children only (no `descendantKeys()`); a type without a slug has no public page to link to. `url` is `url('/'.$slug)` as the list cards build addresses.
3. **Title and sort.** Title = `app(TypeSection::class)->title($child)`. Sort in PHP after titles are computed (a handful of types, no extra query), with `Collator('uk')` (`intl` is present) comparing the titles; the hook falls back to a plain `strcmp` of lower-cased titles if `Collator` cannot be created.
4. **Dedicated template `common/list.blade.php`.** Resolved before `content/list` through the `{type}/list` candidate. It copies `content/list` and adds, after the records list and before the pagination, a block `.content-page__list` with one card per `$childTypes` item, printed only when the list is not empty. The card reuses `.content-card`, `.content-card__title` and `.content-card__link` (markup as in `content/preview`, without `@fields`), so no CSS is added. The `.record-list` class stays on the records list only.
5. **Order on the page.** Records, then child type cards, then pagination and the archive link (the archive link prints nothing for `common`).

## Risks / Trade-offs

- The hook runs on every `content.list` render and exits at the first line for other types: negligible.
- A child type whose `section_title` is missing in the current language falls back to the default language, then to `name` (the `TypeSection` rule), so the card is never empty.
- Pagination: the child cards appear on every page of `common`. `common` has 3 records, so this is acceptable; if it grows, the cards can be limited to page 1.

## Docs

`docs/theme-dcculture.md`: the Templates tree (`common/`), the Hook files list, and a new section "Common page: child types".
