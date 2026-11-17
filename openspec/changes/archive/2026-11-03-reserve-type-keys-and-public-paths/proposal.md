# Proposal

## Why

Скоро з'явиться публічний фронтенд із темами (`themes/<name>/views`) і маршрутами `/{slug}` (сторінка), `/{type.slug}` (список типу `content`), `/{type.slug}/{node.slug}` (деталі). Зараз дані цього не захищають:

- ключ типу стане назвою папки шаблонів у `views/`, але ключ `content`, `pages`, `blocks`, `fields`, `menus` чи `errors` збігся б зі службовою папкою теми (заборона є лише для ключів **полів**, `ContentField::RESERVED_KEYS`);
- slug сторінки, slug типу та службові шляхи (`admin`, `login`, `storage` …) ділять один простір першого сегмента URL, а перевіряються лише на унікальність у своїй таблиці. Сторінка `news` і тип зі slug `news` обидва хочуть `/news`, а сторінка `admin` ховається за службовим маршрутом. Автогенерація теж може дати `admin` із заголовка «Admin».

Помилку треба показувати в формі при збереженні, поки коректні шляхи ще можна виправити, і до того, як з'явиться публічна маршрутизація.

## What Changes

- Ключ нового типу не може бути зі списку зарезервованих (`content`, `pages`, `blocks`, `fields`, `menus`, `errors`). Єдине місце переліку — константа в `ContentType`. Перевіряється при створенні (ключ незмінний), у моделі й у `StoreContentTypeRequest` (помилка біля поля ключа). Наявні типи не чіпаємо.
- Slug **сторінки** (запис виду `page`) не може збігатися зі slug будь-якого типу `content` або зі зарезервованим шляхом сайту (`config('content.reserved_paths')`: `admin`, `login`, `logout`, `locale`, `email`, `up`, `storage`, `build`).
- Slug **типу `content`** не може збігатися зі slug будь-якої сторінки або зі зарезервованим шляхом.
- Автогенерація slug сторінки (`SlugGenerator`) вважає такі значення зайнятими й додає числовий суфікс (`admin` → `admin-2`).
- Перевірка діє на двох рівнях: правила форм (помилка біля поля `slug`) і модель (остання межа для коду та імпорту). Перевіряється лише при створенні та зміні slug, тож наявний збіг не блокує інші правки запису чи типу.
- Тест-страж: перший сегмент кожного не публічного маршруту застосунку входить до `content.reserved_paths`, тож новий службовий маршрут не лишиться без захисту.
- Мовні префікси (`en`, `uk`) **не** входять сюди: їх додасть окрема зміна про мови сайту.
- Slug записів `content` і `block` не перевіряється: вони не стоять на першому сегменті URL.
- **BREAKING** (лише для коду й тестів): створити тип із зарезервованим ключем більше не можна. Тести й фікстури, що використовують ключ `pages` та подібні, переходять на нейтральні ключі.

## Capabilities

### New Capabilities

Немає.

### Modified Capabilities

- `content-model`: ключі типів (зарезервовані); публічний slug типу (перестає бути незалежним від slug сторінок і зарезервованих шляхів); slug запису (нове правило для сторінок і правило генерації без збігів).
- `admin-content-types`: форма створення й редагування типу показує помилки зарезервованого ключа та колізії slug біля відповідних полів.
- `admin-content-nodes`: форма запису сторінки показує помилку колізії slug біля поля slug (створення й редагування).

## Impact

- Код: `app/Models/ContentType.php`, `app/Models/ContentNode.php`, новий сервіс перевірки публічних шляхів у `app/Content/` (поряд зі `SlugGenerator`), `app/Content/SlugGenerator.php`, `app/Content/ContentNodeCreator.php` (повідомлення помилки), `app/Http/Requests/Admin/StoreContentTypeRequest.php`, `StoreContentNodeRequest.php`, `UpdateContentNodeRequest.php`, `config/content.php`, `lang/en.json`, `lang/uk.json`.
- Тести: `tests/Feature/Content` (`ContentTypeTest`, `ContentNodeTest`, `SlugTest`, новий тест колізій і страж маршрутів), `tests/Feature/Admin` (`ContentTypeCreateTest`, `ContentTypeEditTest`, `PageTypeTest`, `ContentNodeWriteTest`), заміна зарезервованих ключів у фікстурах.
- e2e: перевірка помилок у формах у `e2e/admin-content-types.spec.js` і `e2e/admin-page-types.spec.js`.
- Бази даних і міграцій немає: це лише валідація. У робочій БД колізій наразі немає (типи `ddd`, `dsfsdf`, `news`; slug типу `news`; сторінка `rtrtyrty`).
- Docs: оновити `docs/content-model.md` (ключі типів, slug типу й запису, зарезервовані шляхи), `docs/admin-content-types.md`, `docs/admin-content-nodes.md`, `docs/admin-page-types.md` (де перелічено відмінності й помилки slug) і, за потреби, `docs/e2e-testing.md`.
