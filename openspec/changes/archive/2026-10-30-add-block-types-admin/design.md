# Design

## Context

See proposal.md — Why. The change `add-page-types-admin` has already generalized `ContentTypeController`, `ContentFieldController`, the form requests and the Inertia pages by kind: the kind is taken from `defaults('kind', …)` of the route, `Route::bind('contentType')` selects `ofKind($route->defaults['kind'])`, the controllers use the trait `Concerns\ResolvesTypeSection` (`kind`, `segment`, `routeName`, `section`), the forms and the menu build addresses from the prop `section.basePath`, and the slug is shown only for `kind === 'content'`. The model is already kind-agnostic: the slug for `block` is forbidden, the parent must have the same kind, the system field `title` is added for all kinds. What remains is what explicitly knows only about `content`/`page`: the loop of route registration in `routes/web.php`, the ternary expression in `ResolvesTypeSection::segment()`, the type `TypeSection['kind']` and the dictionary `labels` in `section.ts`, `navigation.ts`. Described in `docs/admin-page-types.md`, `docs/admin-content-types.md`, `docs/admin-content-fields.md`, `docs/admin-shell.md`, `docs/content-model.md`.

## Goals / Non-Goals

**Goals:**
- The `block` pages fully repeat the functionality of `page` without new controllers and React pages.
- The addresses `/admin/content-types/**` and `/admin/page-types/**` for types `block` remain 404.

**Non-Goals:**
- Block nodes (creation, the slug prefix `block-`, placement of blocks on pages) — separate changes.
- Changes to the model, migrations or the configuration of kinds.

## Decisions

- **One more entry in the route loop.** To the array `['content-types' => 'content', 'page-types' => 'page']` `'block-types' => 'block'` is added; names `admin.block-types.*`. Alternative — separate controllers: rejected for the same reason as for `page`.
- **The section segment from a mapping, not from a ternary expression.** `ResolvesTypeSection::segment()` currently distinguishes only `page`/`content`; it is replaced by the mapping `['content' => 'content-types', 'page' => 'page-types', 'block' => 'block-types']`, so that adding a kind does not hide in the `else` branch (otherwise `block` would silently get `content-types`). The comments "`content` or `page`" in the PHPDoc of the trait and of the controllers are updated.
- **Frontend.** `TypeSection['kind']` is extended to `'content' | 'page' | 'block'`, and an entry for `block` appears in `labels` ("Block Types", "Create block type", "Edit block type", "Delete block type", the deletion confirmation, "Block type sections"). Components that check `kind === 'content'` for the slug are not changed (for `block` the slug is hidden the same way); it is checked that there is no check `kind === 'page'` instead of `!== 'content'` anywhere.
- **Navigation.** In `navigation.ts` the item `{ title: 'Block Types', href: '/admin/block-types', icon: Blocks }` stands after Page Types; `extraTitles` gets `/admin/block-types/create`, `patternTitles` — `…/block-types/[^/]+/edit`, and the field templates are generalized to `(content|page|block)-types`.
- **Translations.** New keys in `lang/en.json` and `lang/uk.json`, in the style of the keys for `page`.
- **Documentation.** A new `docs/admin-block-types.md` (briefly, with a reference to the kind mechanism in `docs/admin-page-types.md`); `docs/admin-content-types.md`, `docs/admin-content-fields.md`, `docs/admin-shell.md`, `docs/e2e-testing.md`, `docs/README.md` are updated.

## Risks / Trade-offs

- [A regression for `content`/`page` after the change of `segment()`] → the existing Feature tests and e2e of `content-types`, `content-fields`, `page-types` remain unchanged and must pass.
- [A hard-coded kind on the frontend is missed] → `grep -rn "'page'\|\"page\"" resources/js` in the task; the e2e passes the blocks section.
- [A change of `navigation.ts` and the sidebar — shared code] → according to the project rules the full `npm run test:e2e` is run.
