# Admin visual style

The look of the admin panel is driven by CSS variables in `resources/css/app.css` (`:root` for the light theme, `.dark` for the dark theme), exposed to Tailwind through `@theme inline`. Components read tokens (`bg-primary`, `bg-card`, `text-link`); they never hard-code colours. The shell and the shadcn setup are described in [Admin shell](admin-shell.md).

## Tokens

Values are `oklch`. The accent is indigo (hue 277).

| Token | Light | Dark | Used for |
| --- | --- | --- | --- |
| `--primary` | `oklch(0.51 0.23 277)` | `oklch(0.7 0.16 277)` | primary buttons, focus accent |
| `--primary-foreground` | `oklch(0.99 0 0)` | `oklch(0.15 0.03 277)` | text on `--primary` |
| `--background` | `oklch(0.965 0.007 270)` | `oklch(0.17 0.015 277)` | the page area (muted grey) |
| `--card` | `oklch(1 0 0)` | `oklch(0.225 0.02 277)` | panels: header, tables, form sections, controls |
| `--ring` | `oklch(0.62 0.17 277)` | `oklch(0.55 0.14 277)` | focus ring |
| `--link` | = `--primary` | `oklch(0.78 0.12 277)` | links (`text-link`) |
| `--success` | `oklch(0.46 0.13 155)` | `oklch(0.78 0.14 155)` | success tone |
| `--warning` | `oklch(0.48 0.12 65)` | `oklch(0.82 0.13 80)` | warning tone |
| `--info` | = `--primary` | = `--link` | info tone |
| `--sidebar` | `oklch(0.975 0.014 277)` | `oklch(0.2 0.03 277)` | sidebar surface |
| `--sidebar-accent` | `oklch(0.92 0.045 277)` | `oklch(0.3 0.06 277)` | active sidebar item |

The tone lightness was adjusted after a contrast check: in the light theme the worst case (tone text on its 14% tint over the page grey) is 4.64 or more, white on `--primary` is 6.13, `--link` on `--card` is 6.30; in the dark theme every pair is above 6.3.

## Links

Links in page content use `text-link` (and `hover:text-link` for underlined links). Use `text-primary` only for non-link accents.

## Sidebar and surfaces

The sidebar is a light indigo-tinted surface; the active item is a soft indigo pill (`--sidebar-accent`). The page area is `--background`; anything that should read as a white panel uses `--card`.

### Surface rules

- The page is `--background` (muted grey). **Panels** use `--card` (white; a lighter surface than the page in the dark theme): the header (`AdminHeader`), the `DataTable` wrapper, `FormSection`, the sticky `FormActionBar`, and the form controls (`Input`, `Textarea`, the `Select` trigger, the `outline` `Button`, native `select` in forms). In the dark theme inputs keep their `dark:bg-input/30` tint.
- Do not use `bg-muted` / `bg-muted/NN` as a fill on a page surface: it is almost the same colour as the page and disappears. Use `bg-card` (or a visible tint such as `bg-primary/5`). `bg-muted` is fine for hover and drag highlights.
- Dialogs, popovers, sheets and the rich text editor keep `--popover` / their own styling and are not part of this rule.

### FormSection

`Components/Admin/FormSection.tsx` is the one bordered white panel (`rounded-md border bg-card p-4`) for a group of form fields. Spacing and width come through `className` (for example `mb-10 max-w-xl space-y-4`); `data-testid`, `aria-labelledby` and other attributes are passed to the element. Use `as="section"` for a landmark section, and the optional `title` / `titleId` / `description` props for a standard heading. Do not copy `rounded-md border p-4` into a form: use `FormSection`.

Not moved onto it: the `file-settings` fieldset of `ContentFieldForm` (a candidate for `FieldGroup`, see the field-group notes) and controls that have their own border (rich text editor, upload dropzone, accordions).

## BooleanBadge and status tones

`Badge` (`Components/ui/badge.tsx`) has the tone variants `success`, `warning` and `info`: a 14% tint of the tone colour with text in that colour, no icon. A badge always carries a text label, so the colour is never the only carrier of meaning.

- `BooleanBadge` (`Components/Admin/BooleanBadge.tsx`) shows a yes/no value: "Yes" as a `success` badge, "No" as muted text, or as a `warning` badge with `tone="warning"`. The default boolean cell of `DataTable` and the node list use it; a column opts into the warning tone with `booleanTone: 'warning'` (see [admin-data-table.md](admin-data-table.md)).
- Publication states (`MenuItemStatusBadge`, `NodeStatusBadge`) map to tones: published → `success`, scheduled → `info`, expired → `warning`, disabled → `outline` (neutral). The `data-status` attribute and label texts are unchanged.

## UserAvatar and the system badge

- `UserAvatar` (`Components/Admin/UserAvatar.tsx`) is a round decorative avatar with the initials of up to two words of the name; `active` selects the soft indigo tint, `active={false}` the neutral grey. It is `aria-hidden`, so always render the name next to it. Used in the Users list.
- The system role in the Roles list is marked with the `info` badge (soft indigo) instead of the grey `secondary` one.

## FieldGroup

`FieldGroup` (`Components/Admin/FieldGroup.tsx`) is a bordered `fieldset` with a `legend`; assistive technology announces it as a group named by the legend. **Rule:** related controls inside a section (a list of check boxes, a field/direction pair, repeated rows) go in a bordered group with a legend, not under a bare heading.

Used in: "Page fields", "Card fields", "Default sort" and "Pagination" (nodes per page and the paginator toggle) in `PublicDisplaySettingsSection`; "Columns", "Search fields" and "Default sort" in `NodeListSettingsSection`; "Emails" and "Phones" in the site "Contacts" section; "Used in" in `MediaDetails`. `ContentFieldForm` (file settings) and `MetaDataSection` were moved onto it with the same look (`MetaDataSection` keeps `rounded-lg` through `className`). `NodeFieldFrame` keeps its own `fieldset`: its legend row holds extra content and the field frame has a different border treatment.

## Typography and PageTitle

The typeface is Instrument Sans (weights 400/500/600). The type scale:

| Element | Size and weight | Where it is set |
| --- | --- | --- |
| Page title (`h1`) | 20px (`text-xl`), semibold | `PageTitle` |
| Base text | 14px (`text-sm`) | `body` in `resources/css/app.css` |
| Table column header | 12px (`text-xs`), medium, muted colour | `TableHead` in `Components/ui/table.tsx` |
| Sidebar group label | `text-xs`, uppercase, `tracking-wide` | `SidebarGroupLabel` in `Components/ui/sidebar.tsx` |
| Section heading (`h2`) | `text-lg` (unchanged) | `FormSection` and the pages |
| Text input | `text-base md:text-sm` (16px on phones, so mobile browsers do not zoom on focus) | `Input`, `Textarea` |

`PageTitle` (`Components/Admin/PageTitle.tsx`) is the `h1` of every page under `Pages/Admin/*`. It accepts `className` for the page's own spacing (`mb-6` on form pages, none on lists). The login and email verification pages (`Pages/Auth/*`) keep their own `h1`. The login page additionally has a background image and a card around the form (see [admin-auth.md](admin-auth.md#login-and-logout)).

## How to add a token

1. Add the variable to `:root` and `.dark` in `resources/css/app.css` with a comment.
2. Register it in `@theme inline` as `--color-<name>: var(--<name>)` so the `text-<name>` / `bg-<name>` utilities exist.
3. Check contrast in both themes and record the values in this table.
