# Tasks

## 1. Link to the site in the header

- [x] 1.1 Add to `AdminHeader` the icon button `ExternalLink` (`<a href="/" target="_blank" rel="noopener noreferrer">`, `aria-label` and `title` «Open site») before `LocaleSwitcher`; JSDoc in English; add the key «Open site» to the Ukrainian translation.
- [x] 1.2 Add to `e2e/admin-shell.spec.js` a check: the button is there, `href="/"`, `target="_blank"`; run `npm run test:e2e -- e2e/admin-shell.spec.js`.
- [x] 1.3 Update `docs/admin-shell.md` (the header: the link to the site).

## 2. Button «Перегляд» ("Preview") in the form of a record

- [x] 2.1 In `ContentNodeController::edit` add the prop `publicUrl` (`/{slug}` for `page`, `/{type.slug}/{slug}` for `content`, `null` for `block`) with a comment in English.
- [x] 2.2 Add Feature tests of the prop for page, content and block; run `docker compose exec app php artisan test --compact --filter=ContentNode`.
- [x] 2.3 Pass `publicUrl` from `Edit.tsx` to `ContentNodeForm` and show in `FormActionBar` the button «Перегляд» ("Preview") (`target="_blank"`, `rel="noopener noreferrer"`) only in the mode `edit` and when `publicUrl` is not `null`; JSDoc in English; the translation «Preview» → «Перегляд».
- [x] 2.4 Add to `e2e/admin-content-nodes.spec.js` the scenarios: the button is in the editing of a record with the correct `href`/`target`, it is absent in the form of creating and for a block; run `npm run test:e2e -- e2e/admin-content-nodes.spec.js`.
- [x] 2.5 Update `docs/admin-content-nodes.md` (the button «Перегляд» ("Preview"), the rules of the address, 404 for invisible ones) and, if needed, `docs/e2e-testing.md`.
- [x] 2.6 Run `docker compose exec app vendor/bin/pint --dirty --format agent`.
