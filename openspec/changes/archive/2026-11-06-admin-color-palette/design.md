# Design

## Context

The admin theme is the shadcn `zinc` palette defined as CSS variables in `resources/css/app.css` (`:root` and `.dark`), exposed to Tailwind through `@theme inline`. Components read these variables (`bg-primary`, `bg-background`, `bg-card`, `text-primary`, `bg-sidebar`, ...). The shell is documented in [admin-shell.md](../../../docs/admin-shell.md) and the table in [admin-data-table.md](../../../docs/admin-data-table.md). See `proposal.md` for motivation.

Current state relevant to the approach (read from the code):

- `SidebarInset` and the body use `bg-background` (white); the header has only `border-b`; `DataTable` wraps its table in `rounded-md border` with no fill; form sections are copy-pasted blocks `rounded-md border p-4` (about fifteen places) with no fill.
- `Input`, `Textarea` use `bg-transparent`; `Select` trigger and `Button` `outline` use `bg-background`; `FormActionBar` uses `bg-background`.
- There is no link token: links are `text-primary` or `hover:text-primary`, and the `link` button variant is `text-primary`.
- Booleans render as bare `t('Yes' | 'No')` in `DataTableRow` and `ContentNodes/Index`; `Users/Index` has its own cell; `Badge` has only `default | secondary | destructive | outline | ghost | link`.
- Field groups are inconsistent: `PublicDisplaySettingsSection` and `NodeListSettingsSection` use unbordered `fieldset`s and a `div` + `span` for "Default sort"; `Site.tsx` uses `h3` for Emails/Phones; `MediaDetails` uses `h3` for "Used in"; `ContentFieldForm`, `MetaDataSection` and `NodeFieldFrame` already use bordered `fieldset + legend` with slightly different classes.

- Typography: the font is already Instrument Sans (weights 400/500/600 through the Vite font plugin). The page title is a repeated `<h1 className="text-2xl font-semibold">` in about forty admin pages, with `mb-6` on some and none on others. There is no base text size on the body (it inherits 16px) while most of the interface is already `text-sm`. `TableHead` is 14px in the foreground colour; `SidebarGroupLabel` is `text-xs font-medium` without uppercase.

## Goals / Non-Goals

**Goals:**
- Change the look through tokens first; touch components only where a token cannot express the change (surfaces, tones, groups).
- One definition of each recurring pattern (white section, field group, boolean badge, avatar, status tone), reused by every consumer.
- Both themes designed together; no hard-coded colours in components.

**Non-Goals:**
- No change to behaviour, routes, server code, data or translations (the existing `Yes`/`No` and status labels are reused).
- No change to the public site themes (`themes/`), which have their own assets.
- No migration of every `rounded-md border` in the project: only form sections and the listed groups. Controls with their own border (rich text editor, upload dropzone, accordions) stay as they are.
- No Playwright visual-regression snapshots.

## Decisions

### 1. Tokens, not component rewrites

Colours live in `app.css`. Light values (dark values are set in `.dark` in the same change):

| Token | Light | Dark |
| --- | --- | --- |
| `--primary` | `oklch(0.51 0.23 277)` | `oklch(0.7 0.16 277)` |
| `--primary-foreground` | `oklch(0.99 0 0)` | `oklch(0.15 0.03 277)` |
| `--background` (page) | `oklch(0.965 0.007 270)` | `oklch(0.17 0.015 277)` |
| `--card` (surface) | `oklch(1 0 0)` | `oklch(0.225 0.02 277)` |
| `--link` (new) | = primary | `oklch(0.78 0.12 277)` |
| `--success` (new) | `oklch(0.5 0.13 155)` | `oklch(0.78 0.14 155)` |
| `--warning` (new) | `oklch(0.52 0.12 65)` | `oklch(0.82 0.13 80)` |
| `--info` (new) | = primary | = `--link` |
| `--sidebar` | `oklch(0.975 0.014 277)` | `oklch(0.2 0.03 277)` |
| `--sidebar-accent` (active item) | `oklch(0.92 0.045 277)` | `oklch(0.3 0.06 277)` |

`--ring` and `--sidebar-primary` are derived from the primary hue. New tokens are registered in `@theme inline` (`--color-link`, `--color-success`, `--color-warning`, `--color-info`) so `text-link`, `text-success` etc. exist as utilities. The values come from the approved interactive preview; exact numbers are fine-tuned only if a contrast check fails (white text on `--primary` and tone text on its tint must stay legible).

*Alternative:* a separate "brand" scale (`--brand-500`). Rejected: shadcn components already read `--primary`; a second scale would duplicate it.

### 2. Surfaces: page is `--background`, panels are `--card`

`--background` becomes the muted page colour. Everything that should read as a white panel switches to `--card`: header (`AdminHeader`), `DataTable` wrapper, `FormSection`, `FormActionBar`, and the form controls (`Input`, `Textarea`, `Select` trigger, `outline` `Button`). In dark mode the existing `dark:bg-input/30` on inputs is kept.

*Alternative:* keep `--background` white and add a new `--page` token for the page. Rejected: many shadcn parts (popover, dialog, sheet) rely on `--background`/`--popover`; leaving `--background` white means fewer surprises there, but the page colour would need an extra token wired into `SidebarInset` and the layout. The chosen way is smaller in code, and the few `bg-background` users that are panels (listed above) are switched explicitly. Dialogs, popovers and sheets use `--popover`/`--card` and stay white.

### 3. Shared components

- `FormSection` (`Components/Admin/FormSection.tsx`): the bordered white section. Props: optional `title` (renders the existing `h2`), `description`, `className`, plus pass-through of `data-testid`/`aria-labelledby`. It replaces the copy-pasted `rounded-md border p-4` blocks (the `mb-10 max-w-xl space-y-4` variant of forms, the Site `Section`, the permission-matrix blocks, `PublicDisplaySettingsSection`, `NodeListSettingsSection`). Existing `data-testid` values and heading text are preserved because e2e specs look them up.
- `FieldGroup` (`Components/Admin/FieldGroup.tsx`): `fieldset` + `legend` + border + padding, `min-w-0`. Replaces the unbordered fieldsets and the `div`/`span` and `h3` headings listed in the spec. The three existing bordered fieldsets (`ContentFieldForm` file settings, `MetaDataSection`, `NodeFieldFrame`) are moved onto it only if the visual result is identical; otherwise they are left and noted in the docs (the `NodeFieldFrame` uses its own legend row with extra content).
- `BooleanBadge` (`Components/Admin/BooleanBadge.tsx`): `value`, optional `tone="warning"`. Used by the default `DataTableRow` cell, `ContentNodes/Index`, and `Users/Index`.
- `UserAvatar` (`Components/Admin/UserAvatar.tsx`): initials from the name (up to two words), `active` flag, `aria-hidden`. Used by `Users/Index`.
- `Badge` gets `success`, `warning`, `info` variants. The tint is `color-mix(in oklch, var(--tone) 14%, transparent)` with text in the tone colour, so one rule serves both themes. `MenuItemStatusBadge` and `NodeServicePanel` map states to variants (published → success, scheduled → info, expired → warning, disabled → outline/neutral). The `data-status` attributes are kept.

### 4. Column tone option

`Column` in `DataTable/types.ts` gets an optional `booleanTone?: 'warning'`. `DataTableRow` passes it to `BooleanBadge`. `Users/Index` sets it on "Active" and drops its custom cell. No change to the address contract, filters or sorting.

### 5. Table header and hover

`TableHead` rows get a faint primary tint (`bg-primary/5`) and `TableRow` hover uses `bg-primary/5` instead of `bg-muted/50`. Drag-and-drop rows keep their `bg-muted shadow-md` highlight.

### 6. Typography

The page title becomes a shared `PageTitle` (`Components/Admin/PageTitle.tsx`): an `h1` with `text-xl font-semibold` that accepts `className`, so each page keeps its own spacing (`mb-6` or none) and the replacement changes only size and weight. All admin pages in `Pages/Admin/*` use it; `Auth/Login` and `Auth/VerifyEmail` keep their own `h1`. The base size is set once on `body` (`text-sm`), so unstyled text inherits 14px; inputs keep `text-base md:text-sm`, which holds 16px on narrow screens. `TableHead` becomes `text-xs text-muted-foreground font-medium`, and `SidebarGroupLabel` gets `uppercase tracking-wide` at its current small size. Section headings (`h2`, `text-lg`) are unchanged.

*Alternative:* a global `h1` rule in `@layer base`. Rejected: it would also change the login pages, would not remove the forty repeated classes (utility classes win over a base rule), and leaves the inconsistent margins untouched.

### 7. Documentation

A new `docs/admin-visual-style.md` lists the tokens, the type scale, `PageTitle`, tones, `Badge` variants, `BooleanBadge`, `UserAvatar`, `FormSection`, `FieldGroup` and the rules for adding a new section or status. `docs/admin-shell.md` (shadcn section) and `docs/admin-data-table.md` (boolean cell, `booleanTone`) link to it, and `docs/e2e-testing.md` gets the shared-code rows for the new components.

## Risks / Trade-offs

- [Muted `--background` makes components that use `bg-muted/30` or `bg-muted/50` as a fill (for example `NodeFieldsPanel`, the DataTable toolbar strip) nearly invisible] → Review every `bg-muted*` fill on a page surface during the surfaces task and move those panels to `--card` or a stronger tint.
- [Switching `bg-background` to `bg-card` in `ui/*` also affects dialogs, popovers and the rich text editor toolbar] → Only the listed controls change; open one dialog, one popover and the editor in the browser check.
- [e2e specs may rely on classes or text that change, for example "No" inside a badge or grouped `fieldset`s changing role queries] → Run the dependent specs from the shared-code table; `FieldGroup` keeps the legend text, so `getByRole('group', { name })` queries keep working.
- [Contrast of tone text on its tint, especially warning amber and the teal-free indigo link on the muted page] → Check light and dark visually and with a contrast calculator on `--link`/`--card`, `--success`/tint, `--warning`/tint; adjust lightness only.
- [A 14px base size and smaller table headers can reduce readability or shift layouts that relied on 16px text] → Only unstyled text changes size (most text is already `text-sm`); check the dashboard, a form page and a table at desktop and phone width; inputs stay at 16px on narrow screens.
- [Wide change surface (about thirty files) in one change] → Tasks are ordered so that tokens land first and each following group is independently verifiable; no group alters behaviour.
- [`FormSection` extraction touches many forms] → Mechanical replacement that preserves `data-testid`, ids and heading text; run the dependent specs after each group of forms.

## Migration Plan

Frontend-only; deployed by `npm run build`. No database, config or server changes. Rollback is reverting the commit; the previous palette is restored by the token values in `app.css`.

## Open Questions

- None that affect the specs or tasks. The exact lightness values may be fine-tuned during the contrast check.
