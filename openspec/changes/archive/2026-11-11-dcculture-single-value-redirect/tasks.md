# Tasks

## 1. Redirect hook

- [x] 1.1 Confirm in code that `UrlType::present()` works without a prepared `PresentContext` (otherwise use the type's validity method) and that `content.details` and `page` hooks are fired only by `PublicNodeController`; verify by recording the findings in a comment above the callback.
- [x] 1.2 In `themes/dcculture/hooks.php` add one callback registered with `preprocess` on `content.details` and `page`: collect the non-blank items of `$vars['fields']` from `$vars['values']` (multiple fields give a list, `isBlank()` of the field type decides), and when exactly one remains and its field is `file` or `url`, resolve the target (`file_url($value, 'file')`; the `url` value only when acceptable), call `app(ViewCounter::class)->record(request(), $vars['node'])` and throw `HttpResponseException(redirect()->to($target, 302))`. Add English docblock with `@example`. Verify with `docker compose exec app vendor/bin/pint --dirty --format agent` and `docker compose exec app php artisan theme:check`.
- [x] 1.3 Manually check with `APP_THEME=dcculture` on `http://localhost:8080`: a record with one file, a record with one link, a `page` with one link, a multiple field with one and with two items, a record with a file and a text (no redirect), a hidden filled field, a disabled node (404), a list card still linking to `/{type}/{slug}`; confirm `views` grows by one per redirecting request (`docker compose exec app php artisan tinker` or the admin table). Tests are not written for the theme code.

## 2. Documentation

- [x] 2.1 Add a section on the redirect to `docs/theme-dcculture.md` (rule, visible fields only, targets, 302, view counted before the redirect, cards unchanged, no editor bypass) and, if the hooks section of `docs/site-themes.md` needs it, a note that a `preprocess` callback may end the request with `HttpResponseException`; verify that the links between docs stay valid and `docs/README.md` needs no change.
