# Tasks

Verification for theme frontend (per project rules): no PHP or e2e tests; each task is checked with `npm run theme:build -- dcculture` (host) and a visual check in the browser at the widths named in the task. Run `docker compose exec app php artisan theme:check` after markup changes.

## 1. Container and base

- [x] 1.1 Make `container-site` fluid (16 px phone, 32 px `md`, 64 px `lg`, 120 px `xl`; `max-width: 1440px` kept) in `components/container.css`; switch `.news-layout` and `.site-footer__inner` from `w-[1200px]` to `container-site`; verify at 1440 px that the content is still 1200 px wide and centred
- [x] 1.2 In `theme.css` remove `min-w-[1440px]` from `body` and add `overflow-x: clip`; verify at 360 px that the page has no horizontal scrollbar (`document.documentElement.scrollWidth === innerWidth` in the console on `/`) and that the frame still renders on `/` and on a 404 page

## 2. Top bar, header and navigation

- [x] 2.1 Top bar (`topbar.css`): allow wrapping below `lg`, hide date, status text and separators below `md`, keep phone, accessibility link and social links; verify at 360, 768 and 1440 px
- [x] 2.2 Header (`site-header.css`): below `lg` the search field takes a full-width row under the brand; below `md` shrink logo and name sizes and hide the divider and tagline; verify at 360, 768 and 1440 px (1440 unchanged)
- [x] 2.3 Navigation markup and CSS: in `partials/main-nav.blade.php` add the "Меню" `<button>` (`aria-expanded`, `aria-controls`) and `id` on the list; in `main-nav.css` show the button below `lg`, hide the list and dropdowns only under `.main-nav[data-js]`, make the open panel a scrollable vertical list and the dropdown a one-column list (no stripe, no dividers) below `lg`; keep hover/focus dropdowns from `lg`; verify at 1440 px by hovering and tabbing through "Департамент" (unchanged) and at 375 px with scripts disabled (everything expanded)
- [x] 2.4 Create `themes/dcculture/assets/app.js` (menu toggle, accordion, Escape and outside-click close, `matchMedia` reset, `data-js`; plain ES module, English JSDoc with `@example`); run `npm run theme:build -- dcculture` on the host and verify at 375 px: the button toggles the panel and `aria-expanded`, "Департамент" expands without navigating, Escape closes, resizing to 1440 px restores the hover dropdowns
- [x] 2.5 Run `docker compose exec app php artisan theme:check` and confirm `dcculture` and `default` report no problems

## 3. Home: hero and about the city

- [x] 3.1 Hero (`hero.css`): fluid padding, title and lead sizes, `max-w-[720px]` lead; tie the photo-strip item width and loop distance to CSS variables (first check the literal `-1752px` against the real item advance); verify the strip loops without a jump at 375 and 1440 px and does not widen the page
- [x] 3.2 About the city (`about-city.css`): stack below `lg`; scale `.about-city__media` by aspect ratio with percent-positioned plate, image, ring and badge; step down the accent font; verify at 360, 768 and 1440 px (1440 unchanged, badge not cut off on phones)

## 4. Home: news, poster, banner, contests, map

- [x] 4.1 News and poster (`news.css`, `afisha.css`, `section-heading.css`): `.news-layout` and `.afisha-column` stack below `lg`, `.news-hero__body` width fluid, `.news-wide` stacks below `md`, `.news-compact-grid` one column below `md`, section heading may wrap; verify at 360, 768 and 1440 px (poster under news on tablet and phone)
- [x] 4.2 Tourist banner (`tourist-banner.css`): `min-h` with flow content and padding, stronger overlay on phones, full-width button with `max-w`, smaller title; verify at 360, 768 and 1440 px (text not overlapped or cut off)
- [x] 4.3 Contest band (`contest-band.css`): cards 1 → 2 → 3 columns (`md`, `lg`), title step-down; verify the column count at 375, 768 and 1440 px
- [x] 4.4 Map (`map-section.css`): below `lg` the address card is a normal block above a 320 px high map; the overlay is unchanged from `lg`; verify at 375, 768 and 1440 px

## 5. Footer and final check

- [x] 5.1 Footer (`site-footer.css`): remove the fixed column height; 1 → 2 → 3 columns (`md`, `lg`); allow long links and schedule rows to wrap; verify at 375, 768 and 1440 px
- [x] 5.2 Integration check: compare `/` at 1440 px with the approved design and with the previous build (no regression), walk the page at 360, 768 and 1024 px (no horizontal scroll, no overlaps), and check a `default`-rendered page and the 404 page inside the frame at 375 px
- [x] 5.3 Update `docs/theme-dcculture.md`: replace the "Desktop layout only" limitation with a "Responsive layout" section, fix the "no JavaScript" sentences (intro and CSS section) and describe `app.js`; the section covers (breakpoints, fluid container, burger menu and `app.js`, rule that new components state their narrow layout); verify the document matches the final CSS values and that links in it still resolve
