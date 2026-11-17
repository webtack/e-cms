# Design

## Context

Toasts are Sonner (`sonner@2.0.8`). One `<Toaster />` is mounted in `Layouts/AdminLayout.tsx` through the wrapper `resources/js/Components/ui/sonner.tsx`; every toast (`FlashToaster` and the direct `toast.*` calls in the ContentFields, Terms and Menu Items pages) goes through it. The wrapper sets neutral colors from `--popover` and only per-status icons. The form Save buttons live in the sticky bottom action bar (`FormActionBar`), which the default `bottom-right` position covers. Theme tokens `--success`, `--warning`, `--info`, `--destructive` already exist in both themes in `resources/css`, and are exposed as Tailwind colors (`--color-success` and so on). No e2e spec depends on toast position or classes (they wait by text). See proposal.md - Why.

Described in `docs/admin-shell.md` (section "Toasts").

## Goals / Non-Goals

**Goals:**
- Reconfigure the single shared `Toaster` wrapper so that all toasts get the new position, status colors, size, close button and durations.

**Non-Goals:**
- No change to how toasts are triggered or to the flash contract.
- No new tokens unless a status turns out to have no usable one.
- No toast queue, stacking or action-button features.

## Decisions

- **Position `top-right`, no `offset`.** Set on the `Toaster` (`position="top-right"`). The owner accepted that toasts overlap the header. Alternatives: `top-center` (rejected by the owner), `offset` below the header (rejected, not wanted).
- **Status colors through `toastOptions.classNames` (`success`, `error`, `warning`, `info`), not `richColors`.** Each class sets border and background tint from the matching token (for example `border-success/40 bg-success/10 text-foreground`) plus the icon color. Tokens already differ between light and dark, so one class set serves both themes. `richColors` is rejected: its palette ignores the theme tokens and looks off in dark mode.
- **Size.** Width through Sonner's `--width` CSS variable (about 440px), larger text and padding through the base `toast` class, icons from `size-4` to `size-5`. On narrow screens Sonner stretches toasts to the viewport itself; this is verified manually.
- **Close button.** `closeButton` on the `Toaster`, so a toast overlapping the header can be dismissed at once.
- **Durations.** Sonner has one `duration` per toast, with no per-type default in `toastOptions`. To keep one place of configuration, durations are set where toasts are created: `FlashToaster` passes `duration` for `success` (4000) and `error` (8000), and the direct `toast.error` / `toast.success` calls use the same values. A small shared constant avoids repeated literals. Alternative: a thin wrapper around `toast` used everywhere (rejected as heavier than needed for two values; revisit if more call sites appear).
- **Existing `cn-toast` class** stays as the base class on `toast`.

## Risks / Trade-offs

- [Toast hides header controls (user menu, language switcher) while visible] → accepted by the owner; the close button and the short success duration limit it.
- [Tinted backgrounds lose contrast in one theme] → check both themes visually; adjust the opacity per theme if needed.
- [Direct `toast.*` calls miss the duration constants] → grep all `toast.` usages in `resources/js` and update each.
