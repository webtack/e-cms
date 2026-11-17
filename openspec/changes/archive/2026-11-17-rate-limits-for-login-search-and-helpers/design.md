## Context

See proposal.md - Why. Current state, read from the code:

- `routes/web.php` has `throttle` middleware only on `POST /locale` (30 per minute) and `PUT /admin/profile/password` (6 per minute). The search route `public.search`, `admin.files.store`, `admin.lookup.*` and `admin.youtube.lookup` have none.
- `LoginRequest` counts failures under the key `lower(email)|ip` (5 attempts, the default one-minute decay) and clears it on success; there is no other key, so the IP address is never limited as a whole.
- There are no named limiters (`RateLimiter::for`) and no `config/limits.php`. `bootstrap/app.php` configures no trusted proxies, so the client address is the address of the direct peer.
- The throttle message `auth.throttle` already exists for the login form. A 429 response on a JSON or Inertia request is rendered by the framework; the theme has an `errors/404` template but no `errors/429`.
- Per the project rules, a change touching providers, configuration or authentication needs the full PHP test run, and a change to authentication needs the full e2e run.

Documentation describing these components: `docs/admin-auth.md` (rate limiting), `docs/public-site.md` (site search), `docs/access-control.md` (helper endpoints).

## Goals / Non-Goals

**Goals:**
- No endpoint that runs a database search, stores a file or spends an external quota is open to unlimited requests from one client.
- Limits are configuration with sensible defaults and do not change the behaviour or the access checks within the limit.

**Non-Goals:**
- A global limit on all admin routes, account lockout, CAPTCHA, or notifications about attacks.
- A theme template for 429 (the default framework response is used).
- Trusted proxy configuration (documented as a deployment requirement, not changed here).

## Decisions

**1. Named limiters, values in `config/limits.php`.**
`AppServiceProvider::boot` registers `RateLimiter::for('search')`, `'upload'`, `'lookup'` and `'youtube'`; the routes use `throttle:search` and so on. The numbers come from `config/limits.php`, which reads `.env` keys with defaults: search 60 per minute per IP; upload 120 per minute per user; lookup (both reference endpoints) 120 per minute per user; YouTube lookup 20 per minute per user; login per IP 20 failures per 5 minutes. A signed-in user is keyed by the user id and anything else by the IP address.
Why these defaults: they are far above normal human use (a search results page with paging, an upload batch of dozens of files, a debounced autocomplete) and well below a script. They are assumptions: the owner can tune them by `.env` without a release.
Alternative: literal `throttle:60,1` on each route (rejected: the numbers would be scattered over the routes and could not be changed per installation).

**2. A second counter in `LoginRequest`, keyed by IP only.**
On a failed attempt the request hits two keys: the existing `email|ip` one and a new `ip` one with the longer window from the config. Before authenticating it refuses when either is exhausted, with the existing `auth.throttle` message. On success only the `email|ip` key is cleared. Not clearing the IP key is deliberate: an attacker who owns one valid account would otherwise reset the counter after every few guesses.
Alternative: a `throttle` middleware on `POST /login` (rejected: it would count successful requests too and cannot count only failures, and the message would not be the form's field error).

**3. 429 stays the framework response.**
For a search over the limit a guest sees the framework's 429 page; the theme gets no new template in this change. The search form and the other pages are unaffected.

**4. Per-IP limits depend on the real client address.**
Without trusted proxy configuration, behind a load balancer or CDN every visitor appears as one IP, so a per-IP limit would throttle everyone together. This is documented as a deployment requirement in `docs/admin-auth.md` and `docs/public-site.md` (configure trusted proxies when a proxy sits in front), and the limits are tunable through `.env` meanwhile. Making trusted proxies configurable is a separate change.

## Risks / Trade-offs

- [Behind a proxy without trusted proxies all clients share one IP and the per-IP limits fire for everyone] → Documented deployment requirement; the numbers are tunable; the per-email-and-IP login rule is unchanged.
- [A shared office or university IP can reach the per-IP login limit through many users' typing mistakes] → 20 failures per 5 minutes is far above that; tunable.
- [e2e specs log in from one machine and a few of them submit wrong credentials, which now count toward the per-IP limit] → The count is small and the window is 5 minutes; the task runs the full e2e suite once to verify, and the limit is raised in `.env` for the e2e environment if it ever blocks.
- [An editor uploading a very large batch hits the upload limit] → Uploads are sequential, one request per file, 120 per minute; a rejected file shows its error in the form like any other upload failure.
