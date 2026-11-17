# Proposal

## Why

Список записів уже вміє читати `content_types.settings.list` (`visible`, `search`, `sort`), але в адмінці немає місця, де це можна задати: форма типу редагує лише назву, slug, батьківський тип і опис, а `UpdateContentTypeRequest` не приймає `settings`. Колонки, пошук і порядок наразі налаштовуються тільки через tinker. Службові колонки (`slug`, стан, дата публікації) клієнт жорстко дописує в кінець, їх не можна ні сховати, ні переставити.

## What Changes

- Секція «Список записів» на сторінці «Налаштування» типу (`/admin/{section}/{key}/edit`) для всіх трьох видів (`content`, `page`, `block`): вибір колонок і їхній порядок (перетягуванням), поля пошуку, сортування за замовчуванням (поле + напрямок).
- Усі службові атрибути запису стають звичайними ключами в `settings.list.visible` і беруть участь у порядку колонок: `slug`, `status`, `is_active`, `created_at`, `updated_at`, `published_at`, `unpublished_at`. `title` теж ключ у `visible`: її позицію можна змінювати, але колонка завжди присутня й не ховається; без ключа `title` у `visible` вона стоїть першою.
- Сортування за замовчуванням у формі за початковим вибором — «Дата створення» (`created_at`), а не `title`. Сортуються й службові колонки `status`, `is_active`, `created_at`, `updated_at`, `published_at`, `unpublished_at` (`slug` — ні); вони ж допустимі як поле сортування за замовчуванням разом із сортованими полями типу.
- `UpdateContentTypeRequest` (і створення типу) приймають `settings.list` з перевіркою ключів і напрямку; збереження оновлює лише ключ `list` і не затирає інші ключі `settings`.
- `ContentNodeController` і клієнтська таблиця будують колонки в порядку `visible`, включно зі службовими; порожні налаштування зберігають сьогоднішній вигляд (`title`, далі slug, стан, дата публікації).
- Значення множинних полів у списку виводяться стовпчиком (список значень, а не рядок через кому). Поле `image` виводиться мініатюрою-посиланням на файл (нова вкладка), поле `file` — оригінальною назвою файлу посиланням у новій вкладці: `pdf` відкривається браузером, інші типи пропонують завантаження (`download`).
- `ContentNodeQuery` розуміє службові поля сортування: `created_at`, `updated_at`, `unpublished_at` (порожні в кінці; далі `id`, без `title`), а також `status` (похідний стан) і `is_active`.
- Ключі `is_active`, `created_at`, `updated_at`, `unpublished_at` стають зарезервованими ключами полів (`ContentField::RESERVED_KEYS`): нове поле з таким ключем створити не можна, наявні лишаються чинними й не пропонуються як колонки.
- Особисте приховування колонок у `localStorage` (кнопка «Колонки») лишається й працює поверх значень типу за замовчуванням.

## Capabilities

### New Capabilities

Немає.

### Modified Capabilities

- `admin-content-nodes`: колонки таблиці записів будуються з `settings.list.visible` разом зі службовими колонками (зокрема `is_active`, `created_at`, `updated_at`, `unpublished_at`) та їхнім порядком; службові колонки сортуються (крім slug), у тому числі за замовчуванням.
- `admin-content-types`: форма редагування типу отримує секцію налаштувань списку записів, збереження приймає й перевіряє `settings.list`.
- `content-querying`: сортування за службовими атрибутами (`created_at`, `updated_at`, `unpublished_at`, `status`, `is_active`) і допустимі значення `settings.list`.
- `content-model`: перелік зарезервованих ключів полів розширюється ключами `is_active`, `created_at`, `updated_at`, `unpublished_at`.
- `admin-content-fields`: форма створення поля відхиляє ці ключі як зарезервовані.

## Impact

- Код: `app/Models/ContentType.php` (`assertValidListSettings`), `app/Models/ContentField.php` (`RESERVED_KEYS`), `app/Http/Requests/Admin/StoreContentFieldRequest.php`, `app/Http/Requests/Admin/StoreContentTypeRequest.php` і `UpdateContentTypeRequest.php`, `app/Http/Controllers/Admin/ContentTypeController.php` (передача полів типу у форму, злиття `settings`), `ContentNodeController.php` (`listSettings`, `tableColumns`, `row`, формат значень файлових і множинних полів), `app/Content/ContentNodeQuery.php` (`applySort`), `resources/js/Components/Admin/ContentTypes/ContentTypeForm.tsx` + новий компонент секції, `resources/js/Pages/Admin/ContentTypes/Edit.tsx`, `resources/js/Pages/Admin/ContentNodes/Index.tsx`, переклади `en`/`uk`.
- Тести: Feature (`ContentTypeEditTest`, `ContentNodeIndexTest`, за потреби `PageTypeTest`/`BlockTypeTest`), тести `ContentNodeQuery`, e2e `admin-content-types` і `admin-content-nodes`.
- Docs (оновити): `docs/admin-content-types.md`, `docs/admin-content-nodes.md`, `docs/content-querying.md`, `docs/content-model.md`, `docs/admin-content-fields.md`, `docs/e2e-testing.md` (якщо змінюються таблиці «область → спек»).
- Міграцій і нових залежностей немає: `settings` уже JSON-колонка типу.
