# Design

## Context

`HandleInertiaRequests::share()` returns `flash` as `{ success, error }`, two lazy closures reading `$request->session()->get(...)`. `FlashToaster` (`Components/Admin/FlashToaster.tsx`) has two effects with the dependencies `[flash.success]` and `[flash.error]`. React skips an effect when its dependency is equal to the previous render, so an identical text in two consecutive responses (same page, `back()->with('success', …)`) gives no second toast. See proposal.md - Why. Described in `docs/admin-shell.md` ("Toasts", "Routes and shared props").

The pages that reload only part of the props (`only: ['fields', 'flash']` in ContentFields, Terms, Menu Items) must keep working: `flash` is requested as one group, so `id` arrives together with the messages.

## Goals / Non-Goals

**Goals:**
- A new flash message always produces a toast, regardless of its text.

**Non-Goals:**
- No change to the flash contract on the backend (`->with('success'|'error', …)`).
- No de-duplication of equal messages, no toast queue.

## Decisions

- **Server generates `flash.id` at read time.** `flash.id` is a lazy closure: when the session holds a `success` or `error` message it returns a fresh `(string) Str::uuid()`, otherwise `null`. Flash data lives for one request, so a fresh id per request that carries a message is a unique id per message. No change is needed where messages are flashed. Alternative: generate the id when flashing (a helper replacing `->with()`), rejected because it touches every controller and can be forgotten.
- **`FlashToaster` depends on `flash.id`.** One effect with `[flash.id]` shows `toast.success` and/or `toast.error` for the messages present in that payload (both can arrive together). A `null` id shows nothing. Alternative: keep two effects and add the id to each dependency list, rejected as duplicated; one effect is simpler.
- **Type.** `flash: { id: string | null; success: string | null; error: string | null }` in `resources/js/types/index.d.ts`.
- **Partial reloads.** `only: [..., 'flash']` already requests the whole `flash` group, so no page changes are needed. A partial reload without `flash` keeps the old props, so no toast is shown, as now.

## Risks / Trade-offs

- [A closure reading the session several times may return values inconsistent with each other] → all three closures read the same session within one request; the Feature test checks `id`, `success` and `error` together.
- [The React hooks lint rule may ask for `flash.success` and `flash.error` in the dependency list] → add a short comment explaining that the id intentionally identifies the message; verify with the project's lint/build.
- [React Strict Mode runs effects twice in development] → already the case today; the production build is unaffected.
