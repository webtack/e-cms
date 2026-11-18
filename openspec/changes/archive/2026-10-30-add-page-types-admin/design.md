# Design

## Context

See proposal.md — Why. Today `ContentTypeController`, `ContentFieldController`, the form requests and the Inertia pages are hard-bound to the kind `content`: `Route::bind('contentType', …)` in `routes/web.php` selects only `ofKind('content')`, `store()` writes `'kind' => 'content'`, `parentOptions()` and the rule `parent_type` filter by `content`, and the frontend has paths `/admin/content-types/...` in `ContentTypeForm`, `ContentTypeRowActions`, `ContentTypeNav`, `ContentFieldForm`, `ContentFieldRowActions` and the field pages. The model is already kind-agnostic: the slug is forbidden for `page`, the parent must have the same kind (`ContentType::assertValid()`), the system field `title` is added for all kinds. Described in `docs/content-model.md`, `docs/admin-content-types.md`, `docs/admin-content-fields.md`, `docs/admin-shell.md`.

## Goals / Non-Goals

**Goals:**
- The `page` pages fully repeat the functionality of `content` without copying controllers and React pages.
- The old addresses `/admin/content-types/**` behave as before (404 for `page`/`block`).

**Non-Goals:**
- The kind `block` and nodes (node pages, the page tree, routing) — separate changes; the layout item "Pages" is not touched.
- Changes to the model, migrations or the configuration of kinds.

## Decisions

- **The route defines the kind.** A second set of routes `/admin/page-types/**` is registered with the same controllers and `->defaults('kind', 'page')` (for `content` — `'content'`); both sets are registered in one loop/group so that they do not diverge. `Route::bind('contentType')` takes the kind from `$route->defaults['kind']` and selects `ofKind($kind)`. Alternative — separate `PageTypeController`/`PageTypeFieldController`: rejected, because it is ~300 lines of duplicated logic (deletion, ordering, `valuedKeys`), which would diverge.
- **The kind in the controllers.** The controllers read the kind from the route (`$request->route('kind')`) and use it in `index()` (`ofKind`), `store()` (`'kind' => $kind`) and `parentOptions()`. Route names: `admin.page-types.*`, `admin.page-types.fields.*`; redirects after actions are built from the name of the current route (the prefix `content-types`/`page-types`), not from a hard-coded `to_route('admin.content-types.index')`.
- **Validation.** `StoreContentTypeRequest::sharedRules()` receives the kind: the rule `parent_type` filters with `where('kind', $kind)`, and `slug` is in the rules only for `content` (for `page` the key `slug` is not in the rules, so it is dropped from `validated()`). `UpdateContentTypeRequest` takes the kind from the route's type.
- **Frontend — one set of pages.** The controller passes in the Inertia props `section`: `{ kind, basePath, titles… }` or at least `basePath` (`/admin/content-types` or `/admin/page-types`) and `kind`. `ContentTypeForm`, `ContentTypeRowActions`, `ContentTypeNav`, `ContentFieldForm`, `ContentFieldRowActions` and the pages `Pages/Admin/ContentTypes/*`, `ContentFields/*` use `basePath` instead of a hard-coded path; the slug field and the slug column are shown only for `kind === 'content'`. The headers/dialogs ("Page Types", "Delete page type") are taken from the translations by the kind. Alternative — separate pages `Pages/Admin/PageTypes/*`: rejected because of duplication.
- **Navigation.** In `navigation.ts` the item `{ title: 'Page Types', href: '/admin/page-types', icon: FileStack }` is added after Content Types, and in `extraTitles`/`patternTitles` — entries for `/admin/page-types/create`, `/{key}/edit`, `/fields…` (the titles "Create page type", "Edit page type"). The breadcrumbs are shared by both sections: the templates are generalized to `(content|page)-types`.
- **The section address.** `/admin/page-types` (the key in the URL, as for the other entities); the names `page-types`, so as not to confuse them with the future `/admin/pages` for nodes.

## Risks / Trade-offs

- [A regression for `content` after the generalization] → the existing Feature tests and e2e of `content-types`/`content-fields` remain unchanged and must pass; a check is added that `/admin/content-types/**` for `page` is 404.
- [The kind taken from `route defaults` is an implicit dependency] → one helper method in the controller and a comment; a Feature test for each kind.
- [Hard-coded paths that can be missed in the frontend] → a search for `"/admin/content-types"` in `resources/js` in the tasks; e2e passes both sections.
- [Shared translations "Content type" in dialogs] → separate keys for `page`; `lang/*.json` are checked by a test/build.
