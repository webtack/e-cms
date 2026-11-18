## Context

The two row menus are separate components passed into `DataTable` through `rowActions`: `ContentTypeRowActions` and `ContentFieldRowActions`. Right now each renders only a `DropdownMenu` with a `ghost`/`icon-sm` trigger button and an `AlertDialog` for deletion next to it. There is no `ButtonGroup` in `Components/ui`. The motivation is in proposal.md.

## Goals / Non-Goals

**Goals:**
- A main link button + the menu trigger as one visual group in both tables.
- The menu, the deletion dialog and the deletion blocking rules do not change.

**Non-Goals:**
- Changing the composition of the menu, the server routes, the `rowActions` mechanism in `DataTable`.
- Removing the duplicate "Edit" from the menu.

## Decisions

- **`ButtonGroup` as a shadcn primitive** in `Components/ui/button-group.tsx` (a container `role="group"`, merged borders and rounded corners of the child buttons). Alternative — manual classes in each component: duplication and divergences between the two tables.
- **The `outline` variant of the buttons**: in a group `ghost` has no borders, and the group would not read as a single whole. The main one — `Button asChild size="sm"` with a `Link`; the trigger — `size="icon-sm"`, `aria-label` remains `t('Actions')`, so that the e2e locators do not break.
- **Labels**: types — `t('Settings')` (the key is already in `lang/uk.json`, matching the section menu of the type); fields — `t('Edit')`. There are no new translations.
- **Width of the actions column**: the group is wider than one icon; check that the actions column in `DataTable` does not cut it off (the last commit limited the width of the data columns, not the actions).

## Risks / Trade-offs

- [A duplicate "Edit" in the menu and on the button] → accepted per the request "the menu as it is"; it can be removed in a separate change.
- [Narrow screens: the group takes more space] → the main button without an icon with a short label; check in e2e/manually.
- [Focus and closing of the menu with an `asChild` trigger inside the group] → keep `DropdownMenuTrigger asChild` on the button, check with the keyboard.

## Documentation

The components are described in: `docs/admin-content-types.md` (the section on the row actions), `docs/admin-content-fields.md` (the section "Row menu"), `docs/admin-data-table.md` (the description of `rowActions`).
