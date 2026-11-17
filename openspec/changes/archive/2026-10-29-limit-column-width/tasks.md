# Tasks

## 1. Властивість колонки

- [x] 1.1 Додати до `Column` у `Components/Admin/DataTable/types.ts` необов'язкове `width?: string` (максимальна ширина комірок) з JSDoc англійською та `@example`
- [x] 1.2 У `DataTable.tsx` задати `style={{ maxWidth: width }}` на `TableHead` колонки з `width`; у `DataTableRow.tsx` для такої колонки обгортати вміст комірки в `<div className="truncate" style={{ maxWidth: width }}>`; без `width` розмітка не змінюється; `npm run build` проходить
- [x] 1.3 Додати `width` до однієї колонки демо-таблиці (`DataTableDemo`) з довгим значенням у даних, щоб механізм було видно й можна було перевірити в e2e
- [x] 1.4 Оновити розділ «Опис колонок» у `docs/admin-data-table.md` (`width`: призначення, `max-width` на `th`, обгортка з обрізанням, приклад); перевірити посилання

## 2. Колонка назви типів контенту

- [x] 2.1 У `Pages/Admin/ContentTypes/Index.tsx` задати колонці `name` `width` (наприклад `24rem`), поставити `truncate` на назву та опис замість `line-clamp-2`, зберегти `title` з повним описом
- [x] 2.2 Оновити опис колонки `name` у `docs/admin-content-types.md` (обмежена ширина, один рядок, трикрапка)

## 3. Перевірка

- [x] 3.1 Доповнити e2e `admin-data-table` (`th` має `max-width`, довге значення обрізається, комірка не ширша за межу, колонка без межі не змінилась) і `admin-content-types` (довгий опис в один рядок, решта колонок на місці, вузький екран 375px без горизонтальної прокрутки); `npm run test:e2e -- admin-data-table admin-content-types` проходить
- [x] 3.2 Повний `npm run test:e2e` і `docker compose exec app php artisan test` проходять; `openspec validate limit-column-width` без помилок
