# Tasks

## 1. Обране: схема та сервіс

- [x] 1.1 Міграція `user_favorite_types` (FK `user_id` і `type_key` з `cascadeOnDelete`, унікальна пара), модель і звʼязок `User::favoriteTypes`; перевірка: `docker compose exec app php artisan migrate` проходить, відкат (`migrate:rollback`) прибирає таблицю.
- [x] 1.2 Контролер і маршрути `POST`/`DELETE /admin/favorites/{typeKey}` з перевіркою рівня на тип (403 при `none`), JSDoc/PHPDoc англійською з `@example`; перевірка: `docker compose exec app php artisan test --compact --filter=Favorite` (додавання, прибирання, 403, чужий список не змінюється, каскад при видаленні типу).
- [x] 1.3 Документація обраного: створити `docs/admin-favorites.md` (схема, маршрути, фільтрація за доступами), додати в `docs/README.md` і перехресне посилання з `docs/access-control.md`; перевірка: посилання в індексі працюють.

## 2. Меню адміна: схема, сервіс, сторінка

- [x] 2.1 Міграція `admin_menu_entries` (self-FK `parent_id`, nullable FK `type_key`, `title` JSON, `position`, унікальний `type_key`), модель; перевірка: `migrate` і `migrate:rollback` проходять; для міграцій схеми — повний `docker compose exec app php artisan test --compact` (змінює схему БД).
- [x] 2.2 Сервіс запису меню (створення/перейменування/видалення групи, додавання/видалення посилання, збереження нової структури з порядком і батьком одним запитом) з валідацією глибини 2, унікальності типу і непорожньої назви групи; перевірка: `docker compose exec app php artisan test --compact --filter=AdminMenu`.
- [x] 2.3 Контролер, форм-реквести й маршрути `/admin/settings/navigation` під `can:settings`; перевірка: Feature-тест 403 без права, успішні дії з правом.
- [x] 2.4 Сторінка `Pages/Admin/Settings/Navigation.tsx` (групи, посилання на типи, впорядкування й переміщення між коренем і групою перетягуванням мишею через `@dnd-kit`, редагування назви групи); перевірка: `npx tsc --noEmit` і `npm run build` на хості без помилок; перетягування покриває e2e-завдання 5.1.
- [x] 2.5 Документація меню адміна: створити `docs/admin-navigation-menu.md` і рядок в `docs/README.md`; перевірка: посилання працюють.

## 3. Спільний проп `navigation` і фільтрація за доступами

- [x] 3.1 Сервіс вибірки видимих пунктів обраного й меню за `AccessResolver::nodeLevel` (групи без листків відкидаються), ледачий проп `navigation` у `HandleInertiaRequests` і тип у `resources/js/types/index.d.ts`; перевірка: `docker compose exec app php artisan test --compact --filter=SidebarNavigation` (відкликання доступу ховає пункт, повернення показує, видалений тип зникає, порожня група ховається).
- [x] 3.2 Оновити `docs/access-control.md` розділом про фільтрацію обраного й меню за рівнями; перевірка: розділ посилається на `admin-favorites.md` та `admin-navigation-menu.md`.

## 4. Sidebar і зірочка

- [x] 4.1 `navigation.ts`: групи Settings («Структура», «Доступ») із наявними `visible`, видимість груп, пошук листків у `getBreadcrumbs`/`isActive`; JSDoc англійською з `@example`; перевірка: `npx tsc --noEmit` без помилок.
- [x] 4.2 `AppSidebar.tsx`: блоки Dashboard, «Обране», меню адміна (розкривні групи, іконки за видом), Pages (макет), Media Library, розкривний Settings, що розкривається при активному підпункті; перевірка: `npm run build` на хості, ручний огляд у браузері для користувачів із різними ролями.
- [x] 4.3 Зірочка «Обране» в заголовку `Pages/Admin/ContentNodes/Index.tsx` (проп `isFavorite`, `preserveState`/`preserveScroll`); перевірка: `npm run build`; Feature-тест передає `isFavorite`.
- [x] 4.4 Переклади нових ключів в `lang/en.json` і `lang/uk.json`; перевірка: `docker compose exec app php artisan test --compact --filter=Locale` (або тест перекладів) проходить.
- [x] 4.5 Оновити `docs/admin-shell.md` (розділ Sidebar, «Як додати пункт меню», структура Settings); перевірка: опис збігається з поведінкою `navigation.ts`.

## 5. E2E

- [x] 5.1 Новий спек `e2e/admin-favorites-menu.spec.js` (зірочка додає/прибирає тип у «Обраному», група меню з типом, перетягування мишею змінює порядок і зберігається після перезавантаження, зникнення пункту після зміни ролі, розкритий Settings із підпунктами за правами) і рядки в таблицях «Область → спек» та «Спільний код → спеки» в `docs/e2e-testing.md`; перевірка: `npm run test:e2e -- e2e/admin-favorites-menu.spec.js` на хості.
- [x] 5.2 Адаптувати наявні спеки, що клікають пункти sidebar (оболонка/sidebar змінюється): повний `npm run test:e2e` на хості, бо змінюється оболонка/sidebar; за потреби скинути `locale` на `en` для користувача e2e.

## 6. Доопрацювання sidebar (після огляду)

- [x] 6.1 `navigation.ts`: секції «Структура»/«Доступ» у корені замість Settings, видалити пункт Pages, оновити `visibleSettings`/`isGroupActive` та JSDoc; перевірка: `npx tsc --noEmit`.
- [x] 6.2 `AppSidebar.tsx`: секції в корені (`SidebarGroup` із заголовком), блок меню адміна із заголовком «Navigation» і іконкою (як у «Обраного»), `SidebarFooter` із посиланням на `/admin/settings/navigation` за `can.system.settings`, компактні відступи між групами; перевірка: `npm run build`, огляд у браузері для ролей з різними правами.
- [x] 6.3 Зірочка «Обране» в рядку списку типів: `favoriteKeys` у контролерах списків типів і `Pages/Admin/ContentTypes/Index.tsx` (також у режимі без права `types`); перевірка: `docker compose exec app php artisan test --compact --filter=Favorite` та Feature-тести списків типів.
- [x] 6.4 Переклади: ключ `Navigation` (uk: «Навігація»), решта наявних у `lang/en.json` і `lang/uk.json`; видалити невикористані ключі Pages/Settings, якщо такі є; перевірка: `--filter=Locale`.
- [x] 6.5 Оновити `docs/admin-shell.md`, `docs/admin-favorites.md`, `docs/admin-navigation-menu.md` під нову структуру; перевірка: опис збігається з `navigation.ts`.
- [x] 6.6 E2E: оновити `admin-favorites-menu.spec.js` (заголовок «Navigation», зірочка зі списку типів, футер-посилання, відсутність Pages і Settings) та спеки, що клікають «Settings»/«Pages»; запускати спеки змінної області, а за зміни оболонки/sidebar — повний `npm run test:e2e`.
