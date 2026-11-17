# Tasks

## 1. Посилання на сайт у шапці

- [x] 1.1 Додати в `AdminHeader` кнопку-іконку `ExternalLink` (`<a href="/" target="_blank" rel="noopener noreferrer">`, `aria-label` і `title` «Open site») перед `LocaleSwitcher`; JSDoc англійською; додати ключ «Open site» в український переклад
- [x] 1.2 Додати в `e2e/admin-shell.spec.js` перевірку: кнопка є, `href="/"`, `target="_blank"`; запустити `npm run test:e2e -- e2e/admin-shell.spec.js`
- [x] 1.3 Оновити `docs/admin-shell.md` (шапка: посилання на сайт)

## 2. Кнопка «Перегляд» у формі запису

- [x] 2.1 У `ContentNodeController::edit` додати проп `publicUrl` (`/{slug}` для `page`, `/{type.slug}/{slug}` для `content`, `null` для `block`) з коментарем англійською
- [x] 2.2 Додати Feature-тести пропа для page, content і block; запустити `docker compose exec app php artisan test --compact --filter=ContentNode`
- [x] 2.3 Передати `publicUrl` з `Edit.tsx` у `ContentNodeForm` і показати в `FormActionBar` кнопку «Перегляд» (`target="_blank"`, `rel="noopener noreferrer"`) лише в режимі `edit` і коли `publicUrl` не `null`; JSDoc англійською; переклад «Preview» → «Перегляд»
- [x] 2.4 Додати в `e2e/admin-content-nodes.spec.js` сценарії: кнопка є в редагуванні запису з правильним `href`/`target`, немає у формі створення й для блоку; запустити `npm run test:e2e -- e2e/admin-content-nodes.spec.js`
- [x] 2.5 Оновити `docs/admin-content-nodes.md` (кнопка «Перегляд», правила адреси, 404 для невидимих) і, за потреби, `docs/e2e-testing.md`
- [x] 2.6 Запустити `docker compose exec app vendor/bin/pint --dirty --format agent`
