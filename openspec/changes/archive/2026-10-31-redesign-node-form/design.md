# Design

## Context

- The form is `ContentNodeForm.tsx` (shared by `Create`/`Edit`), one `useForm` with `slug`, `values`, `file_meta`; the slug is now the first fieldset in `max-w-5xl`. The controller is `App\Http\Controllers\Admin\ContentNodeController`, the validation is `StoreContentNodeRequest`/`UpdateContentNodeRequest`, the creation is `ContentNodeCreator`.
- `content_nodes` has only `uuid`, `content_type`, `slug`, `timestamps`. The application timezone is `UTC`; the form already passes the values of `datetime` fields in the format `Y-m-d\TH:i` without converting time zones (`docs/admin-content-nodes.md`). The same approach for `published_at`.
- `ContentNodeQuery` serves both the admin list and (potentially) a public selection; the admin must see all records. `ContentNode::findBySlug()` is the public search by slug.
- There is `ui/sheet.tsx` and the hook `useIsMobile` (a breakpoint of 768 px, not `lg`).
- Field keys: the format `^[a-z][a-z0-9_]*$` (`ContentType::KEY_PATTERN`), validated in `StoreContentFieldRequest` and `ContentField::assertValid()`; there are no reserved keys, and `ContentNodeController::RESERVED_KEYS = ['uuid', 'slug']` only hides such fields from the columns.
- Documentation: `docs/admin-content-nodes.md`, `docs/admin-content-fields.md`, `docs/content-model.md`, `docs/content-querying.md`, `docs/e2e-testing.md`.

## Goals / Non-Goals

**Goals:**
- A two-row layout 70/30 with an expandable service column; on `< lg` — a Sheet opened by a button in the sticky panel.
- The service attributes `published_at`, `is_active`, `user_id` at the level of the DB, the model, the form and the table.
- The visibility rule for a public consumer in one place (`ContentNodeQuery`/the model).

**Non-Goals:**
- The public front-end that consumes the visibility (only a ready filter).
- Editing `created_at`, changing the author, a log of changes.
- Changes for the kinds `page`/`block` in the admin (there are no record routes there; the attributes in the model are shared).
- Translation of the service attributes (they do not depend on the language).

## Decisions

**1. Two axes of visibility: `published_at` (when) + `is_active` (whether it is allowed).** Visible ⇔ `is_active AND published_at <= now()`. Disabling does not erase the date. *Alternative*: a single `published_at` with NULL for a draft — erases the date when disabled and does not distinguish "draft" from "not yet time". The state («Опубліковано/Заплановано/Вимкнено») is derived and is not stored; it is computed in one place on the server for the table and in one function on the client for the badge of the form (the form needs the badge by the state of the form before saving). Disabled has priority over the date.

**2. `published_at` NOT NULL.** The form puts the current moment in create, the server substitutes `now()` if the field is empty/not sent at creation; on update an empty value is a validation error (`required`). Existing records: `published_at = created_at`. This removes `NULL` from the visibility condition. The format is `Y-m-d\TH:i` (the same convention as the datetime fields), UTC without conversions; a deferred publication works with the precision of a minute.

**3. `user_id` NOT NULL, FK `restrictOnDelete`.** Migration: adds a nullable column, backfills it, then makes it NOT NULL (so that it works in MySQL and SQLite). The backfill takes the administrator by `env('ADMIN_EMAIL', non-production ? 'admin@example.com' : null)` (as `DatabaseSeeder`); for an empty table the backfill is skipped; for a non-empty one without an administrator — an exception with a clear text (the migration does not leave a partially changed table). *Alternative* `nullOnDelete` + `NULL` — rejected by the requirement "never NULL".

**4. The author is set by the server.** `user_id` is NOT in `$fillable` from HTTP and not in the request rules; `ContentNodeCreator::create()` receives the parameter `?int $authorId = null` and, when it is `null`, takes `auth()->id()`; if there is no author at all — a `LogicException`. The update controller does not touch `user_id`. *Consequence*: tests and the factory that create records without a user must pass the author or `actingAs`; `ContentNodeFactory` adds `user_id => User::factory()` and `published_at => now()`.

**5. `updated_at` is updated at every save.** The values of the fields lie in other tables, so their saving alone does not change the row `content_nodes`. The `update()` of the controller calls `$node->touch()` at the end of the transaction (after writing the values and the metadata of the files). *Alternative* — listeners on the value tables: more complicated and affects the import.

**6. Visibility is an option of the selection, disabled by default.** `NodeQueryParams::$onlyVisible` (default `false`) → `ContentNodeQuery` adds `where is_active = 1 and published_at <= now()` to the query and to the count (one condition, through the scope `ContentNode::scopeVisible()`); `ContentNode::findBySlug($slug, onlyVisible: false)`. The admin passes nothing and sees everything. *Alternative* — a global scope: it breaks the admin and the tests.

**7. The table of records.** Two server columns are added after the slug: `status` (a badge, `sortable: false`) and `published_at` (type `date`, `sortable: true`, the value `Y-m-d H:i`). Sorting by `published_at` — `orderBy('content_nodes.published_at')` with the same secondary order by `id`; `tableColumns()` allows this, `sortable()` for the fields of a type does not change. The state is computed in `row()` from `is_active` and `published_at`. The columns have no visibility switch (unlike the fields of a type). The keys of the service columns are ordinary, like `slug` and `uuid`, without a prefix; a conflict with the key of a field of a type is excluded by **reserving the keys** (decision 7a).

**7a. Reserved keys of fields.** One list `ContentField::RESERVED_KEYS = ['uuid', 'slug', 'status', 'published_at']` (the keys of the service columns of the table of records) — the single source for three places: (1) `StoreContentFieldRequest` (the rule `not_in`, the message "the key is reserved" next to the key field); (2) `ContentField::assertValid()` — the last barrier for other write paths, only when `! $this->exists` (creation), so existing fields with such keys (earlier `slug`/`uuid` were not forbidden) do not break on update; (3) `ContentNodeController::listSettings()` instead of its own `RESERVED_KEYS` — they are still not offered as columns (the behaviour of the existing fields does not change). The field `title` is not reserved (it is a system field, the column `title` shows it). *Alternative* — a prefix `_` in the keys of the columns: nothing forbids it, but it leaves `?sort=_published_at` in the address and diverges from how `slug` is already done. Consequence for the future: a new service attribute that becomes a column of the table adds its key to this list; for the existing types the fields with such a key remain valid, but are not shown as a column.

**8. The layout of the form.** The root is a `<form>` with `grid lg:grid-cols-[minmax(0,7fr)_minmax(0,3fr)] gap-6`; the left column — the fields of the type; the right — an `aside` with `lg:sticky lg:top-4 self-start`. The service fields are moved into a separate component `NodeServicePanel` (the content of the block), which receives `data`/`setData`/`errors`/`meta` as props and is **rendered once**: in the `aside` on `lg`, in a `Sheet` on a smaller screen. The choice of the container is by the hook `useMediaQuery('(min-width: 1024px)')` (a generalization of `useIsMobile`; the breakpoint `lg` does not coincide with the existing 768). Until the first calculation (SSR/the first render) the mobile variant without the content of the Sheet is shown, so that there are no duplicate ids (`#slug`). The state lives in `useForm`, so closing the Sheet does not lose the values (Radix unmounts the content). The containers of the fields of the type (`fieldset` in the left column) have `min-w-0`: a `fieldset` by default has `min-inline-size: min-content`, and unbreakable content (the `truncate` title of the YouTube card, long URLs) would push the left column and go under the right one. On the desktop the block is not a `fieldset` with a legend for each attribute — one block with the heading «Деталі» and rows «label — element» (the column is extended gradually by adding rows).

**9. A shared sticky action bar of the forms.** The component `FormActionBar` (`resources/js/Components/Admin/`) — a sticky `div` at the bottom (`sticky bottom-0 z-10`, background, a top border, `pb-[max(0.75rem,env(safe-area-inset-bottom))]`), aligned to the edges of `<main>` (`-mx-4 md:-mx-6`, because in the shell it is `p-4 md:p-6`). It contains «Скасувати» (`variant=ghost`, `Link`) and «Зберегти» (`type=submit`, `disabled={processing}`), «Скасувати» stands before «Зберегти»; the slot `children` for additional buttons on the left. All forms of the admin use it: `ContentNodeForm`, `ContentFieldForm`, `ContentTypeForm`. The bar is the last child of the `<form>` **outside** the grid of columns (a `grid` element cannot stick outside its row), so on `lg` the `aside` remains `sticky top-4`, and the bar does not depend on the columns. In the forms of the type and of the field `max-w-xl` is on an inner `div`, not on the `<form>`, so that the bar is full width. On `< lg` the record form passes into the slot a button-icon of the details (without visible text and without the status badge, because on small screens the text does not fit; the accessible name «Деталі» through `aria-label` and `title`; the error dot remains; on `lg` there is no button, because the column is visible). In the forms of the type and of the field the inner `div` with `max-w-xl` gets `rounded-md border` (a frame around the fields, the action bar remains full width outside the frame). Between the content of the form and the action bar there is a small indent (`mb-4` on the content, on top of the existing `pb-24`/scrolling), so that the form does not stick to the bar. The indicator of errors — `errors.slug || errors.published_at || errors.is_active`. The container of opening — a `Sheet` `side="bottom"`, with the button «Готово».

**10. The status badge.** The badge is shown in the table of records and in the service column/panel, but not on the button-icon in the action bar. The pure function `nodeStatus(isActive, publishedAt, now)` in `Components/Admin/ContentNodes/status.ts` (the server has an equivalent in the controller); `now` is taken from the browser, the comparison of the strings `Y-m-d\TH:i` in UTC, because the format is lexicographically ordered.

**11. Inertia props.** `create`: `node = { slug: '', is_active: true, published_at: <now UTC>, updated_at: null, author: { name } }`. `edit`: `node = { uuid, slug, is_active, published_at, updated_at, author: { name } | null }`. `forForm()` carries the errors of `is_active`/`published_at` without the prefix `values.` (as `slug`).

**12. Translations.** New rows (`Details` (the existing key of the media library, so its translation «Деталі» is shared by both places), `Show on site`, `Publication date`, `Updated`, `Author`, `Published`, `Scheduled`, `Disabled`, `Status`) — in `lang/uk.json` and `lang/en.json` synchronously (`TranslationParityTest`).

## Risks / Trade-offs

- [The NOT NULL migration on production without `ADMIN_EMAIL`] → an explicit exception with a text; the migration runs in a transaction where the driver supports it, and does not leave partially applied changes.
- [A duplicate `id="slug"` and loss of focus when the window is resized, because the container switches] → one render by the hook; the e2e checks the absence of duplicates and the persistence of the values.
- [The Sheet unmounts the content: validation errors are not visible] → an error indicator on the button and messages in the panel on opening; the errors live in the Inertia state, not in the DOM.
- [`published_at` in UTC, and the administrator is in another time zone] → the form shows what was entered, without conversions (as the datetime fields); a separate timezone of the user is beyond the limits of this change. This is a visible compromise, not a solved task.
- [Binding the tests to the factory/Creator] → we update all ~20 test files that create records; the new default values in the factory minimize the edits.
- [A field of a type with the key `status`/`published_at`/`slug`/`uuid` already exists] → such new fields cannot be created (decision 7a); the existing ones remain valid, but are not shown as a column of the table and may be invisible to the administrator; before the migration check the database with a query by `content_fields.key`.
- [Unbreakable content in a `fieldset` pushes the left column and overlaps the service one] → `min-w-0` on the container of the field; the e2e checks that the YouTube card does not go beyond the left column.
- [The sticky bottom bar overlaps the end of the form] → a bottom indent (`pb-24`) on all forms with the bar.

## Migration Plan

1. Migration: add the columns (nullable), backfill `published_at = created_at`, `user_id` by the administrator, then NOT NULL + FK + the index `(content_type, published_at)`.
2. Deployment: make sure `ADMIN_EMAIL` is set (in production it is mandatory), `php artisan migrate`.
3. Rollback: `down()` removes the FK and the three columns; the data of the service attributes is lost.

## Open Questions

- Should the service columns of the table of records (`Status`, `Published`) be shown always, or add a switch in the settings of the list of the type? Now — always; the switch can be added without changes in the specifications.
