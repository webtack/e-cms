## Context

Каркас тем уже є: `ThemeManager::render($candidates, $data, $hook)` (каскад «кандидат → тема», хуки `preprocess`/`suggest`, namespace Blade `theme`), публічні контролери в `app/Http/Controllers/Public/`, `PublicData` (спільні змінні), `themes/default/views/{html,layout,home,errors/404,pages/page,content/list,content/details}`. Читання значень: `ContentValueReader::load()` повертає значення типізовано (`cast`), але посилання лишаються сирими (`{uuid}.{ext}` для файлів, uuid терма й запису, ключ типу, складене значення youtube/url як масив). Публічні налаштування типу читає `PublicDisplaySettings::resolve()` (`list_fields`, `page_fields`, `sort`, `per_page`, `pagination`); меню — `MenuTree::tree($key, $lang)`; URL файла — `FileStorage::urlForValue()`. Узгоджені раніше рішення про іменування, кандидатів і хуки — у `docs/site-themes.md`; тут лише те, чого там ще немає. Мотивація — `proposal.md`.

Документи, що описують компоненти: `docs/site-themes.md` (каскад, хуки), `docs/public-site.md` (маршрути, змінні), `docs/content-values.md` (читання значень, контракт `FieldType`), `docs/menus.md` (`MenuTree`); новий `docs/theme-templates.md` описує поля, блоки, меню й повний перелік кандидатів.

## Goals / Non-Goals

**Goals:**
- Шаблон ніколи не робить запитів і не знає про формат зберігання: отримує готові дані.
- Додати новий тип поля = клас типу (з `present`) + шаблон `fields/field-{ftype}`; ядро рендера не змінюється.
- Одна точка рендера полів/блоків/меню для всіх шаблонів теми, з хуками.

**Non-Goals:**
- Мови сайту (рендер лише мовою `default`), регіони блоків, перемикання теми в адмінці, нові теми окрім `default`.
- Кешування відрендерених фрагментів.
- Зміна форматів збереження чи API читання значень для адмінки.

## Decisions

### 1. `present()` у `FieldType`, а не окремий форматувальник
`FieldType::present(mixed $value, ContentField $field, PresentContext $context): mixed` повертає готове значення для ОДНОГО елемента. `AbstractFieldType` повертає `$value` як є, тож типи без посилань (`string`, `integer`, `boolean`, `date`, `datetime`, `decimal`, `text`) нічого не перевизначають. Перевизначають `image`/`file` (URL), `richtext` (очищений HTML, ще раз через `HtmlSanitizer`), `youtube`, `url`, `term`, `node_ref`, `type_ref`. Тип знає свій формат, тож ніякого `switch` за ключем типу; нові типи з `config/content.php` підхоплюються автоматично.

*Альтернатива*: окремий реєстр форматувальників у темі. Відхилено: дублює реєстр типів, і тема не мусить знати структуру збережених значень.

### 2. Пакетне розв'язання посилань
Щоб не було запитів на кожен елемент, `FieldRenderer` перед рендером збирає значення полів сторінки/картки й розв'язує посилання пакетом (`present` отримує `PresentContext` з кешем: терми за uuid + переклади однією вибіркою, цільові записи за uuid однією вибіркою зі значенням `title`, типи за ключем). Список з N карток робить фіксовану кількість запитів, а не N. `PresentContext` живе на час одного запиту (scoped-сервіс).

*Альтернатива*: запит у `present()` на кожне значення. Відхилено: N+1 на списках.

### 3. `FieldRenderer` у `app/Theme/`, директиви як тонкі обгортки
`FieldRenderer::render(ContentField $field, mixed $raw, ContentNode $node, ContentType $type): string` будує список із восьми кандидатів (порядок із `docs/site-themes.md`), готує `$items` (завжди масив, відфільтрувавши значення, що `present` повернув як `null`), викликає `ThemeManager::render($candidates, $vars, "field.{$ftype}")`. Хук-ім'я `field.{ftype}` виконує спершу `field`. Порожній `$items` дає порожній рядок без рендера. Поля, потрібні шаблону, беруться з `$type` + `ContentField` (типи й налаштування вже завантажені; `PublicDisplaySettings` дає список ключів).

Директиви Blade реєструються в `AppServiceProvider::boot()` (поруч із наявною реєстрацією namespace `theme`): `@field($values, 'key')` (потребує в шаблоні `$node`, `$type`, `$fields`, які контролер кладе в змінні), `@fields($keys)` для групи, `@block('slug')`, `@menu('key')`. Директиви лише викликають сервіси (`FieldRenderer`, `BlockRenderer`, `MenuRenderer`) і виводять їх HTML неекранованим (його вже зібрав Blade). Вся логіка вибору кандидатів у сервісах, а не в директивах, тому вона покрита Feature-тестами без Blade.

*Альтернатива*: компонент `<x-field>`. Відхилено: потребує класу/анонімного компонента і прив'язки до шляху; директива не залежить від теми.

### 4. Блоки й меню — окремі прості сервіси
`BlockRenderer::render(string $slug)`: знаходить запис `kind = block` за slug (`ContentNode::findBySlug($slug, onlyVisible: true)`, перевіряє `contentType->kind === 'block'`), читає значення `ContentValueReader::load()`, кандидати `blocks/block-{t}--{slug-без-block-}`, `blocks/block-{t}`, `blocks/block`, хук `block`. Невидимий/відсутній → `''`. `MenuRenderer::render(string $key)`: `Menu::findByKey` + `MenuTree::tree($key, 'default')`, кандидати `menus/menu--{key}`, `menus/menu`, хук `menu`; відсутнє меню → `''`. Шаблон `menus/menu` рендерить дерево рекурсивно через `@include` анонімного вкладеного списку (`menus/item` не робимо окремим кандидатом: один шаблон на меню простіший у перевизначенні).

Дані одного запиту `layout` + меню `main` додають один запит дерева; значення блока — стандартні запити читача.

### 5. Pages/content контролер дає шаблонові поля й налаштування
`PublicNodeController` розширюється: окрім `node/type/values`, передає `fields` (ключ → `ContentField` для тих, що показуються: з `PublicDisplaySettings::resolve()['page_fields']` або `list_fields`) і готує список записів для картки (`ContentValueReader::load()` по сторінці пагінатора, а не по одному). Для списку `ContentNodeQuery::paginate()` викликається з `perPage`, `sort` з налаштувань типу (`NodeQueryParams`); `pagination = false` ховає `links()` у `content/list`. Шаблони `default` складають поля так: `@foreach ($fields as $key => $field) @field($key) @endforeach`.

Картка списку рендериться окремим викликом `ThemeManager::render` з кандидатами `{t}/preview--{id}`, `{t}/preview`, `content/preview` (хук `content.preview`) — через директиву `@preview($item)` в `content/list`.

### 6. Мета-дані документа
`meta_title`, `meta_description`, `meta_image` додаються до `PublicData::make()` змінними `$meta` (за наявності значення), а `themes/default/views/html.blade.php` виводить `<title>`, `<meta name="description">`, `og:image`. `meta_title` перебиває заголовок запису в `$title`. Це поведінка `default`; інша тема може не виводити.

### 7. Безпека виводу
Усе, окрім `richtext`, виводиться через `{{ }}` (екранується). `richtext` значення береться з бази, де його вже очистив `HtmlSanitizer` при запису, і `present()` очищає його повторно (захист від даних, записаних поза API); у шаблоні виводиться `{!! !!}` тільки для нього. URL `url`-поля, що не проходить `UrlType::isValid`, не виводиться посиланням. Embed YouTube: лише `https://www.youtube-nocookie.com/embed/{id}` з ідентифікатора, який уже відповідає `[A-Za-z0-9_-]{11}`, а не з довільного рядка.

## Risks / Trade-offs

- [Складний контракт `present` + пакетний `PresentContext`] → змушує типи з посиланнями отримувати кеш; мінімізуємо: типи без посилань не змінюються, а тести на кількість запитів (`DB::getQueryLog`) страхують від N+1.
- [Директиви Blade залежать від змінних, що кладе контролер (`$node`, `$type`)] → `FieldRenderer` не читає глобальний стан; директива без змінних кидає `ThemeException` з поясненням, а не мовчить.
- [Шаблони `default` для 15 типів — багато файлів] → спільна структура (`<div class="field field-{ftype} field--{key}">`); перевірка `theme:check` розширюється: у `default` має бути `fields/field-{ftype}` для кожного типу з реєстру (щоб новий тип без шаблона ловився командою).
- [`meta_*` змінюють `<title>`] → лише коли значення задане; інакше поведінка каркаса не змінюється.
- [Меню `main` у `layout` — припущення про ключ] → ключ `main` стає конвенцією теми `default`, описаною в `docs/theme-templates.md`; без такого меню шапка не ламається.

## Migration Plan

Міграцій БД і змін у даних немає. Крок за кроком: контракт `present` → рендерер полів і шаблони → сторінки/список/деталі → блоки → меню → `theme:check`. Відкат — звичайний `git revert`; `themes/default` без цих файлів повертається до каркаса.
