## Why

The auth audit found that the baseline access suite is red: `RouteCoverageTest` fails on `PUT /admin/settings/languages/x` (404 instead of 403), because the test has no record for the `{language}` route parameter and the model binding runs before the `can:*` check. A permanently red guard test hides real regressions. The same run showed that the binding runs before the access check on every admin route, so a user without the right sees 404 for a missing record and 403 for an existing one (audit finding F5). The owner prefers to accept this and document it instead of reordering the global middleware.

## What Changes

- `RouteCoverageTest` gets a real record for the `{language}` parameter (and is checked for any other parameter that falls back to the placeholder `x`), so the whole admin route map is verified again.
- The test fails with a clear message when a route parameter has no record, instead of silently using the placeholder, so a new route parameter cannot make the guard weaker or red by accident.
- `docs/access-control.md` states the accepted compromise: for routes with a bound record, a user without the right gets 404 for a missing record and 403 for an existing one, so the existence of a record (a type key, a vocabulary key, a menu key, a user id, a role id) can be probed by a user who lacks the section's right. The behavior is unchanged.

No behavior changes, so there is no spec delta (`skip_specs`).

## Capabilities

### New Capabilities
<!-- None. -->

### Modified Capabilities
<!-- None: tests and documentation only. -->

## Impact

- Tests: `tests/Feature/Access/RouteCoverageTest.php` only.
- Documentation (English): `docs/access-control.md` (a short paragraph about the binding order and its consequence for 403/404).
- No application code, no migrations, no dependencies.
