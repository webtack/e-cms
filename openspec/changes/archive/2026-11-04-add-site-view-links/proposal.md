# Proposal

## Why

From the admin there is no quick transition to the public site, and from the form of editing a node — to its page. To check the result the administrator types the address into the browser by hand.

## What Changes

- An icon button «Відкрити сайт» ("Open site") appears in the header of the admin shell: a link to the home page of the site (`/`), which opens in a new tab.
- In the form of editing a record (a page, a record `content`) a button «Перегляд» ("Preview") appears in the bottom action panel: a link to the public address of the record, which opens in a new tab.
- The address of the record is built on the server: a page — `/{slug}`, a record `content` — `/{type.slug}/{node.slug}`; for blocks (they have no address of their own) there is no button.
- The button «Перегляд» is a simple link: for an invisible record (disabled, scheduled, expired) the site will still give 404. There is no separate route for previewing drafts.
- The new interface strings are translated into Ukrainian.

## Capabilities

### New Capabilities

### Modified Capabilities
- `admin-shell`: the header of the shell receives a button-link to the site.
- `admin-content-nodes`: the form of editing a record receives the button «Перегляд».

## Impact

- Code: `resources/js/Components/Admin/AdminHeader.tsx`, `resources/js/Components/Admin/ContentNodes/ContentNodeForm.tsx`, `resources/js/Pages/Admin/ContentNodes/Edit.tsx`, `app/Http/Controllers/Admin/ContentNodeController.php` (the prop `publicUrl`), the translation files `uk`.
- Tests: a Feature test of `edit` (the prop `publicUrl`), e2e `admin-shell`, `admin-content-nodes`.
- Docs: update `docs/admin-shell.md`, `docs/admin-content-nodes.md`; `docs/e2e-testing.md` if needed.
