## 1. Hook

- [x] 1.1 Add `themes/dcculture/hooks/common-children.php` with `preprocess('content.list')`: return unless `$vars['type']->key === 'common'`; take `$type->children()` with a non-empty slug; map to `['title' => TypeSection::title($child), 'url' => url('/'.$slug)]`; sort by title with `Collator('uk')` (fallback: `strcmp` of lower-cased titles); set `$vars['childTypes']`. Header docblock in English with the item shape and an `@example`.
- [x] 1.2 Register the group `common-children` in `themes/dcculture/hooks.php` (the loop list and the file docblock).

## 2. Template

- [x] 2.1 Add `themes/dcculture/views/common/list.blade.php`: a copy of `content/list` plus, after the records list and before the pagination, a `.content-page__list` with one `.content-card` per `$childTypes` item (title link only), printed only when the list is not empty. Header comment in English.

## 3. Docs

- [x] 3.1 Update `docs/theme-dcculture.md`: the Templates tree (`common/list`), the hooks file list, and a section "Common page: child types" (hook, item shape, sort, template).

## 4. Verification

- [x] 4.1 `docker compose exec app php artisan theme:check`.
- [x] 4.2 `npm run theme:build -- dcculture` on the host, then check `/common` visually: cards under the records, sorted by title, links lead to `/common/...`; `/news` and `/collectives` show no cards. No PHP or e2e tests (theme frontend rule).
