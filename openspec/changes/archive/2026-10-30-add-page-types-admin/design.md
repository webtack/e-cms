# Design

## Context

Див. proposal.md — Why. Сьогодні `ContentTypeController`, `ContentFieldController`, form requests і Inertia-сторінки жорстко прив'язані до виду `content`: `Route::bind('contentType', …)` у `routes/web.php` відбирає лише `ofKind('content')`, `store()` пише `'kind' => 'content'`, `parentOptions()` і правило `parent_type` фільтрують за `content`, а фронтенд має шляхи `/admin/content-types/...` у `ContentTypeForm`, `ContentTypeRowActions`, `ContentTypeNav`, `ContentFieldForm`, `ContentFieldRowActions` та сторінках полів. Модель вже кінд-агностична: slug заборонено для `page`, батько мусить мати той самий вид (`ContentType::assertValid()`), системне поле `title` додається для всіх видів. Описано в `docs/content-model.md`, `docs/admin-content-types.md`, `docs/admin-content-fields.md`, `docs/admin-shell.md`.

## Goals / Non-Goals

**Goals:**
- Сторінки `page` повністю повторюють функціональність `content` без копіювання контролерів і React-сторінок.
- Старі адреси `/admin/content-types/**` поводяться як раніше (404 для `page`/`block`).

**Non-Goals:**
- Типи виду `block` та вузли (сторінки-вузли, дерево сторінок, маршрутизація) — окремі зміни; макетний пункт «Pages» не чіпаємо.
- Зміни моделі, міграцій чи конфігурації видів.

## Decisions

- **Вид задає маршрут.** Другий набір маршрутів `/admin/page-types/**` реєструється з тими самими контролерами та `->defaults('kind', 'page')` (для `content` — `'content'`); реєстрація обох наборів робиться одним циклом/групою, щоб вони не розходилися. `Route::bind('contentType')` бере вид із `$route->defaults['kind']` і відбирає `ofKind($kind)`. Альтернатива — окремі `PageTypeController`/`PageTypeFieldController`: відкинуто, бо це ~300 рядків дубльованої логіки (видалення, порядок, `valuedKeys`), яка б розходилась.
- **Вид у контролерах.** Контролери читають вид із маршруту (`$request->route('kind')`) і використовують його у `index()` (`ofKind`), `store()` (`'kind' => $kind`) та `parentOptions()`. Імена маршрутів: `admin.page-types.*`, `admin.page-types.fields.*`; редіректи після дій будуються від імені поточного маршруту (префікс `content-types`/`page-types`), а не жорстким `to_route('admin.content-types.index')`.
- **Валідація.** `StoreContentTypeRequest::sharedRules()` отримує вид: правило `parent_type` фільтрує `where('kind', $kind)`, а `slug` входить у правила лише для `content` (для `page` ключ `slug` не в правилах, тож відкидається з `validated()`). `UpdateContentTypeRequest` бере вид із типу маршруту.
- **Фронтенд — один набір сторінок.** Контролер передає в Inertia-пропси `section`: `{ kind, basePath, titles… }` або мінімум `basePath` (`/admin/content-types` чи `/admin/page-types`) і `kind`. `ContentTypeForm`, `ContentTypeRowActions`, `ContentTypeNav`, `ContentFieldForm`, `ContentFieldRowActions` та сторінки `Pages/Admin/ContentTypes/*`, `ContentFields/*` замість жорсткого шляху використовують `basePath`; slug-поле й колонка slug показуються лише для `kind === 'content'`. Заголовки/діалоги («Page Types», «Delete page type») беруться з перекладів за видом. Альтернатива — окремі сторінки `Pages/Admin/PageTypes/*`: відкинуто через дублювання.
- **Навігація.** У `navigation.ts` додається пункт `{ title: 'Page Types', href: '/admin/page-types', icon: FileStack }` після Content Types, у `extraTitles`/`patternTitles` — записи для `/admin/page-types/create`, `/{key}/edit`, `/fields…` (заголовки «Create page type», «Edit page type»). Хлібні крихти спільні для обох розділів: шаблони узагальнюються до `(content|page)-types`.
- **Адреса розділу.** `/admin/page-types` (ключ у URL, як у решти сутностей); імена `page-types`, щоб не плутати з майбутнім `/admin/pages` для вузлів.

## Risks / Trade-offs

- [Регресія для `content` після узагальнення] → наявні Feature-тести й e2e `content-types`/`content-fields` лишаються без змін і мусять проходити; додається перевірка, що `/admin/content-types/**` для `page` — 404.
- [Вид береться з `route defaults`, неявна залежність] → один допоміжний метод у контролері й коментар; Feature-тест на кожен вид.
- [Жорсткі шляхи, які можна пропустити у фронтенді] → пошук `grep "/admin/content-types"` по `resources/js` у завданнях; e2e проходить обидва розділи.
- [Спільні переклади «Content type» у діалогах] → окремі ключі для `page`, `lang/*.json` перевіряються тестом/збіркою.
