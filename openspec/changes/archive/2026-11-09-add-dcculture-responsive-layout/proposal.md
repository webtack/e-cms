# Proposal

## Why

The `dcculture` theme implements only the 1440 px desktop design: `body` has `min-width: 1440px`, the container has fixed 120 px side margins, and most blocks use fixed widths (`w-[1200px]`, `w-[400px]`, `w-[552px]`, `h-[241px]`, …). On a laptop, tablet or phone the page scrolls horizontally and is unusable. Most visitors of a municipal site come from phones, so the frame and the home page must adapt to any screen width before real data and the other pages are built on top of them.

## What Changes

- Remove the desktop lock (`min-width: 1440px` on `body`) and make the content container fluid: side margins shrink with the screen, the 1200 px content width on wide screens stays the same.
- Introduce one set of breakpoints for the whole theme (Tailwind defaults): below `lg` (1024 px) the layout is tablet/mobile, from `lg` it is the existing desktop layout, pixel-identical to the approved design at 1440 px.
- Top bar and site header: groups wrap or hide secondary items on narrow screens; the search field moves under the brand and takes the full width.
- Main navigation: below `lg` it becomes a burger panel driven by a small theme script `assets/app.js` (button with `aria-expanded`, accordion for sub-items, close on Esc / outside tap). Without the script the panel stays expanded (all links reachable). From `lg` the hover/focus CSS dropdowns stay as they are.
- Home page sections adapt: hero (heading size, lead width, photo-strip item width), "about the city" (stacked), news + poster (poster column moves under the news, news cards stack), tourist banner (height follows content, full-width button), contest cards (3 → 2 → 1 columns), map (address card above/over the map), footer (3 → 1 column, no fixed height).
- Document the breakpoints and the rules for new components in `docs/theme-dcculture.md`.

- The theme gets its first JavaScript entry (`assets/app.js`, built by the existing theme Vite config); the requirement "no JavaScript from the theme's own bundle" is dropped.

Not changing: colours, fonts, texts, markup order of sections, the data (still static), and the inherited `default` templates.

## Capabilities

### New Capabilities

None.

### Modified Capabilities

- `dcculture-theme`: the requirement "Wide-screen layout" (desktop only) is replaced by a responsive layout requirement; the requirement "Main navigation with dropdowns without JavaScript" is renamed to "Main navigation" and gains the below-desktop burger panel; "Design styling and assets" no longer forbids the theme's own JavaScript.

## Impact

- Code: `themes/dcculture/assets/theme.css`, `assets/components/*.css` (container, topbar, site-header, main-nav, hero, about-city, news, afisha, tourist-banner, contest-band, map-section, site-footer, section-heading); `themes/dcculture/assets/app.js` (new, burger menu), `assets/app.css` is unchanged in role, `themes/dcculture/views/partials/main-nav.blade.php` (burger button markup), small markup tweaks in header/topbar partials if needed. No PHP, no migrations, no new dependencies.
- Docs: `docs/theme-dcculture.md` is updated (the "Desktop layout only" limitation is replaced by a responsive layout section with breakpoints); `docs/README.md` needs no change.
- Tests: none. Per project rules, public-site theme frontend has no PHP Feature or e2e tests; verification is `php artisan theme:check`, the theme build and a visual check at 360, 768, 1024 and 1440 px.
