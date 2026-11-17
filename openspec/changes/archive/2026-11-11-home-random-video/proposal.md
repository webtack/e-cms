# Proposal

## Why

The "about the city" block on the home page (`.about-city__media`) shows a static picture (`video-preview.jpg`) that looks like a video but plays nothing. The site already has a `video` section whose records carry a `youtube` field, and the theme already renders that field as a video card with a modal player (`fields/field-youtube--youtube`). The home page should use that content: show a random video of the section on every load, rendered exactly as on `/video`.

## What Changes

- New theme hook file `themes/dcculture/hooks.php` with a `preprocess('home')` callback. It picks one random visible record of the `video` type that has a non-empty `youtube` value, reads its values, keeps only the first non-empty `youtube` item (the field may be multiple), and adds the result to the home template variables as `homeVideo` (`node`, `type`, `values`, `fields`), or `null` when there is no such record.
- `themes/dcculture/views/partials/home/about-city.blade.php` prints the card with `@field($values, 'youtube')` inside `.about-city__media`, so it is the same `field-youtube--youtube` template, card and modal as on `/video`. The decorations (plate, ring, badge) stay. When `homeVideo` is `null`, the static picture stays as a fallback, so the home page still works on an empty database.
- `themes/dcculture/assets/components/about-city.css`: the card takes the place and size of the former picture (520 × 292 on desktop, the same percentages below `lg`) and keeps its rounded corners.
- A new random video on every page load. The home response is not cached today; the hook documents that a cache of the home page would break this.
- No core code changes, no migration, no new query API. No tests are added for the theme frontend (project rule); verification is `theme:check`, the theme build and a visual check at 360, 768, 1024 and 1440 px.
- Out of scope: choosing the video by an editor, a "more videos" link, autoplay on the home page, and changing the `video` type or its field settings.

## Capabilities

### New Capabilities

None.

### Modified Capabilities

- `dcculture-theme`: the requirement "Home page sections" changes. The "about the city" block is no longer entirely static: it shows a random visible `video` record as a video card, falls back to the static picture when there is none, and the page still does not depend on any content existing.

## Impact

- Code (theme): new `themes/dcculture/hooks.php`; `themes/dcculture/views/partials/home/about-city.blade.php`; `themes/dcculture/assets/components/about-city.css`.
- Docs updated: `docs/theme-dcculture.md` (the hook, the `homeVideo` variable, the fallback, the row `home/about-city` in "Data seams", and a note in "Video card and modal" that the card is also used on the home page). `docs/site-themes.md` and `docs/theme-templates.md` describe the hook mechanism in general and do not change.
- Specs: delta of `openspec/specs/dcculture-theme/spec.md`.
- Data: needs at least one visible `video` record with a `youtube` value for the video to appear; otherwise the static picture is shown.
