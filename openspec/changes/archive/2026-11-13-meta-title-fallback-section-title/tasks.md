# Tasks

## 1. Title fallback in the code

- [x] 1.1 In `app/Theme/TypeSection.php` add a public method `section(ContentType $type): ?string` returning the Section title (reading language, then `default`; `null` for a non-`content` type or when both are empty) and rewrite `title()`/`resolve()` on top of it (`section($type) ?? $type->name`), keeping the per-request memo. Add an English docblock with `@example`. Verify: `docker compose exec app php artisan test --compact --filter=TypeSection` (and the Theme/Public tests that use it).
- [x] 1.2 In `app/Theme/PageMeta.php` inject `TypeSection` and extend the last title step to `$this->text($own('meta_title')) ?? ($type === null ? null : $this->text($this->sections->section($type)))`; update the class docblock (step 4 and the `@example`). Verify: docblock states the new order.
- [x] 1.3 Run `docker compose exec app vendor/bin/pint --dirty --format agent`.

## 2. Tests

- [x] 2.1 In `tests/Feature/Public/PageMetaTest.php` add cases: Section title used when no `meta_title`; `meta_title` wins over `section_title`; reading-language and base-language fallback; no titles → site name (not the type name); a node with a `title` keeps its `title`. Verify: `docker compose exec app php artisan test --compact --filter=PageMetaTest`; also run `--filter=PublicRoutesTest`.

## 3. Documentation

- [x] 3.1 Update `docs/public-site.md` (the Page meta data table, the "Title" rule, the `$title` row) and `docs/theme-templates.md` (the `$meta` description) with the `meta_title` → `section_title` fallback, in English.
- [x] 3.2 Skip e2e and theme checks: no browser behavior or theme files change; state this in the final report along with the PHP tests that were run.
