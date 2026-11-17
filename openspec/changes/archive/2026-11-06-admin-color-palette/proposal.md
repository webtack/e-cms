# Proposal

## Why

The admin panel is entirely grey (the shadcn `zinc` palette): the primary colour is near-black, the sidebar is off-white, the page background is pure white, and tables and forms sit directly on that background with no visual separation. Links, statuses and yes/no values are plain text or grey badges, and several form sections group their fields without any border or legend. The result is hard to scan and has no colour identity. A single palette with a few semantic tones and consistent grouping makes the interface easier to read without changing any behaviour.

## What Changes

- **Palette** (light and dark themes), all driven by the existing CSS variables in `resources/css/app.css`:
  - Primary becomes indigo; focus rings and the sidebar accent follow it.
  - A new `--link` token (indigo in light, a lighter indigo in dark) for links.
  - The sidebar becomes a light, indigo-tinted surface; the active item is a soft indigo pill.
  - The main area (`--background`) becomes a muted, slightly cool grey; surfaces (`--card`) stay white.
  - New semantic tokens `--success`, `--warning`, `--info` (with matching dark values).
- **White surfaces on a muted page**: the header, the `DataTable` wrapper, form sections (`rounded-md border p-4` blocks), inputs, selects, textareas and `outline` buttons use `--card` instead of `--background`/transparent, so tables and forms read as white panels on the muted background.
- **Badges**: new `success`, `warning` and `info` variants of `Badge`, rendered as a soft tint of the tone with text only. Publication statuses use them (published = success, scheduled = info, expired = warning, disabled = neutral).
- **Boolean values**: the `Yes`/`No` cell in tables becomes a text-only badge (`Yes` = soft green, `No` = quiet grey text). A column can opt into a warning tone for `No` (used by the Users "Active" column). Applies to the default `DataTable` boolean cell and to the node list cell.
- **User avatars**: the Users list shows initials in a soft indigo circle before the name (grey for an inactive user).
- **"System" badge**: the Roles list marks the system role with a soft indigo badge instead of the grey secondary one.
- **Table styling**: table header row on a faint primary tint; subtle row hover.
- **Typography** (same typeface, Instrument Sans, already loaded): the page title (`h1`) becomes 20px semibold instead of 24px; the base text size becomes 14px; table column headers become 12px muted; the sidebar group labels become small uppercase with letter spacing. A shared `PageTitle` replaces the repeated `h1` markup of the admin pages (the login and email verification pages are not changed).
- **Field groups**: a shared `FieldGroup` (`fieldset` + `legend` + border) replaces unbordered groups: "Page fields", "Card fields", "Default sort" and the pagination controls in "Public display"; "Columns", "Search fields" and "Default sort" in the "Node list" section; "Emails" and "Phones" in the site "Contacts" section; "Used in" in the media details. A shared `FormSection` replaces the hand-copied bordered section blocks.
- No change to routes, server code, data, or the public site themes.

## Capabilities

### New Capabilities
- `admin-visual-style`: the admin colour palette (light and dark), white surfaces on a muted page, semantic badge tones and publication status colours, boolean value badges, and the field-group (border + legend) presentation of form sections.

### Modified Capabilities
- `admin-data-table`: the boolean cell is shown as a text-only badge and a column can request a warning tone for `No`.
- `admin-users`: the users list shows an initials avatar before the name.

## Impact

- **Code (frontend only)**: `resources/css/app.css` (tokens); `Components/ui/{badge,button,input,select,textarea,switch}.tsx`; `Components/Admin/DataTable/{DataTable,DataTableRow,types}` (`.tsx`/`.ts`); new `Components/Admin/{FieldGroup,FormSection,BooleanBadge,UserAvatar,PageTitle}.tsx`; `Components/ui/{table,sidebar}.tsx` (header and group-label typography); the `h1` of every page in `Pages/Admin/*`; `Components/Admin/FormActionBar.tsx`, `AdminHeader.tsx`; `Layouts/AdminLayout.tsx`; status badges (`Menus/MenuItemStatusBadge`, `ContentNodes/NodeServicePanel`); form sections in `ContentTypes/*`, `ContentFields/ContentFieldForm`, `Taxonomy/*Form`, `Users/UserForm`, `Roles/*`, `Menus/*Form`, `Pages/Admin/Settings/Site.tsx`, `Media/MediaDetails`; `Pages/Admin/Users/Index.tsx`, `Pages/Admin/Roles/Index.tsx`, `ContentNodes/Index.tsx`.
- **Tests**: e2e specs that touch the changed shared components (see the "shared code → specs" table in `docs/e2e-testing.md`); no PHP behaviour changes, so no PHP tests are required.
- **Docs**: new `docs/admin-visual-style.md` (tokens, badges, `FieldGroup`/`FormSection`, usage rules); update `docs/admin-shell.md` (the "Theme tokens" mention in the shadcn section links to the new file), `docs/admin-data-table.md` (boolean cell and tone option, header typography), `docs/README.md` (index), and the shared-code table in `docs/e2e-testing.md` for the new shared components.
- **Specs**: new `admin-visual-style`; added requirements in `admin-data-table` and `admin-users`.
