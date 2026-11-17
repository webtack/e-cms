# Design

## Context

`ContentTypeNav` (`Components/Admin/ContentTypes/ContentTypeNav.tsx`) is rendered by 11 pages with only `section`, `typeKey` and `active`. Each page prints its own `PageTitle` below it ("Nodes of “X”", "Fields of “X”", "Edit content type", "Display"; `Seo.tsx` has it commented out; the create/edit pages of a node and a field print "Create node", "Edit field", ...). The controllers pass the type as `$contentType->only(['key', 'name'])` (the edit page also `slug`), and several Feature tests assert this exact shape. The public address of a node is built on the server by `ContentNodeController::publicUrl`; a type list lives at `/{type slug}` (`docs/public-site.md`). The Preview item for nodes (`ContentNodeRowActions`) is the pattern to follow: `Eye` icon, `<a target="_blank" rel="noopener noreferrer">`. See `docs/admin-content-types.md` (Type section menu, Row actions), `docs/admin-content-nodes.md`, `docs/admin-content-fields.md`.

## Goals / Non-Goals

**Goals:**
- One place (`ContentTypeNav`) owns the type heading and the type Preview link, so every tab is consistent.
- The server decides whether a public address exists; the client does not rebuild the rule.

**Non-Goals:**
- No change to the breadcrumbs, the `<Head>` titles, the public `section_title`, or the page/block sections' lack of a type address.
- No new routes or permissions.

## Decisions

**1. Props of `ContentTypeNav`: `type: { key, name, slug? }` replaces `typeKey`.** The component needs the name and slug anyway; passing one object avoids three parallel props. Alternative: add `typeName` and `typeSlug` next to `typeKey` — more call-site noise for the same data. All 11 call sites are updated in this change, so no compatibility shim.

**2. The server sends `slug` in the `type` prop everywhere the Nav is rendered.** Controllers change `->only(['key', 'name'])` to `->only(['key', 'name', 'slug'])` (`ContentTypeController` display/seo/`valuesPage`, `ContentFieldController` ×3, `ContentNodeController` index/create/edit). The existing Feature assertions on `type` are updated. Alternative: a shared Inertia prop — rejected, the data belongs to the page's type, not to the whole app.

**3. Preview condition is `section.kind === 'content' && type.slug`.** Page and block types have no slug, and a `content` type may have a null slug; both mean no public list page. The link is `/${type.slug}` in a new tab. This duplicates one trivial rule on the client (`slug` already carries the decision: only `content` types have one), so no extra server field is added. The link is placed at the right edge of the tab row (`ml-auto`) inside the existing `<nav>`; it is not a tab (no `aria-current`) and is shown to a user with only the nodes tab too, because it needs no permission.

**4. The row menu gets "Preview" next to the shared list, not inside `typeSectionItems`.** `typeSectionItems` describes internal tab destinations (path under the type); Preview is an external address. `ContentTypeRowActions` appends it after "Nodes" and before the separator when `row.slug` is set and `section.kind === 'content'`. The section menu and the row menu then share the same condition. The spec's "never differ in destinations" rule is kept by using one small helper `typePublicUrl(section, slug)` exported from `section.ts`.

**5. Heading and the number of `h1`.** The type name is rendered by `ContentTypeNav` above the tabs as the page `h1` on every page that uses it. The create/edit pages of a node and a field drop their own "Create/Edit node/field" titles, so each page has exactly one `h1`; there is no `titleAs` prop. The star of the node list is a `children` slot rendered next to the heading, so `ContentNodes/Index` keeps `data-testid="favorite-toggle"` and its `router` handler.

**6. Slug truncation reuses the `DataTable` `width` mechanism** (`DataTableRow` wraps the cell in `truncate` with `maxWidth`). Node table: the service `slug` column gets `width` (about `16rem`) and a cell that sets `title` to the full slug. Types table: the same for the `slug` column. No change to `DataTable` itself. Alternative: a CSS-only max-width on `td` — unreliable, and the table already documents why the wrapper is used.

## Risks / Trade-offs

- [Existing Feature and e2e tests assert the old headings and the exact `type` prop] → update them in the same task groups that change the code (see tasks).
- [Two `h1` or none on a page] → decision 5; e2e asserts one `h1` on the node create page.
- [Preview link for a type whose list page is empty or hidden by the site returns 404] → accepted and stated in the spec, like node Preview.
- [Heading with a long type name] → the heading uses `truncate` inside the flex row so the star and tabs stay in place.

## Open Questions

- Exact `width` of the slug columns (proposed `16rem`) can be tuned visually during implementation; it does not change the specs.
