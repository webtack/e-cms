## 1. Toaster configuration

- [x] 1.1 In `resources/js/Components/ui/sonner.tsx` set `position="top-right"` (no `offset`), `closeButton`, width about 440px through `--width`, larger text, padding and `size-5` icons. Keep the `cn-toast` base class. Add English JSDoc to the exported `Toaster` describing the position, styling and close button.
- [x] 1.2 Add status classes `success`, `error`, `warning`, `info` to `toastOptions.classNames` using the tokens `--success`, `--destructive`, `--warning`, `--info` (border, tinted background, icon color), without `richColors`. Check both light and dark themes visually; adjust tints per theme if contrast is poor.

## 2. Durations

- [x] 2.1 Add shared duration constants (success 4000 ms, error 8000 ms) with JSDoc, and pass them in `Components/Admin/FlashToaster.tsx`.
- [x] 2.2 Grep `toast.` usages in `resources/js` and apply the same durations in `Pages/Admin/ContentFields/Index.tsx`, `Pages/Admin/Taxonomy/Terms/Index.tsx`, `Pages/Admin/Menus/Items/Index.tsx`.

## 3. Documentation

- [x] 3.1 Update the "Toasts" section of `docs/admin-shell.md` (position, status colors, size, close button, durations, where the durations are set). Keep the English text and the existing cross-links valid.

## 4. Verification

- [x] 4.1 Build on the host with `npm run build`, trigger a success toast (save an edit form) and an error toast, and check position, colors in both themes, size, close button, durations, and a narrow viewport.
- [x] 4.2 Run `npm run test:e2e -- e2e/admin-content-fields.spec.js` (the spec waits for a success toast). Other specs are skipped: the change is visual and no spec depends on toast position or classes. PHP tests are skipped: no PHP changes.
