# Proposal

## Why

Admin toasts (Sonner) use the default `bottom-right` position, which covers the Save buttons of the edit forms. They are also visually neutral (only the icon differs by status) and small, so a result of a save is easy to miss.

## What Changes

- Move toasts to the `top-right` corner. No offset is applied; a toast may overlap the header.
- Add a colored look per status (success, error, warning, info) via custom `toastOptions.classNames` built on the theme tokens `--success`, `--destructive`, `--warning`, `--info`. Sonner `richColors` is not used. Light and dark themes are both covered.
- Make toasts larger: width about 440px, larger text, larger icons, more padding.
- Add a close button to every toast.
- Set per-status durations: success 4 s, error 8 s (other statuses keep the Sonner default).

No change to how toasts are triggered (`FlashToaster`, direct `toast.*` calls) or to the flash contract.

## Capabilities

### New Capabilities

None.

### Modified Capabilities

- `admin-shell`: the requirement "Notifications about the result of actions" gains position, status styling, close button and duration rules.

## Impact

- Code: `resources/js/Components/ui/sonner.tsx` (the only place that configures the shared `<Toaster />`); possibly `resources/css` if a status needs an extra token or class.
- Docs: `docs/admin-shell.md` (section "Toasts") is updated.
- Specs: delta for `openspec/specs/admin-shell/spec.md`.
- Tests: no e2e selectors depend on toast position or classes; verify with `e2e/admin-content-fields.spec.js` (waits for a success toast). No PHP changes.
