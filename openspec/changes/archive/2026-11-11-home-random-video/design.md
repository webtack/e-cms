# Design

## Context

The home route (`HomeController`) renders `home` with the shared page variables only; a template makes no queries (`docs/theme-templates.md`). Data for a template comes from the controller or from a theme hook: `ThemeManager::render()` runs `preprocess('home')` before the template, and `themes/<theme>/hooks.php` is loaded after `themes/default/hooks.php` (`docs/site-themes.md`). `dcculture` has no `hooks.php` yet. The video card is the field template `fields/field-youtube--youtube`; it needs `$items` (built by `FieldRenderer` from the raw value), `$field`, `$node`, `$type` and `$values`, which `@field($values, 'youtube')` takes from the template scope (`$node`, `$type`, `$fields`, and the argument). The partial `home/about-city` is included from `home` and sees the home template variables. The block's picture is absolutely positioned inside `.about-city__media` (520 × 292, in percentages below `lg`). See proposal.md for motivation.

## Goals / Non-Goals

**Goals:**
- The card on the home page is the same template, markup and modal as on `/video`, with no copy of it.
- A different record on (almost) every load; the home page works on an empty database.

**Non-Goals:**
- No change to the core, to field types or to the `video` type settings.
- No new query API such as random ordering in `ContentNodeQuery`.
- No caching of the chosen video.

## Decisions

**1. The record is chosen by a `preprocess('home')` hook in `themes/dcculture/hooks.php`.** The theme hook mechanism exists for this: theme code adds variables to a template without changing the core. Alternatives: a random mode in `ContentNodeQuery` or a Blade directive (extends the core and its spec for one theme's need), and a query inside the partial (breaks the "templates make no queries" rule).

**2. Selection uses the documented listing API, and the randomness is done in PHP.** The hook calls `ContentNodeQuery::paginate()` for the `video` type with `onlyVisible: true`, `perPage: 0` (all, up to `content.max_all`), `fields: ['title', 'youtube']`, and the default language, then reads the nodes' values from `$node->values`. It drops the nodes whose `youtube` value is blank (`FieldType::isBlank()`, so a video-less record never wins) and takes one with `Arr::random`. This reuses the visibility rules and the value reading of the site as they are and avoids SQL on the value tables. Cost: one batch of queries over the `video` records, which are few (six today); the hard cap of `max_all` means at most 1000 records are looked at. Alternative: `inRandomOrder()->first()` on `ContentNode` (one row, but needs a second query for the values and can pick a record without a video, so it needs a retry loop).

**3. Only the first video is kept.** If the field is multiple, the hook sets `$values['youtube']` to a one-item list holding the first non-blank item; for a single field the value stays as it is. The template and `FieldRenderer` are not changed. Taking the first non-blank item (not strictly index 0) avoids an empty first slot hiding a real video; the selection in decision 2 uses the same test, so a chosen record always has a card.

**4. The hook exposes one variable, `homeVideo`.** It is `['node' => ContentNode, 'type' => ContentType, 'values' => array, 'fields' => Collection]` or `null`. `fields` holds the `youtube` field under its key, as `@field` expects. The partial sets up the scope for the directive (`$node`, `$type`, `$fields`) from `homeVideo` and calls `@field($values, 'youtube')`; with `homeVideo === null` it prints the existing `<img>`. The partial keeps the Blade comment that lists the variable it reads.

**5. The card is styled inside the block, not redefined.** `.about-city__media .video-preview` gets the geometry of the former `.about-city__image` (absolute, top-left, 520 × 292, `rounded-3xl`, and the percentage sizes below `lg`); colours, mask, play icon and title stay from `video-preview.css`. The `.video-previews` wrapper that the field template prints around the card is also made to fill the same box. The card is placed above the plate (z-order as for the image) and below the badge and the ring.

**6. Randomness per request needs no cache.** The home response is not cached today (no response cache, no `Cache-Control` set by the public controllers). The hook's comment states that caching the home page would freeze the choice.

Described in: `docs/theme-dcculture.md` (the hook and the variable, "Data seams", "Video card and modal"); the hook mechanism itself is in `docs/site-themes.md`.

## Risks / Trade-offs

- [Many `video` records make the hook read them all] → the `video` section is small, `max_all` caps it at 1000, and only two fields are read; revisit with a core random mode if the section grows.
- [A card of a different height from the picture breaks the block's layout] → the card is 16:9 and the block's picture is 520 × 292 (≈ 16:9); the card is sized by the same box, checked at 360, 768, 1024 and 1440 px.
- [A page cache added later would freeze the choice] → documented in the hook comment and in `docs/theme-dcculture.md`.
- [The hook runs a few queries even though the visitor may never see them] → they run only for the home page and are cheap for the size above.
