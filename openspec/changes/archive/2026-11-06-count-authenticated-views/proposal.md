## Why

The owner opens a node page on the public site while signed in to the admin panel, and the view counter does not grow, because `ViewCounter` skips authenticated users (and, as a guest, a second visit within 24 hours is skipped by the per-visitor dedup). The owner wants every visit counted — signed-in users included, with no per-visitor limit — and a freshly created node to start with one view instead of zero.

## What Changes

- `ViewCounter` stops excluding authenticated users and turns the per-visitor dedup off by default: every `GET` of a node page that is not made by a bot counts +1. Both behaviors are configurable in `.env`: `CONTENT_VIEWS_TTL` (seconds, default `0` = no dedup; a positive value brings back the once-per-visitor-per-period rule with the atomic `Cache::add` key) and `CONTENT_VIEWS_COUNT_AUTHENTICATED` (default `true`; `false` excludes signed-in users as before). The bot filter, the `GET`-only rule, and `content.views.enabled` stay.
- A node created through the application starts with `views = 1`: the default of the `$views` argument of `ContentNodeCreator::create()` becomes `1`. Callers that pass a value (the legacy import) are unaffected. The `views` column default stays `0`, so existing nodes and rows inserted outside the creator keep `0`; no data migration.
- Tests, docs, and the two affected main specs are updated.

## Capabilities

### New Capabilities

None.

### Modified Capabilities

- `public-site`: requirement "Counting views of public node pages" counts authenticated users and does not limit counting per visitor by default; both are switchable by config (`count_authenticated`, `ttl`), with scenarios for each.
- `content-model`: requirement "Service attributes of a node": a node created through the application starts with `views = 1`; the scenarios "Default view counter" and "Counter is not accepted from a request" change.

## Impact

- Code: `app/Content/ViewCounter.php` (make the user check and the cache dedup conditional on config, update the class comment), `config/content.php` (`views.ttl` default `0` from `CONTENT_VIEWS_TTL`, new `views.count_authenticated` from `CONTENT_VIEWS_COUNT_AUTHENTICATED`, comments), `.env.example` (both variables), `app/Content/ContentNodeCreator.php` (default `$views = 1`, docblock), `app/Models/ContentNode.php` docblock if it mentions the default.
- Tests: `tests/Feature/Content/ViewCounterTest.php`, `tests/Feature/Public/PublicViewsTest.php`, tests of the creator and of admin node creation that assert `views` equals `0` (found by `grep -rn "views" tests`).
- Docs: `docs/public-site.md` (view counter section), `docs/content-model.md` (setting `views`, default on creation).
- Specs: `openspec/specs/public-site`, `openspec/specs/content-model` (via delta specs).
- Interaction with the open change `import-legacy-site`: its importer passes `views` explicitly, so it is not affected; its delta specs do not touch these requirements.
