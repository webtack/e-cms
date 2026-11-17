# Proposal

## Why

`FlashToaster` shows a toast in an effect that depends on the message text (`flash.success`, `flash.error`). When the server flashes the same text twice in a row (for example "Saved" after two saves of the same edit form, where `back()` keeps the user on the page), the prop does not change between the two responses, the effect does not run, and the second toast is silently skipped.

## What Changes

- The server adds a unique `flash.id` to the shared `flash` prop. The id is present only when a `success` or `error` message is flashed for the current request and is `null` otherwise. A new id is generated for every request that carries a flash message.
- `FlashToaster` triggers on `flash.id` instead of the message text, so every new flash message produces a toast, even with the same text as the previous one.
- The type of the shared `flash` prop gains `id: string | null`.
- Flash still lives for one request: navigating to another page does not repeat a toast.

No change to how the backend flashes messages (`->with('success', …)`) or to toast styling and durations.

## Capabilities

### New Capabilities

None.

### Modified Capabilities

- `admin-shell`: the requirement "Notifications about the result of actions" gains a scenario: the same message flashed in two consecutive responses shows two toasts.

## Impact

- Code: `app/Http/Middleware/HandleInertiaRequests.php` (the `flash` shared prop), `resources/js/types/index.d.ts`, `resources/js/Components/Admin/FlashToaster.tsx`.
- Docs: `docs/admin-shell.md` (sections "Toasts" and "Routes and shared props") and `docs/admin-content-fields.md` stays valid (it only requires `flash` in `only`).
- Specs: delta for `openspec/specs/admin-shell/spec.md`.
- Tests: PHP Feature test `tests/Feature/Admin/DashboardTest.php` (`test_flash_messages_are_shared_once`) is extended for `flash.id`. e2e: a check that two saves with the same text show two toasts, in the spec that covers an edit form with a success toast.
