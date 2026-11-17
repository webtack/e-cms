# Design

## Context

See `proposal.md` for the motivation. Current state, read from code and docs:

- `/events` is rendered by `themes/dcculture/views/content/list.blade.php` (a flex column, `.record-list`) and `content/preview.blade.php` (title with a dot and `@fields`). The `news` type has its own `news/list` and `news/preview`; the theme resolves `{type}/list` and `{type}/preview` before `content/*`.
- A theme template of a type with an archive must include `partials/archive-link.blade.php` itself (`docs/theme-dcculture.md`, "Archive link").
- The list card receives `$node`, `$type`, `$values` (the `list_fields` of the type: `title`, `date`, `poster`, `body`), `$fields`, `$url`. `$values['date']` is an array of `CarbonImmutable` objects in the application timezone; `$values['poster']` is a raw `{uuid}.{ext}` string (multiple fields give a list; the first value is used).
- `news/preview` chooses between image styles from the size of the `media` row that the list has already loaded (`PresentContext::file()`).
- The home "Afisha" block formats dates with an inline closure `$afishaDates` in `partials/home/news-afisha.blade.php` (numeric `d.m.Y`), reading `from` / `to` that `hooks/home-afisha.php` builds from the first and last `date` value.
- Month names: `lang/{locale}/months.php`, key `full` (genitive). The news dates use `d month` without a year.
- Stored dates are wall-clock values (see `app-timezone-env`, `docs/content-model.md`); the right way to show them is as they are, in the application timezone.

## Goals / Non-Goals

**Goals:**
- A list of portrait poster cards (one per row) for `events`, same on the list and the archive.
- One date format for events in one place, used by the list card and the home block.

**Non-Goals:**
- Showing the time of the event, the excerpt, a "read more" label or the view counter on the card.
- The event details page (`events/{slug}`) and its date; a separate change if wanted.
- Changing the selection, the sort or the archive logic of `events`.
- Changing the application timezone (that is `app-timezone-env`).
- Tests: the theme is frontend, so no PHP or e2e tests are added (project rule).

## Decisions

**1. Own templates `events/list` and `events/preview`.** Same mechanism as `news`, no core change and no new hook. The list copies `content/list` with a list class and includes the archive link and the pagination. Alternative: a modifier class on `content/list` chosen by type; rejected, because per-type templates are the established way and keep generic lists untouched.

**2. One partial for the dates, `partials/event-dates.blade.php`.** It takes the list of `date` values (or `from` / `to`), compares the **calendar days** in the application timezone (`isSameDay`), and prints the text by the rules of the spec, with month names from `trans('months.full')`. The list card and the home block both include it, so there is one rule. The home hook keeps providing `from` and `to`; the partial accepts the two ends. Alternative: a Blade directive or a PHP helper class; rejected as more infrastructure than one partial needs, and the theme has no PHP namespace of its own.

**3. No timezone conversion; the zone is the application's.** The values are `CarbonImmutable` already in `config('app.timezone')`; the partial calls `->timezone(config('app.timezone'))` explicitly so that the intent is visible (a no-op for these values) and takes "today" and "the current year" from `now()` in the same zone. The result does not change when `app-timezone-env` lands: only the boundary of the current year/day does.

**4. Year only when it differs from the current year.** Keeps cards short; an event in a later year is explicit. Rule for ranges is in the spec. Alternative: always show the year (the old site does); rejected by the owner.

**5. Poster whole, by its own ratio.** The poster column has a fixed width (160 px, 200 px from `lg`; 200 px centred above the content on a phone) and the `<img>` is `w-full h-auto`, so the poster is never cropped and its height follows the picture, at any ratio. The `width` / `height` attributes come from the already loaded `media` row (`PresentContext::file()`), the file is not opened, as in `news/preview`; 300 × 400 without a row. Image style: `750x` (width only), since the column is at most 200 px wide (2x = 400 px). Alternative: a fixed 3:4 box with `object-cover` or a blurred fit; rejected, because posters must not lose any part of the picture, and a very tall poster only makes its row taller.

**6. Rows by CSS only.** A flex column of cards with the gap of the theme; each card is a flex row from `sm` (poster column 160 px, 200 px from `lg`, content beside it) and a column below `sm` (poster on top, 200 px, centred); no JavaScript. The card is one `<a>`, as `news-wide`.

**7. Styles in `assets/components/events.css`.** A new component file imported in `assets/app.css` next to `afisha.css`. The date uses the existing `date-pill` look? Open: see Open Questions; default is the muted `news-meta` style (clock icon, text) used by the news cards, so the date reads the same on news and events.

## Risks / Trade-offs

- [A very tall poster makes its row tall] → accepted: the poster is never cropped.
- [The home block and the list must stay identical] → one partial; the task checks both pages.
- [Dates that cross the new-year boundary read oddly] → covered by the year rule and spec scenarios; checked by hand with three sample values.
- [`events` key and the fields `date`, `poster`, `title` are fixed in the theme] → same as `news` and `home-afisha`; documented.

## Migration Plan

1. Add the templates, the partial and the CSS; `npm run build` on the host.
2. `docker compose exec app php artisan theme:check`.
3. Compare `/events`, `/events?period=archive` and the home page with the layout at 375, 768, 1024, 1440 px.
4. Rollback: remove `views/events/*` (the generic list returns) and restore the closure in `news-afisha.blade.php`.

## Open Questions

- The look of the date on the card: the muted `news-meta` line with the clock icon (default) or the amber `date-pill` that the home "Afisha" cards use now. This only changes one class in the card template and the CSS, not the specs or the tasks.
