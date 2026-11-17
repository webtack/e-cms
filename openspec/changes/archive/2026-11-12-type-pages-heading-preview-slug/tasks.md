# Tasks

## 1. Server: slug in the `type` prop

- [x] 1.1 Change `ContentFieldController` (index, create, edit), `ContentTypeController` (display, seo via `valuesPage`) and `ContentNodeController` (index, create, edit) to send `$contentType->only(['key', 'name', 'slug'])`; verify with `docker compose exec app php artisan test --compact --filter=ContentFieldIndexTest` after updating the assertions in the next task.
- [x] 1.2 Update the Feature tests that assert the exact `type` prop (`ContentFieldCreateTest`, `ContentFieldEditTest`, `ContentFieldIndexTest`, `ContentNodeIndexTest`, and the node create/edit and type display/seo tests found by `grep -rn "'type', \[" tests/Feature/Admin`) to expect `slug`, and add one assertion that a `content` type with a slug sends it and one without sends `null`; verify by running `docker compose exec app php artisan test --compact tests/Feature/Admin` filtered to the changed files (`--filter=ContentField`, `--filter=ContentNode`, `--filter=ContentType`, `--filter=PageType`, `--filter=BlockType`).

## 2. `ContentTypeNav`: heading, Preview link

- [x] 2.1 Add `typePublicUrl(section, slug)` to `Components/Admin/ContentTypes/section.ts` (returns `/${slug}` for kind `content` with a non-empty slug, otherwise `null`) with JSDoc and `@example`; verify with `npx tsc --noEmit` on the host.
- [x] 2.2 Rewrite `ContentTypeNav` props to `type: { key, name, slug? }`, `active`, optional `children` (shown next to the heading); render the heading above the tabs and the "Preview" link (`Eye` icon, `target="_blank"`, `rel="noopener noreferrer"`, `ml-auto`) at the right edge of the tab row only when `typePublicUrl` is not null; add JSDoc with `@example`; verify with `npx tsc --noEmit`.
- [x] 2.3 Update all call sites to the new props and remove the page titles: `ContentTypes/{Edit,Display,Seo}.tsx`, `ContentFields/Index.tsx` (remove its `PageTitle`), `ContentNodes/Index.tsx` (remove its `PageTitle`, pass the favorites star as `children`); `ContentFields/{Create,Edit}.tsx` and `ContentNodes/{Create,Edit}.tsx` drop their form titles; drop unused imports; verify with `npx tsc --noEmit` and `npm run build`.
- [x] 2.4 Update the e2e specs that assert the removed headings or the star position (`admin-content-types` — "Edit content type" at line ~367, `admin-content-fields`, `admin-content-nodes` via `-g`, `admin-page-types`, `admin-block-types` where they assert titles), and add tests: heading shows `name` on each tab, one `h1` on the node create page, Preview link on a `content` type with a slug (`href` `/{slug}`, `target=_blank`), absent without a slug and for page/block types; verify with `npm run test:e2e -- e2e/admin-content-types.spec.js`, `e2e/admin-content-fields.spec.js`, `e2e/admin-page-types.spec.js`, `e2e/admin-block-types.spec.js`, and `e2e/admin-content-nodes.spec.js -g "<favorite|heading|title>"` (not the whole nodes spec).
- [x] 2.5 Update `docs/admin-content-types.md` (Type section menu: heading, Preview link, new props), `docs/admin-content-nodes.md`, `docs/admin-content-fields.md`, `docs/admin-page-types.md` and `docs/admin-block-types.md` (headings); verify the described props match the code and `docs/README.md` links are still valid.

## 3. Row menu "Preview" in the types list

- [x] 3.1 Add the "Preview" item (`Eye`, new tab) to `ContentTypeRowActions` after "Nodes" and before the separator, shown when `typePublicUrl(section, row.slug)` is not null; add `slug` to `ContentTypeActionsRow` and pass it from `Pages/Admin/ContentTypes/Index.tsx`; update the JSDoc; verify with `npx tsc --noEmit`.
- [x] 3.2 Update `e2e/admin-content-types.spec.js` (menu items list at line ~192 now includes "Preview" for a type with a slug; add a case without a slug and a click case) and the matching sentences in `docs/admin-content-types.md` (Row actions); verify with `npm run test:e2e -- e2e/admin-content-types.spec.js -g "<menu|Preview>"`. `admin-page-types` and `admin-block-types` need no run (their menus are unchanged).

## 4. Slug column width

- [x] 4.1 Set `width` (about `16rem`) and a cell with `title` for the service `slug` column in `ContentNodes/Index.tsx` and for the `slugColumn` in `ContentTypes/Index.tsx`; JSDoc/comment updated; verify with `npx tsc --noEmit` and a visual check with a long slug.
- [x] 4.2 Add an e2e check that a long slug is cut (scrollWidth > clientWidth or the `title` equals the full slug) in `admin-content-nodes` (single test via `-g`) and `admin-content-types`; update the column tables in `docs/admin-content-nodes.md` and `docs/admin-content-types.md`; verify with `npm run test:e2e -- e2e/admin-content-types.spec.js -g "<slug>"` and `e2e/admin-content-nodes.spec.js -g "<slug>"`.
