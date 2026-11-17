# Proposal

## Why

The list of the `events` type (`/events`, `/events?period=archive`) is rendered by the generic `content/list` and `content/preview`: a plain row with a title and the fields printed one after another. Events are posters, mostly in portrait orientation, so the list should be rows of poster cards with a readable date, like the "Afisha" block on the home page. The home block also prints dates in its own numeric format (`13.11.2026`, `05.11 – 22.11, 2026`), which differs from the news dates of the theme (day and month name) and shows the year always.

## What Changes

- `events` list: a theme template `events/list` with one card per row, the pagination and the archive link below it.
- `events` card: a theme template `events/preview`: the whole poster (never cropped) on the left, the date, the title and a short plain-text excerpt of `body` (when present) on the right (the poster above the content on a phone); the whole card is the link. a record without a poster gets a card without the picture.
- `events` details page: a theme template `events/details`: a light grey block with the small poster (opens the photo lightbox) and the dates under it, then the title and, when there is a text, a divider and the text.
- One date format for events, in one shared partial: the start date only when the end date does not differ (same calendar day, or a single value); a range otherwise. Month as a word in the genitive case (`months.full`), the year only when it is not the current year, no time. The home "Afisha" block uses the same partial and loses its numeric pills.
- The dates are the stored wall-clock values read in the application timezone (`config('app.timezone')`); nothing is converted.
- **BREAKING (visual only):** the dates in the home "Afisha" block change from `13.11.2026` / `05.11 – 22.11, 2026` to `13 листопада` / `5 – 22 листопада`.

## Capabilities

### New Capabilities
- `dcculture-events`: the theme's rendering of the `events` type: the dates of an event and the card and rows of its list.

### Modified Capabilities
- `dcculture-theme`: the requirement "Home block "Afisha" shows the nearest events" prints event dates in the same format as the list.

## Impact

- Code (theme `dcculture` only): `views/events/list.blade.php`, `views/events/preview.blade.php`, `views/partials/event-dates.blade.php` (new); `views/partials/home/news-afisha.blade.php` (uses the partial; the inline `$afishaDates` closure is removed); `assets/components/events.css` (new, imported in `assets/app.css`); `hooks/home-afisha.php` docblock if its `from` / `to` contract changes. No core code, no migrations.
- Tests: none, by the project rule for theme frontend; verification is `php artisan theme:check`, `npm run build` and a visual comparison.
- Docs updated: `docs/theme-dcculture.md` (events list and card, the date format, home afisha pills); `docs/README.md` links stay valid.
- Specs: new `dcculture-events`; delta for `dcculture-theme`.
- Related change: `app-timezone-env` sets the application timezone to `Europe/Kyiv`. This change does not depend on it: it reads `config('app.timezone')`, whatever it is.
