# Tasks

## 1. Date format

- [x] 1.1 Create `themes/dcculture/views/partials/event-dates.blade.php`: input the list of `date` values (or `from` / `to`), output by the rules of the `dcculture-events` spec ("Event dates"): empty gives nothing; one day gives the start; a range names the month once within a month; the year only when it is not the current year (once after the last end of a same-year range, on each end when the years differ); `months.full` for the names; the en dash with spaces; calendar days compared in `config('app.timezone')`; no time. Add a Blade comment in English with the variables and an `@example`. Verify: render the partial with the sample values from the spec scenarios in `docker compose exec app php artisan tinker` (`view('theme::partials.event-dates', [...])->render()`) and check every scenario text.
- [x] 1.2 In `themes/dcculture/views/partials/home/news-afisha.blade.php` replace the inline `$afishaDates` closure and its numeric pills with the partial for the large and the compact cards; update the header comment and the docblock of `hooks/home-afisha.php` if the `from` / `to` contract changes. Verify: `docker compose exec app php artisan theme:check` passes and the home page at `http://localhost:8080/` prints the same dates as the list will.
- [x] 1.3 Document the date format in `docs/theme-dcculture.md` ("Home afisha": the new pill text instead of `d.m.Y`; a new subsection "Event dates") with a cross-link from "Home afisha". Verify: the doc text matches the spec scenarios, and the links resolve.

## 2. List and card

- [x] 2.1 Create `themes/dcculture/views/events/preview.blade.php`: one `<a>` to `$url`; the first `poster` value, `image_style($image, '750x')`, shown whole at the column width with its own height (size attributes from `PresentContext::file()`, 300 × 400 without a size); then the date partial (nothing when empty); then the title (`$values['title'] ?? $node->slug`). Header comment in English with the variables. Verify: the card renders for an event with a portrait poster, a landscape poster, no poster and no date (four sample records or tinker renders).
- [x] 2.2 Create `themes/dcculture/views/events/list.blade.php` (copy of `content/list` with the list class): `page-head`, the list of `@preview($item)`, the pagination and `@include('theme::partials.archive-link')`; header comment in English. Verify: `/events` and `/events?period=archive` respond 200 with cards, the pagination and the archive link, and an empty archive still shows the link.
- [x] 2.3 Create `themes/dcculture/assets/components/events.css` (one card per row: poster left from `sm`, on top below it; card, poster column, date and title styles using the theme tokens) and import it in `assets/app.css` after `afisha.css`; run `npm run build` on the host. Verify: at 375 px the poster is above the content, at 768, 1024 and 1440 px it is on the left, and the page has no horizontal scroll; the long titles are clamped (3 lines).
- [x] 2.4 Update `docs/theme-dcculture.md` with the new section "Events list" (templates, the card, the poster fitting, the rows, the fixed type key `events` and the fields `title`, `date`, `poster`) and add the new files to the files tree; keep `docs/README.md` valid. Verify: the section names the same files and rules as the spec `dcculture-events`.

## 3. Details page

- [x] 3.1 Create `themes/dcculture/views/events/details.blade.php` and the `.event-details*` styles in `events.css`: the grey block with the poster (`x640`, at most 320 px high, `data-lightbox="poster"`) and the dates under it, the title, the divider and `body` in `.news-body` only when `body` has content, then the remaining fields. Header comment in English. Verify: `/events/{slug}` responds 200 for an event with a poster and a text, the poster link has `data-lightbox`, and a click opens the lightbox.
- [x] 3.2 Add the section "Event details page" to `docs/theme-dcculture.md` and the files tree line. Verify: the doc matches the spec requirement "Event details page".

## 4. Check

- [x] 4.1 Run `docker compose exec app php artisan theme:check`, `openspec validate events-list-cards --strict`, and compare `/events`, `/events?period=archive` and the home page with the layout visually; no PHP or e2e tests (theme frontend rule); the report says so. Verify: all checks pass and the visual differences are listed in the report.
