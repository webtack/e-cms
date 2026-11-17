## 1. Frame and link addresses in rich text

- [x] 1.1 `IframeSrcSanitizer` rejects a path with a segment `.` or `..`, a backslash, or `%2e`, `%2f`, `%5c` (any letter case); document the rule in its docblock in English. Verify: new cases in `tests/Feature/Content/HtmlSanitizerTest.php` pass with `docker compose exec app php artisan test --compact --filter=HtmlSanitizerTest`: `/embed/../account`, `/embed/%2e%2e/account`, `/embed/%2E%2E/x`, `/embed/a%2fb`, `/embed/a%5cb` remove the frame, and the existing frame tests (YouTube, Drive, Forms, Maps, Facebook) stay green.
- [x] 1.2 Add the `a[href]` sanitizer (for example `LinkHrefSanitizer`) that drops an address starting with `//` or containing a backslash, register it in `HtmlSanitizer`, and comment it in English with an `@example`. Verify: new cases in `HtmlSanitizerTest` (`//example.com/a` and `/\example.com` lose the `href` and keep the text; `/about`, `#top`, `?page=2`, `https://example.com/a`, `mailto:`, `tel:` keep it) pass, and `docker compose exec app php artisan test --compact --filter=RichTextFieldTypeTest` stays green.
- [x] 1.3 Update `docs/content-values.md` (the `iframe` bullet: the path rule; a new bullet for links: `//` and backslash). Verify: the text states the same rules as the spec and the code.

## 2. Menu item address

- [x] 2.1 `MenuUrl::isValid` rejects a backslash anywhere in the address. Verify: `tests/Feature/Menus/MenuUrlTest.php` gets `/\evil.example` and `/a\b` in the invalid cases, `/%5Cx` stays valid, and one HTTP case in `tests/Feature/Menus/MenuItemAdminTest.php` shows an error under `url`; run `docker compose exec app php artisan test --compact --filter=MenuUrlTest` and `--filter=MenuItemAdminTest`.
- [x] 2.2 Update `docs/menus.md` (the item address rule). Verify: the doc matches the spec.
- [x] 2.3 Format PHP: `docker compose exec app vendor/bin/pint --dirty --format agent`. Verify: Pint reports no remaining issues.
