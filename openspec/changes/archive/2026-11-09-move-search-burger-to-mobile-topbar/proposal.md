# Proposal

## Why

On phones and tablets the `dcculture` frame wastes space and hides the search: the burger button sits in a separate 60 px teal bar, the header search is hidden below 1024 px, and the top bar spends room on social icons that matter little on a small screen. Moving the search and the burger into the top bar, and opening both in modals, makes them reachable at once and removes the extra bar.

## What Changes

- Below 1024 px the top bar shows a search button and a burger button in place of the social-network icons; from 1024 px the social icons stay and the two buttons are not shown.
- The burger button leaves the main navigation bar. Below 1024 px it opens the main navigation in a full-screen modal (home link, items, accordion sub-links, close button).
- The search button opens the search form in a modal over a dimmed page, with focus in the input. The header no longer shows the search below 1024 px.
- Both modals close with their close button, Escape, or a tap on the dimmed area (search); the page behind does not scroll while a modal is open.
- Below 1024 px the menu and the search require the theme script; the no-script fallback of the expanded navigation panel is removed.

## Capabilities

### New Capabilities
None.

### Modified Capabilities
- `dcculture-theme`: the "Top bar and header content", "Main navigation", "Design styling and assets" and "Adaptive frame and home sections" requirements change (menu and search in modals opened from the top bar, social links from 1024 px only, no-script fallback for the mobile menu removed).

## Impact

- Theme `themes/dcculture`: `views/partials/topbar.blade.php`, `main-nav.blade.php`, `site-header.blade.php`, `search-field.blade.php`, `assets/components/{topbar,main-nav,site-header,social-links}.css`, `assets/app.js`.
- Docs: `docs/theme-dcculture.md` (frame, modals, script contract) is updated.
- No PHP, database, or other theme is affected. Per project rules no tests are added for theme frontend.
