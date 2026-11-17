# Design

## Context

Див. proposal.md — Why. Зміна `add-page-types-admin` уже узагальнила `ContentTypeController`, `ContentFieldController`, form requests і Inertia-сторінки за видом: вид береться з `defaults('kind', …)` маршруту, `Route::bind('contentType')` відбирає `ofKind($route->defaults['kind'])`, контролери користуються трейтом `Concerns\ResolvesTypeSection` (`kind`, `segment`, `routeName`, `section`), форми й меню будують адреси від пропса `section.basePath`, а slug показується лише для `kind === 'content'`. Модель уже кінд-агностична: slug для `block` заборонено, батько мусить мати той самий вид, системне поле `title` додається для всіх видів. Залишилось те, що явно знає лише про `content`/`page`: цикл реєстрації маршрутів у `routes/web.php`, тернарний вираз у `ResolvesTypeSection::segment()`, тип `TypeSection['kind']` і словник `labels` у `section.ts`, `navigation.ts`. Описано в `docs/admin-page-types.md`, `docs/admin-content-types.md`, `docs/admin-content-fields.md`, `docs/admin-shell.md`, `docs/content-model.md`.

## Goals / Non-Goals

**Goals:**
- Сторінки `block` повністю повторюють функціональність `page` без нових контролерів і React-сторінок.
- Адреси `/admin/content-types/**` і `/admin/page-types/**` для типів `block` лишаються 404.

**Non-Goals:**
- Вузли блоків (створення, префікс slug `block-`, розміщення блоків на сторінках) — окремі зміни.
- Зміни моделі, міграцій чи конфігурації видів.

## Decisions

- **Ще один запис у циклі маршрутів.** До масиву `['content-types' => 'content', 'page-types' => 'page']` додається `'block-types' => 'block'`; імена `admin.block-types.*`. Альтернатива — окремі контролери: відкинуто з тієї ж причини, що й для `page`.
- **Сегмент розділу з відображення, а не з тернарного виразу.** `ResolvesTypeSection::segment()` зараз розрізняє лише `page`/`content`; його замінює відображення `['content' => 'content-types', 'page' => 'page-types', 'block' => 'block-types']`, щоб додавання виду не ховалось у `else`-гілці (інакше `block` мовчки отримав би `content-types`). Коментарі «`content` or `page`» у PHPDoc трейта й контролерів оновлюються.
- **Фронтенд.** `TypeSection['kind']` розширюється до `'content' | 'page' | 'block'`, у `labels` з'являється запис `block` («Block Types», «Create block type», «Edit block type», «Delete block type», підтвердження видалення, «Block type sections»). Компоненти, що перевіряють `kind === 'content'` для slug, не змінюються (для `block` slug так само прихований); перевіряється, що ніде немає перевірки `kind === 'page'` замість `!== 'content'`.
- **Навігація.** У `navigation.ts` пункт `{ title: 'Block Types', href: '/admin/block-types', icon: Blocks }` стоїть після Page Types; `extraTitles` отримує `/admin/block-types/create`, `patternTitles` — `…/block-types/[^/]+/edit`, а шаблони полів узагальнюються до `(content|page|block)-types`.
- **Переклади.** Нові ключі в `lang/en.json` і `lang/uk.json`, у стилі ключів для `page`.
- **Документація.** Новий `docs/admin-block-types.md` (коротко, з посиланням на механізм виду в `docs/admin-page-types.md`); оновлюються `docs/admin-content-types.md`, `docs/admin-content-fields.md`, `docs/admin-shell.md`, `docs/e2e-testing.md`, `docs/README.md`.

## Risks / Trade-offs

- [Регресія для `content`/`page` після зміни `segment()`] → наявні Feature-тести й e2e `content-types`, `content-fields`, `page-types` лишаються без змін і мусять проходити.
- [Пропущений жорстко закодований вид у фронтенді] → `grep -rn "'page'\|\"page\"" resources/js` у завданні; e2e проходить розділ блоків.
- [Зміна `navigation.ts` і sidebar — спільний код] → згідно з правилами проєкту запускається повний `npm run test:e2e`.
