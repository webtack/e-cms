# Design

## Context

Таксономія вже містить усе, що потрібно меню з боку структури: дерево з одним батьком (`taxonomy_terms.parent_uuid`, `weight`), незмінний `key` контейнера, окрему таблицю перекладів, одне зображення з обліком у `media_relations`, інваріанти в моделях і `TermWriter`/`TermTree` у `App\Taxonomy`, а в адмінці — розділ `/admin/taxonomy` із деревом у `DataTable`, формою з вибором батька й перетягуванням. Видимість за розкладом уже є у `content_nodes` (`is_active`, `published_at`, `ContentNode::scopeVisible()`), але там `published_at` обов'язкова і немає дати зняття. Публічного фронтенду й прив'язки адрес до записів поки немає. Мотивацію й вимоги див. [proposal.md](proposal.md) і [specs/](specs/).

Описують компоненти: новий `docs/menus.md` (домен, `MenuTree`), новий `docs/admin-menus.md` (розділ адмінки), `docs/taxonomy.md` і `docs/admin-taxonomy.md` (зразок, з яким узгоджено рішення), `docs/media-library.md` (реєстр власників), `docs/admin-shell.md`, `docs/e2e-testing.md`.

## Goals / Non-Goals

**Goals:**
- Повторити перевірені рішення таксономії (ключ, `uuid`, інваріанти в моделях, переклади, зображення, адмінка), щоб розділи поводилися однаково й не вимагали нового досвіду від редактора й розробника.
- Видимість рахується на читанні: жодних cron-завдань, черг і подій для публікації та зняття.
- Один запит на пункти меню (плюс один на переклади) незалежно від глибини дерева.

**Non-Goals:**
- Спільна абстракція «дерево з вагами» для термінів і пунктів: рефакторинг таксономії поза цією зміною.
- Публічні маршрути, Blade-компоненти й кешування виводу меню.
- Прив'язка пункту до запису контенту; кешування дерева.

## Decisions

### 1. Окремі таблиці за зразком таксономії

```
menus(id, key UNIQUE, name, description null, settings JSON null, timestamps)
menu_items(id, uuid UNIQUE,
           menu_key FK → menus.key RESTRICT,
           parent_uuid FK self → menu_items.uuid RESTRICT null,
           weight int default 0,
           url varchar(2048) null,
           target varchar(8) default '_self',
           css_class varchar(255) null,
           image varchar(64) null,
           is_active bool default true,
           published_at datetime null,
           unpublished_at datetime null,
           timestamps;
           INDEX(menu_key), INDEX(parent_uuid, weight))
menu_item_translations(id, item_uuid FK → menu_items.uuid CASCADE,
                       lang (бінарна колація), title, description null, timestamps;
                       UNIQUE(item_uuid, lang))
```

Ідентифікація, колації `lang`, самопосилання, додане окремим `Schema::table`, і `RESTRICT` на всіх зовнішніх ключах, крім перекладів, збігаються з `create_taxonomy_tables`. `slug` у пункту немає: його ніхто не адресує, а адмінка адресує за `uuid`. Нова міграція лише створює таблиці, наявні дані не чіпає; відкат їх видаляє.

**Альтернативи:** пункти як вузли контенту (`ContentNode`) — тягнуть глобально унікальний `slug`, службові атрибути й EAV-значення, які для фіксованих полів пункту не потрібні; налаштування меню в `app_settings` — немає структури дерева. Відхилено.

### 2. Інваріанти — у моделях, запис — у `MenuItemWriter`

Моделі `Menu`, `MenuItem`, `MenuItemTranslation` у `app/Models` перевіряють у `saving`/`deleting` (`ValidationException`): формат і незмінність `key`, `max_depth`, батько з того самого меню, відсутність циклів, глибину з урахуванням піддерева, незмінність меню пункту, `target`, `css_class`, `url` та інтервал дат, а також заборону видалення батька з дітьми й меню з пунктами. Сервіс `App\Menus\MenuItemWriter` (`create(Menu, array)`/`update(MenuItem, array)`) в одній транзакції записує пункт, переклади (`ContentLanguages::assertWritable`, `default` обов'язковий, порожній неосновний переклад видаляється) і зображення (`FileRelations::sync('menu_item', $id, 'image', 'default', …)`, `FileValue`); хук `deleting` викликає `FileRelations::forgetOwner('menu_item', $id)`. Без `slug` генерувати нічого.

Логіку обчислення глибини, висоти піддерева й виявлення циклів я пишу в `App\Menus\MenuItemTree` за тим самим алгоритмом, що в `TermTree`, а не виношу в спільний трейт (Non-Goal): так таксономія лишається без змін, а дублювання невелике (≈ 150 рядків) і прозоре. Якщо третя деревоподібна сутність з'явиться, спільну абстракцію виділять тоді.

### 3. Адреса: одна точка перевірки, спільна для моделі й запитів

Правила форми адреси живуть в одному місці (`App\Menus\MenuUrl`): `isValid(string)`, `isExternal(?string)`. Модель викликає її в `saving` (остання межа), `StoreMenuItemRequest`/`UpdateMenuItemRequest` — у правилі поля `url`, тож клієнт бачить помилку біля поля, а код/імпорт не обминає перевірку.

Правило: рядок до 2048 символів без пробілів і керівних символів; або починається з `/` (але не `//`), `#`, `?`; або розбирається як URL із схемою зі списку дозволених (`http`, `https`, `mailto`, `tel`). Дозволений список схем, а не заборонений (`javascript:`, `data:`, `vbscript:`…): будь-яка схема поза списком відхиляється. `//host` відхиляється, бо протокол-відносна адреса неоднозначна (залежить від схеми поточної сторінки) і легко плутається з відносним шляхом. Порожній рядок нормалізується до `NULL`. `isExternal`: `true` для чотирьох схем, `false` для відносних, `null` для `NULL`. Колонка для цього не потрібна: значення виводиться з `url`.

### 4. Видимість рахується на читанні

Прапорець `is_active` плюс `published_at`/`unpublished_at` (UTC, до хвилини, як `published_at` записів) і порівняння з `now()`:

```
visible(item) = is_active
                AND (published_at   IS NULL OR published_at   <= now)
                AND (unpublished_at IS NULL OR unpublished_at >  now)
visible_in_tree(item) = visible(item) AND visible_in_tree(parent)   // корінь: visible(item)
```

`MenuItem::status(?CarbonInterface $now)` повертає `disabled | scheduled | expired | published` (порядок перевірок: вимкнено, заплановано, завершено) і слугує адмінці. Передача `now` у метод робить його детермінованим у тестах. `MenuItem::scopeVisible()` лишається для плоских запитів, але не знає про предків; гілки відсікає `MenuTree`. Дати інтервалу валідуються: `unpublished_at > published_at`, коли задані обидві.

**Альтернативи:** планувальник, що перемикає `is_active` за розкладом, — стан розходиться з розкладом при збоях і стирає намір редактора; дату зняття в тій самій колонці — не вкладається. Обчислення на читанні відхиляє обидва ризики й збігається з тим, як уже працює видимість записів.

### 5. `MenuTree`: два запити й обхід у пам'яті

`App\Menus\MenuTree::tree(string $menuKey, ?string $lang = 'default')`: перевіряє меню (`ModelNotFoundException` для невідомого ключа) і мову (`ContentLanguages::assertReadable`), одним запитом читає пункти меню (`orderBy weight, id`) і одним — переклади потрібної мови для їхніх `uuid`; будує карту дітей за `parent_uuid` і обходить від коренів, не заходячи в невидимі вузли (так гілка відсікається автоматично й циклів не боїмося, бо інваріанти їх виключають; захист від зациклення додатково тримає перелік відвіданих `uuid`). Вузол: `uuid`, `title`, `description` (без fallback), `url`, `target`, `css_class`, `image`, `is_external`, `children`. `now` береться раз на виклик, тож усе дерево узгоджене. Окремий метод `adminTree(Menu, lang)` для адмінки повертає всі пункти зі статусами (без відсікання); реалізація спільна, прапорець `onlyVisible`.

### 6. Адмінка: копія структури розділу таксономії

Маршрути `/admin/menus/**`, `Route::bind('menu')` за `key`, `Route::bind('item')` за `uuid` у межах меню (404 для чужого), `reorder` оголошений перед `{item}`. Контролери `MenuController`, `MenuItemController`, запити `Store/UpdateMenuRequest`, `Store/UpdateMenuItemRequest`, `ReorderMenuItemsRequest`. Сторінки `Pages/Admin/Menus/*` і `Pages/Admin/Menus/Items/*`, форми `MenuForm`, `MenuItemForm`, `MenuRowActions`, `MenuItemRowActions` у `Components/Admin/Menus`. Сторінка пунктів — клієнтський `DataTable` з `reorder` (як у термінів); колонка «Статус» — `MenuItem::status()`. У формі пункту: `parentOptions: {uuid, name, depth}` рахує сервер (виключає сам пункт і нащадків, враховує `max_depth` та висоту піддерева), `defaultWeight`, мови з `ContentLanguages`, зображення — `FileUpload` з `profile="image"`. Дати — `datetime-local` (до хвилини), `is_active` — перемикач. Порядок: рівно всі `uuid` меню, `weight = позиція`, прямий `UPDATE` у транзакції. Видалення пункту: діалог без підтвердження, якщо є діти (на відміну від термінів, використань немає).

**Альтернатива:** окрема сторінка для перетягування між батьками — відкладено (Non-Goal, як і в таксономії).

### 7. Медіатека

`App\Files\Owners\MenuItemOwner` (`MediaOwner`) реєструється в `config/files.php` як `menu_item`; `describe(array $ids)` за один прохід повертає базову назву й посилання `/admin/menus/{menu_key}/items/{uuid}/edit`.

## Risks / Trade-offs

- [Дублювання логіки дерева з `TermTree`] → навмисно, див. рішення 2; обидві реалізації покриті окремими тестами, а рефакторинг можливий окремою зміною.
- [Невидима гілка ховає видимих нащадків, редактор може цього не помітити] → адмінка показує статус кожного пункту за його власними даними, а не успадкований; рядок нащадка під невидимим батьком не позначається окремо. Прийнято: поведінка описана в [docs/menus.md](../../../docs/menus.md), а підказку в таблиці можна додати окремо.
- [Фронтенду ще немає, тож `MenuTree` перевіряється лише тестами] → покриваємо сценарії спеки Feature-тестами з фіксованим `now`.
- [Дані `published_at`/`unpublished_at` в UTC без перетворення поясів] → як у записів; редактор бачить час у тому ж вигляді, що й в записах.
- [Дозволений список схем URL відхиляє легітимні `ftp:`, `sms:`] → список легко розширити в `MenuUrl`; спека зараз фіксує чотири схеми, зміна потребує оновлення вимоги.

## Migration Plan

1. Нова міграція створює три таблиці; даних для перенесення немає.
2. Розгортання: `php artisan migrate`, звичайна збірка фронтенду. Нових змінних середовища й залежностей немає.
3. Відкат: `migrate:rollback` видаляє таблиці; перед цим варто видалити меню через модель, щоб зняти зв'язки зображень у `media_relations` (як для таксономії), інакше записи `menu_item` у `media_relations` лишаться висячими.
