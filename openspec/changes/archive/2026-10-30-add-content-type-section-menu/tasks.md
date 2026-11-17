# Tasks

## 1. Компонент меню

- [x] 1.1 Створити `ContentTypeNav` (пункти «Налаштування»/«Поля», `aria-current` на активному, посилання назад до списку типів) з JSDoc англійською та `@example`; перевірка: `npm run build` без помилок типів
- [x] 1.2 Додати рядок «Settings» у `lang/uk.json` та `lang/en.json` (якщо відсутній); перевірка: перемикання мови показує переклад меню

## 2. Підключення до сторінок

- [x] 2.1 Використати `ContentTypeNav` у `ContentTypes/Edit.tsx` (active=settings) та `ContentFields/Index.tsx` (active=fields, прибрати власне посилання «Типи контенту»); перевірка: сторінки відкриваються в браузері з меню
- [x] 2.2 Використати `ContentTypeNav` у `ContentFields/Create.tsx` і `Edit.tsx` (active=fields); перевірка: меню видно, «Поля» виділено; `ContentTypes/Create.tsx` без змін
- [x] 2.3 Додати e2e у `e2e/admin-content-types.spec.js` і `e2e/admin-content-fields.spec.js` (меню на всіх чотирьох сторінках, `aria-current`, перемикання, відсутність меню на створенні типу); перевірка: `npm run test:e2e` проходить
- [x] 2.4 Оновити `docs/admin-content-types.md` і `docs/admin-content-fields.md` описом меню та компонента; перевірка: посилання в `docs/README.md` валідні
