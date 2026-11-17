# Proposal

## Why

В українському інтерфейсі `ContentNode` називається «вузол» («Вузли», «Створити вузол», «Вузол створено.»). Власник проєкту вважає цей термін невдалим для редакторів: «вузол» — технічне слово з теорії графів, а не поняття CMS. Обрано «запис» (аналог entries у Strapi, Craft, Contentful): злічуване, нейтральне, не збігається з уже зайнятим «елемент» (повторювані поля) і з «Контент» (`Content`, «Типи контенту»).

## What Changes

- Український переклад: усі форми слова «вузол» (`вузол`, `вузла`, `вузли`, `вузлів`, `вузлам`, `вузлом` …) замінюються відповідними формами слова «запис» у `lang/uk.json`: ключ `"Nodes"` → «Записи», `"Create node"` → «Створити запис», `"Node created."` → «Запис створено.», `"Nodes of “:name”"` → «Записи типу «:name»» та решта рядків із вузлом.
- Очікування e2e-спеків (`e2e/admin-content-nodes.spec.js`, `e2e/admin-content-types.spec.js`) оновлюються під нові підписи.
- Українська документація в `docs/` і формулювання в основних специфікаціях використовують «запис» замість «вузол».
- Підписи пунктів «Вузли» в меню дій рядка типу й у горизонтальному меню розділу типу стають «Записи» (спостережувана поведінка, тому ці вимоги змінюються дельтами).
- **Не змінюється**: англійські ключі перекладу, `node`/`ContentNode` у коді й БД, маршрути `/admin/content-types/{key}/nodes`, імена файлів і спеків (`admin-content-nodes`), англійська локаль, поведінка списку, форм і видалення. Зміна лише тексту.
- Архівні зміни в `openspec/changes/archive/` — історія, не редагуються.

## Capabilities

### New Capabilities

Немає.

### Modified Capabilities

- `admin-content-nodes`: вимога «Сторінка списку вузлів» перейменовується на «Сторінка списку записів»; поточний пункт меню розділу — «Записи».
- `admin-content-types`: вимоги «Меню дій рядка» та «Горизонтальне меню розділу типу» — пункт меню називається «Записи» (було «Вузли»).

Решта специфікацій (`admin-block-types`, `admin-page-types`, `content-model`, `content-querying`, `content-values` та інші вимоги перелічених вище) згадують «вузол» лише як термін у прозі, без підписів інтерфейсу. Їхні вимоги не змінюються; термін у них оновлюється редакційною правкою під час apply (див. design.md).

## Impact

- Код: `lang/uk.json` (≈12 рядків). Англійські ключі й `resources/js` не чіпаються.
- Тести: `e2e/admin-content-nodes.spec.js`, `e2e/admin-content-types.spec.js`.
- Документація, що оновлюється: `docs/README.md`, `docs/admin-content-nodes.md`, `docs/admin-content-types.md`, `docs/admin-block-types.md`, `docs/admin-page-types.md`, `docs/admin-data-table.md`, `docs/content-model.md`, `docs/content-querying.md`, `docs/content-values.md`, `docs/e2e-testing.md`. Нових файлів немає, посилання й назви файлів не змінюються.
- Специфікації: дельти в `admin-content-nodes`, `admin-content-types`; редакційна правка терміна в основних специфікаціях `admin-block-types`, `admin-page-types`, `admin-content-nodes`, `admin-content-types`, `content-model`, `content-querying`, `content-values`.
- БД, міграції, API, залежності: без змін.
