# Proposal

## Why

Редакторам потрібні посилання між сутностями CMS: вибрати тип контенту (наприклад, для блока «Список записів») і вибрати конкретний запис певного типу. Зараз для цього лишається `string`, який нічого не знає про існування цілі й не оновлює підпис.

## What Changes

- Новий тип поля `type_ref` («Посилання на тип контенту»): значення — `key` типу, у `content_values_string`. У налаштуваннях поля обирається вид типів (`settings.kind`: `content` / `page` / `block`). У формі запису — `Select` з автокомплітом і пошуком на сервері за `name` типу.
- Новий тип поля `node_ref` («Посилання на запис»): значення — `uuid` запису (нижній регістр), у `content_values_string`. У налаштуваннях поля обирається тип запису (`settings.content_type`). У формі запису — автокомпліт із пошуком на сервері за `title` запису (мова `default`).
- Валідація: значення має вказувати на наявний тип потрібного виду / наявний запис потрібного типу; блокування посилань на час запису (як у `term`).
- Налаштування поля обов'язкові, а після появи значень — незмінні (як `vocabulary` у `term`).
- Нові адмін-ендпоінти пошуку (JSON, лише для авторизованих адмінів): типи та записи за запитом `q`, ліміт на відповідь.
- У таблиці записів комірка показує `name` типу / `title` запису (а не `key`/`uuid`) як посилання на редагування цієї сутності, що відкривається в тому ж вікні; колонка сортується за цим підписом.
- Захист цілісності: тип/запис, на який посилаються значення полів, не можна видалити (як термін, що використовується).
- Нових таблиць немає (`content_values_string`); міграція не потрібна.

## Capabilities

### New Capabilities

Немає.

### Modified Capabilities

- `content-values`: типи `type_ref` і `node_ref` (значення, валідація, фільтри, захист від видалення).
- `admin-content-fields`: налаштування полів `type_ref` (вид) і `node_ref` (тип запису).
- `admin-content-nodes`: віджети автокомпліту у формі запису, ендпоінти пошуку, відображення в таблиці.

## Impact

- Код: нові `App\Content\FieldTypes\TypeRefType` і `NodeRefType`, рядки в `config/content.php`; ендпоінти пошуку (контролер + маршрути в `routes/web.php`); `PresentsFormFields`, `ContentNodeController` (підписи в комірках, початкові підписи вибраних значень); моделі `ContentNode` / `ContentType` (заборона видалення використовуваних); `resources/js/Components/Admin/ContentFields/ContentFieldForm.tsx` (налаштування), `resources/js/Components/Admin/ContentNodes/` (новий `RefAutocomplete`, `NodeFieldInput`, `types.ts`), `Pages/Admin/ContentNodes/Index.tsx`; переклади `lang/uk.json`, `lang/en.json`.
- Тести: Feature-тести обох типів, ендпоінтів пошуку та захисту від видалення; e2e-спек форми запису.
- Документація: `docs/content-values.md`, `docs/admin-content-fields.md`, `docs/admin-content-nodes.md`, `docs/e2e-testing.md`.
