# Design

## Context

See proposal.md. The current state from which the approach starts:

- The sections of types are already generalized by kind: `routes/web.php` registers the set of type routes in a loop over `['content-types' => 'content', 'page-types' => 'page', 'block-types' => 'block']` with `defaults('kind', …)`, `Route::bind('contentType')` selects the type by kind, and the trait `ResolvesTypeSection` provides `kind()`, `routeName()` and the Inertia prop `section` (`{ kind, basePath }`) ([admin-page-types.md](../../../docs/admin-page-types.md#вид-задає-маршрут)).
- The records are registered in a separate loop only under `/content-types/{contentType}/nodes` with `defaults('kind', 'content')` (`routes/web.php`). `ContentNodeController` already uses `ResolvesTypeSection` (`section($request)` in the props, `routeName($request, 'nodes.index')` in `store`), and the React pages `Pages/Admin/ContentNodes/*`, `ContentNodeForm` and `ContentNodeRowActions` build addresses from `section.basePath`. That is, the backend and the frontend of records are almost ready for the other kinds.
- Tied hard to `content` only: the loop of record routes; `ContentTypeNav` and `ContentTypeRowActions` (the item «Записи» only when `section.kind === 'content'`); `linkedNodesColumn` in `Pages/Admin/ContentTypes/Index.tsx`; the breadcrumb templates of records in `navigation.ts`; `ContentNodeOwner` (the link only when `kind === 'content'`, the route name `admin.content-types.nodes.edit`).
- The slug rules by kind are already in the model: `ContentNode::assertValidSlug()` requires the prefix `block-` for blocks, `SlugGenerator::forType()` generates `block-xxxxxxxx` for blocks and a slug from the title for the rest ([content-model.md](../../../docs/content-model.md#slug-запису)).
- The description of the behaviour of records: [admin-content-nodes.md](../../../docs/admin-content-nodes.md); the sections of types: [admin-page-types.md](../../../docs/admin-page-types.md), [admin-block-types.md](../../../docs/admin-block-types.md); the media library: [media-library.md](../../../docs/media-library.md).

## Goals / Non-Goals

**Goals:**
- One controller, one set of pages and forms of records for all three kinds; the kind determines only the route.
- No duplication of routes, controllers or React components.
- The same behaviour of records in the sections (addressing by `uuid`, 404 for a foreign kind, redirection to the section of the same kind).

**Non-Goals:**
- The page tree, public addresses and rendering, placement of blocks on a page, a change of the kind-specific fields of the form.
- New fields or service attributes of a record; any migrations.
- The mock item «Pages» (`/admin/pages`) in the sidebar remains a mock.

## Decisions

**1. A loop of record routes over the same three sections.** The record routes are registered inside a loop over the sections: `"/$section/{contentType}/nodes…"` with the name `"$section.nodes.$name"` and `defaults('kind', $kind)`. The names `admin.content-types.nodes.*` do not change; `admin.page-types.nodes.*` and `admin.block-types.nodes.*` appear. The binding rules (`Route::bind('contentType')` by kind, `Route::bind('node')` by the pair (type, `uuid`)) already work correctly for any kind, so a foreign kind gives 404 without new code. *Alternative* — separate record controllers for pages and blocks: rejected, because the behaviour is the same, and differences will appear only when kind-specific functions arise (separate changes).

**2. The item «Записи» is shown for all kinds.** In `ContentTypeNav`, `ContentTypeRowActions` and `Index.tsx` the condition `section.kind === 'content'` for records is removed; `linkedNodesColumn` applies to all kinds. The counter `nodes_count` is already provided by `ContentTypeController` for each kind, so no server changes are needed. *Alternative* — a flag "supports records" on the section: rejected as an unnecessary abstraction while all three sections support records.

**3. The breadcrumbs — a generalized template.** The three templates of records in `navigation.ts` are merged into `(content|page|block)-types`, as already done for fields; the sidebar item for the intermediate breadcrumb is found by the existing `isActive` logic.

**4. `ContentNodeOwner` builds the URL by kind.** The route name is composed from the section segment of the kind (`content` → `content-types`, `page` → `page-types`, `block` → `block-types`); the correspondence is taken from one place (a shared constant/method next to `ResolvesTypeSection::SEGMENTS`), so as not to duplicate the map. For an unknown kind the link stays `null`. *Alternative* — a copy of the map in the owner: rejected because of the risk of a divergence from the routes.

**5. The prefix of a block slug — in the form, the truth — on the server.** The server remains the arbiter: a block without an explicit slug gets `block-…` from `ContentNodeCreator`, an explicit slug without the prefix is rejected by the model with the error `slug`, which `ContentNodeController::forForm()` returns next to the slug field (checked by a Feature test; if the error does not reach the field — it is fixed within this change). On top of this, the form for the kind `block` shows `block-` as an unchangeable part of the field (an addon before the `Input`, for example based on the existing `ButtonGroup`) and works only with the rest of the slug. The form does not write the prefix into a hard-coded string: the controller passes it as the prop `slugPrefix` (`ContentNode::BLOCK_PREFIX` for the kind `block`, an empty string for the others), so the knowledge of the prefix lives in one place. Normalization in `ContentNodeForm`: the state `data.slug` keeps only the rest; `onChange` cuts an explicitly typed/pasted prefix at the beginning (so as not to double it), and `transform` before the request adds the prefix to a non-empty rest. An empty rest is sent empty. A hint under the field in the creation mode explains that an empty field gives a generated slug with the prefix. The length limit (190) is counted from the full slug, so the length error is given by the server. *Alternatives:* (a) keep the full slug with a pre-filled `block-` in the state — rejected, because an empty field stops meaning "generate" and the prefix can be erased; (b) only a hint under the field — rejected, because the user does not see the final result.

**6. Test coverage.** The current Feature tests expect 404 for records of `page-types`/`block-types` (`ContentNodeIndexTest`, `ContentNodeWriteTest`); they are rewritten for the new behaviour (200 for its own kind, 404 for a foreign one). E2E: new scenarios in `admin-page-types.spec.js` and `admin-block-types.spec.js` (the menu, the transition to the list, creating a record, breadcrumbs); a full run of `admin-content-nodes` is not needed, because the form of records does not change, except in the test that checks the absence of links in the section of pages (it is updated).

## Risks / Trade-offs

- [Tests that fix "there are no records for page/block" (Feature, e2e, docs) will remain outdated] → the tasks explicitly rewrite `ContentNodeIndexTest`, `ContentNodeWriteTest`, the e2e `admin-content-types` and `admin-content-nodes` (the check of the absence of record links in the section of pages), and a `grep` over `docs/` for the wording "записів немає".
- [Deleting a type with records] → unchanged: `nodes_count > 0` already blocks the deletion of a type for `page`/`block` too; now the user can reach the records from the dialog.
- [The working tree contains an uncommitted change `redesign-node-form`] → the code of the record form is not rewritten in this change, only used; there are no file conflicts except `ContentNodeController` (the class comment).
- [A divergence between the prefix in the form and on the server] → the prefix comes from the server as the prop `slugPrefix`, and is not duplicated in JS; a Feature test checks the prop, the e2e — the final slug.
- [A user pastes a slug with the prefix] → `onChange` cuts it in the field, `transform` adds it back, so the result is not doubled; covered by e2e.
