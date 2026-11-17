# Design

## Context

See proposal.md for motivation. Today (`themes/dcculture`):

- `main-nav.blade.php` holds the burger `button.main-nav__toggle` inside `nav.main-nav`; below `lg` it is a 60 px teal bar with a collapsible list, and `assets/app.js` finds the button with `nav.querySelector`.
- The search field partial is included in the header with `max-lg:hidden`, so below `lg` there is no search.
- `topbar.blade.php` ends with `.social-links` (Facebook, YouTube) shown at every width.
- `main-nav.blade.php` has an uncommitted `sticky top-0` on the `nav`; this change does not depend on it.

## Goals / Non-Goals

**Goals:**
- Search and burger buttons in the top bar below `lg`, replacing the social links there; both open modals.
- One markup for desktop and mobile: no duplicated nav or search form.
- One breakpoint (`lg`, 1024 px), as the spec requires for all components.

**Non-Goals:**
- A working search (the form stays `action="#"`).
- Changing the desktop (≥ 1024 px) look, the dropdown behavior, or menu items.
- A no-script fallback for the mobile menu and search (the owner dropped it).

## Decisions

1. **Buttons live in the top bar**, in the right `.topbar__group`, shown only below `lg` (`lg:hidden`), icon-only with `aria-label` ("Меню", "Пошук"), sized like `.social-link--outline`. The burger has `aria-controls="main-nav"`, the search button `aria-controls="search-modal"`.
2. **Modals are CSS overlays on the existing elements, not `<dialog>`.** `<dialog>` cannot render inline on desktop while being the same element, and a duplicated nav/form would drift. Below `lg` the closed elements are `hidden`; when `.is-open` is set they become `fixed inset-0 z-50`. Alternative: `<dialog>` with two copies of the markup — rejected, double maintenance.
3. **Menu modal**: below `lg` `nav.main-nav.is-open` is a full-screen teal panel: a close button row at the top, then the list (scrolls inside, `overflow-y-auto`). Sub-links keep the existing accordion. The old `.main-nav__toggle` markup and CSS are removed.
4. **Search modal**: the form is wrapped in `div.search-modal#search-modal`. From `lg` it is a normal inline wrapper in the header; below `lg` it is hidden, and when `.is-open` it is a fixed full-screen dimmed layer (`bg-ink-dark/60`) with the form in a white card at the top plus a close button. A tap on the dimmed layer outside the card closes it.
5. **Script** (`assets/app.js`): `initMainNav` finds the burger by `aria-controls`, and a shared helper toggles `.is-open`, `aria-expanded`, a `modal-open` class on `<html>` (locks page scroll below `lg`), closes on Escape, and returns focus to the opener. A new `initSearchModal` (with JSDoc and `@example`) mirrors it and focuses the input on open. On crossing `lg` every modal state is dropped.
6. **Social links** get `max-lg:hidden` in the topbar only; the footer variant is untouched.
7. **Without the script** below `lg` the buttons do nothing, the nav and search stay hidden; the spec states this explicitly.

## Risks / Trade-offs

- [Menu and search unavailable below 1024 px if the script fails] → Accepted by the owner; the script is small and has no dependencies.
- [Two modals open at once] → Opening one closes the other.
- [Focus can leave the modal with Tab] → Not trapped in this change; both modals close with Escape and the close button. A focus trap is a possible follow-up.
