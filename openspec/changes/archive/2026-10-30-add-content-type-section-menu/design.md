# Design

## Context

All four pages are Inertia pages with `AdminLayout`; each already receives `type` (the key and the name). The markup of the headers is repeated on the pages, and the link "← Content types" exists only in `Pages/Admin/ContentFields/Index.tsx`. Breadcrumbs are built from the URL in `navigation.ts` and do not change. Description of the components: `docs/admin-content-types.md`, `docs/admin-content-fields.md`.

## Goals / Non-Goals

**Goals:**
- One menu component used by all pages of the section.
- No changes on the backend.

**Non-Goals:**
- A menu for the kinds `page`/`block` and for the type creation page.
- New sections (nodes, etc.) — items are added later in the same component.

## Decisions

- **The component `ContentTypeNav`** (`Components/Admin/ContentTypes/ContentTypeNav.tsx`) with the props `typeKey` and `active: 'settings' | 'fields'`; the items — Inertia `Link` with `aria-current="page"` on the active one. The prop `active` is explicit, not from `usePage().url`: it is simpler to test and does not depend on the URL form (the field forms have the same items as the list).
- **A shared wrapper**: the component also renders the link "← Content types" above the menu, so the pages do not duplicate it. Alternatives: an Inertia nested layout — rejected, because the `type` data is already in the props of each page, and a nested layout would complicate persistence without benefit.
- **Styles**: a horizontal row with a bottom border, the active item with an underline (Tailwind theme tokens); no new `ui` primitives are added.
- **Translations**: the keys "Settings" and "Fields" (the latter already exists) are added to `lang/*.json` if missing.

## Risks / Trade-offs

- [The active item is set manually on each page] → a small number of pages; e2e checks `aria-current` on each.
- [Extra height on the forms] → the menu is compact, one row.
