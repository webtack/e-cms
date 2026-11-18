## Context

See proposal.md — Why. The current state:

- `ContentTypeNav` — a menu of three links (`Налаштування` "Settings", `Поля` "Fields", `Записи` "Records"), each is a separate Inertia page with its own route.
- `Pages/Admin/ContentTypes/Edit.tsx` renders `ContentTypeForm` and `TypeValuesPanels` (one form for both panels «Розділ» "Section" and «SEO», saving `PUT /content-types/{key}/values`). `ContentTypeController::edit` gives the props `typeFields`, `typeValues`, `typeFiles` through `typeValuesProps()`.
- `ContentTypeValuesController::update` calls `ContentValueWriter::write`, which writes only the keys present in the request (`array_key_exists`), so a partial saving does not erase the other fields.
- `ContentNodeForm` makes a 7/3 grid: on the left the main fields and `NodeFieldsPanel` «SEO», on the right (only from `lg`) `NodeServicePanel`; below `lg` the same `panel` is rendered once in a `Sheet` (the ids of the fields are fixed, so duplicating is not allowed).
- There is no accordion component in `Components/ui`; the package `radix-ui` (^1.8.1) already exists.

## Goals / Non-Goals

**Goals:**
- Split «Розділ» and «SEO» of a type into their own tabs of the menu of the type.
- Move «SEO» of a record to the right column as an accordion section, kept in the sheet on mobile.

**Non-Goals:**
- Changing the data model, the system fields, the validation, the API of saving the values of a record.
- Tabs for `page` and `block` (they have no values of the type itself).
- An accordion for other blocks of the form.

## Decisions

1. **The tabs of the type are separate pages, not client tabs.** We add two GET routes only for `content-types`: `/{contentType}/section` and `/{contentType}/seo` (`ContentTypeController::section()` / `seo()`, names `content-types.section` / `content-types.seo`, `kind=content`; other kinds → 404, as in `values`). `ContentTypeNav` gets the items `section` and `seo` only when `section.kind === 'content'`. Reason: the menu is already navigational (`<Link>`, `aria-current`), deep links and the history work without new state. Alternative — Radix Tabs on the settings page: one form remains, but it mixes navigational and client tabs in one menu; rejected.
2. **One panel — one form.** `TypeValuesPanels` becomes the form of one panel (the prop `panel: 'section' | 'seo'`), the state contains only its fields, `put` sends `values` only of these keys to the existing `PUT /content-types/{key}/values`. The backend endpoint does not change (a partial saving is safe, see Context). `typeValuesProps()` accepts the panel and filters the fields; `edit` no longer gives these props. `NodeFieldsPanel` remains for the markup of a field in a panel (the heading of the tab may coincide with the heading of the panel).
3. **The accordion `Components/ui/accordion.tsx`** on `radix-ui` (`Accordion`), in the style of the rest of `ui`. The type `single` with `collapsible`, a controlled state `value: string` (`''` — everything is closed): initially `'details'`; when an error appears in the fields `meta_*` (`errors['values.meta_*']`) the value becomes `'seo'` (opening one section closes the other). Each `AccordionItem` has its own frame (`rounded-md border px-3`), with a gap between the elements (`space-y-2` on `Accordion`); the shared frame of the column (`rounded-md border p-3`) remains on `Accordion`, so the frames of the sections are nested in it. The controlled state lives in `ContentNodeForm`, so it survives the closing of the sheet (the sheet renders the content only when open, but the state lies above).
4. **A single render of the accordion.** As `NodeServicePanel`, the accordion (together with the fields «SEO») is rendered once: in the `aside` on desktop or in a `Sheet` below `lg`. The SEO fields have ids, so duplicates are not allowed. `NodeServicePanel` becomes the content of the section «Деталі» ("Details") (its own `section` and the heading «Деталі» move into `AccordionTrigger`; the accessible name «Деталі» is kept). The section «SEO» renders `NodeFieldFrame` of the fields `meta_*`; `NodeFieldsPanel` is not used for a record. The section `seo` is absent if the type has no `meta_*`.
5. **Errors.** `hasServiceError` is extended with the errors `values.meta_*`, so that the indicator on the button of the sheet shows them too. The focus on the first erroneous field of the main column does not change.
6. **`data-testid`.** We keep `seo-panel` for the container of the section SEO of a record and for the form of the tab of the type, to minimally change the e2e.
7. **Hiding the fields of the panels.** A «field of a panel» is a field for which `SystemFields::panel($key) !== null` (`meta_*`, `section_*`); `title` is not such. `ContentFieldController::index` excludes them from `fields`; `edit`, `update`, `destroy` respond with 404 (`abort_if`, the check in one private method). `reorder` and `ReorderContentFieldsRequest` work with the shown fields: «exactly all shown fields», the weights are rewritten only for them. The weights of the hidden fields are not touched: they create no collisions with the shown ones, because the hidden fields are not among the main fields of the form of a record (they are in the panels/sections), and the other sortings by weight do not touch them. Alternative — a mark «Поле типу» ("Field of the type") in the table: rejected, because the user has nothing to do with them. The data and `ContentNodeController::listSettings()` (the columns by `meta_title`) do not change. A side effect: the description, the weight and the name of these fields are no longer edited through the UI (the desired behaviour).
8. **The sticky action bar (`FormActionBar`) is kept.** It is `sticky bottom-0` and must be the last child of `<form>`; the stickiness is broken by ancestors with `overflow` (hidden/auto/scroll) between it and the scrolling of `<main>`. Therefore: (a) the forms of the tabs «Розділ»/«SEO» instead of their own button «Update values» use `FormActionBar` (`cancelHref` = the list of types `section.basePath`), not a separate block with an indent; (b) in `ContentNodeForm` the bar remains the last child of the form outside the grid and outside the accordion; the accordion and the `Sheet` do not wrap the form; (c) the right `aside` has its own vertical scrolling (`max-h` according to the height of the window minus the indent and the height of the action bar + `overflow-y-auto`), so that with a large number of elements (the expanded «Деталі» and «SEO») it does not go beyond the window; the scrolling is only on the `aside`/the container of the accordion, not on the ancestors of the action bar; (d) an `aside` with `lg:sticky` must not overlap with the bar (an indent/`max-h` taking its height into account, `z-10` of the bar). The button of the details in the left part of the panel on narrow screens remains.
   The existing e2e «form action bar» (`admin-content-types`, `admin-content-nodes`, `admin-content-fields`) must remain green; for the tabs of the type the same test is added.

Documentation: `docs/admin-content-types.md` (the tabs «Розділ»/«SEO», the routes, the props), `docs/admin-content-fields.md` (the hidden fields of the panels), `docs/admin-content-nodes.md` (the accordion in the right column and the sheet), `docs/e2e-testing.md` (only if the correspondence of the specs changes).

## Risks / Trade-offs

- [The weights of the hidden fields coincide with the weights of the shown ones after the rewrite] → safe, because the order in the form of a record is defined only by the shown fields; do not «align» the weights of the hidden ones.
- [The accordion/tabs break the stickiness of the action bar] → point 8 of the Decisions; it is checked by e2e at 1280 and 390 px with a height of 400 px.
- [Bookmarks/links to `.../edit` with the expectation of the panels] → there are no panels on `edit` any more; there are no internal links to them, backward compatibility is not needed.
- [A collapsed SEO hides an error] → the controlled accordion opens «SEO» on an error (and closes «Деталі»); an indicator on the button of the sheet.
- [A field with `autoFocus` or rich text in a collapsed section] → Radix renders the closed content unmounted; the state of the values lies in `useForm`, so the values are not lost; check e2e for `meta_image`.
- [The height of the right column with `sticky`] → an open section (one) may be taller than the window; the column must scroll (`max-h` + `overflow-y-auto`) — check visually.
