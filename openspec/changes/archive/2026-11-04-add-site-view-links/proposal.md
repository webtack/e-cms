# Proposal

## Why

З адмінки немає швидкого переходу на публічний сайт, а з форми редагування ноди — на її сторінку. Щоб перевірити результат, адміністратор вручну вводить адресу в браузері.

## What Changes

- У шапці адмін-оболонки з'являється кнопка-іконка «Відкрити сайт»: посилання на головну сторінку сайту (`/`), що відкривається в новій вкладці.
- У формі редагування запису (сторінка, запис `content`) у нижній панелі дій з'являється кнопка «Перегляд»: посилання на публічну адресу запису, що відкривається в новій вкладці.
- Адреса запису будується на сервері: сторінка — `/{slug}`, запис `content` — `/{type.slug}/{node.slug}`; для блоків (власної адреси немає) кнопки немає.
- Кнопка «Перегляд» — просте посилання: для невидимого запису (вимкнений, запланований, завершений) сайт, як і раніше, дасть 404. Окремого маршруту перегляду чернеток немає.
- Нові рядки інтерфейсу перекладаються на українську.

## Capabilities

### New Capabilities

### Modified Capabilities
- `admin-shell`: шапка оболонки отримує кнопку-посилання на сайт.
- `admin-content-nodes`: форма редагування запису отримує кнопку «Перегляд».

## Impact

- Код: `resources/js/Components/Admin/AdminHeader.tsx`, `resources/js/Components/Admin/ContentNodes/ContentNodeForm.tsx`, `resources/js/Pages/Admin/ContentNodes/Edit.tsx`, `app/Http/Controllers/Admin/ContentNodeController.php` (проп `publicUrl`), файли перекладу `uk`.
- Тести: Feature-тест `edit` (проп `publicUrl`), e2e `admin-shell`, `admin-content-nodes`.
- Docs: оновити `docs/admin-shell.md`, `docs/admin-content-nodes.md`; за потреби `docs/e2e-testing.md`.
