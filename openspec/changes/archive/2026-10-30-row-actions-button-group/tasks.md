# Tasks

## 1. Примітив ButtonGroup

- [x] 1.1 Додати `resources/js/Components/ui/button-group.tsx` у стилі наявних shadcn-компонентів, з JSDoc англійською та `@example`. Перевірка: `npm run build` на хості без помилок типів.

## 2. Типи контенту

- [x] 2.1 У `ContentTypeRowActions.tsx` замінити одиночний тригер на `ButtonGroup`: кнопка «Налаштування» (`Link` на `/admin/content-types/{key}/edit`) + тригер «⋮» з незмінним меню; оновити JSDoc. Перевірка: у браузері на `http://localhost:8080/admin/content-types` видно групу, меню містить «Редагувати», «Поля», «Видалити».
- [x] 2.2 Оновити `e2e/admin-content-types.spec.js`: сценарій переходу по основній кнопці «Налаштування»; наявні сценарії з «Actions» мають лишитись зеленими. Перевірка: `npm run test:e2e -- admin-content-types`.
- [x] 2.3 Оновити `docs/admin-content-types.md` (опис дій рядка й компонента, українською). Перевірка: текст відповідає новій поведінці, посилання у `docs/README.md` валідні.

## 3. Поля контенту

- [x] 3.1 У `ContentFieldRowActions.tsx` зробити те саме: кнопка «Редагувати» (`Link` на `{base}/edit`) + тригер «⋮» з незмінним меню; кнопка активна й для системного поля; оновити JSDoc. Перевірка: на сторінці полів типу видно групу, для `title` «Видалити» в меню неактивний, а кнопка «Редагувати» активна.
- [x] 3.2 Оновити `e2e/admin-content-fields.spec.js`: перехід по основній кнопці, у тому числі для `title`. Перевірка: `npm run test:e2e -- admin-content-fields`.
- [x] 3.3 Оновити `docs/admin-content-fields.md` (розділ «Меню рядка») і згадку про меню рядка в `docs/admin-data-table.md`. Перевірка: тексти відповідають поведінці, посилання валідні.

## 4. Інтеграція

- [x] 4.1 Повний прогін `npm run test:e2e` та `docker compose exec app vendor/bin/pint --dirty --format agent`. Перевірка: усе зелене; якщо e2e падають через locale `uk`, діяти за правилом з пам'яті.
