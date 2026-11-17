## Why

The auth audit found that request limits cover only two routes besides the login form. The login limit is per email and IP pair (5 failures), so one address can try five passwords for every new email without end (credential stuffing). The public site search runs a `LIKE` query on every request with no limit, and the authenticated helper endpoints that cost real resources (file upload, the YouTube lookup that spends an external API quota, the autocomplete lookups) have none either. These are low-severity, but they are cheap to close and nothing in the specification states a limit today.

## What Changes

- **Login.** Besides the existing limit (5 failed attempts per email and IP pair), failed attempts are also counted per IP address across all emails; after a set number the login answers with the same throttle message until the window ends. A successful login does not reset the per-IP counter (an attacker with one valid account must not be able to reset it).
- **Public search.** `/search` is limited per IP address; over the limit it answers 429.
- **Helper endpoints.** `POST /admin/files`, `GET /admin/lookup/content-types`, `GET /admin/lookup/nodes` and `POST /admin/youtube/lookup` are limited per signed-in user; over the limit they answer 429. The YouTube lookup gets a tighter limit because each call may spend the external API quota.
- The numbers are configuration with defaults, so an installation behind a proxy or with heavy editors can change them without code.

## Capabilities

### New Capabilities
<!-- None. -->

### Modified Capabilities
- `admin-auth`: adds the requirement that login attempts are limited per email and IP pair and per IP address (the per email and IP rule exists in the docs but not in the main spec).
- `public-site`: adds the requirement that the site search is rate limited.
- `access-control`: adds the requirement that the resource-heavy helper endpoints are rate limited per user.

## Impact

- Code: `app/Http/Requests/Auth/LoginRequest.php` (a second limiter key), `routes/web.php` (throttle middleware on the search route and the four helper routes), named limiters in `app/Providers/AppServiceProvider.php`, a new `config/limits.php`, and `.env.example`.
- Documentation (English): `docs/admin-auth.md` (the rate limiting section), `docs/public-site.md` (the site search section), `docs/access-control.md` (helper endpoints), and `docs/e2e-testing.md` if the e2e login behaviour needs a note.
- Tests: `tests/Feature/Auth/LoginTest.php` (per-IP limit), `tests/Feature/Public/SearchRouteTest.php` (429), and a new `tests/Feature/Access/HelperEndpointLimitsTest.php`.
- No migrations, no new dependencies. Behind a reverse proxy the per-IP limits depend on the trusted proxy configuration (see design).
