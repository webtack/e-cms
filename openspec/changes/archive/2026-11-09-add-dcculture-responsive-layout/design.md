# Design

## Context

See proposal.md for motivation and specs/dcculture-theme/spec.md for the requirements. Observed state of `themes/dcculture/` (read from the code):

- Desktop lock: `theme.css` sets `body { min-width: 1440px }`; `container-site` (`components/container.css`) is `max-width: 1440px; padding-inline: 120px`.
- Fixed widths outside the container: `.news-layout` (`w-[1200px]`), `.site-footer__inner` (`w-[1200px]`), `.afisha-column` (`w-[400px]`), `.about-city__media` (`552×322` with absolutely positioned plate, image, ring and badge), `.news-hero__body` (`w-[700px]`), `.news-wide__image` (`w-[375px]`), `.hero__lead` (`w-[720px]`), `.tourist-banner__button` (`w-[352px]`), `.search-field` (`w-[300px]`), `.address-card` (`left-[120px]`), `.hero__intro` (`px-[120px]`), `.site-footer__cols` (`h-[241px]`).
- Fixed heights: `.tourist-banner` (`h-[420px]`, content absolutely positioned), `.map-section` (`h-[280px]`, address card absolutely positioned over a 1920 px wide image).
- Navigation: `.main-nav__list` is a 60 px row of 8 items; dropdown panels are `absolute inset-x-0` and open on `:hover` / `:focus-within`. The nav is one `<ul>`; items have no JavaScript.
- Photo strip: CSS keyframes `photo-strip-scroll` shift the track by a literal `-1752px`; item width is `w-[300px]` with `-mr-12` and a `-ml-[309px]` start offset.
- The shared `html` template (inherited from `default`) already has `<meta name="viewport" content="width=device-width, initial-scale=1">`.
- Tailwind 4 with default breakpoints (`sm` 640, `md` 768, `lg` 1024, `xl` 1280); the project rules forbid tests for theme frontend.
- The theme has no JavaScript yet. `vite.theme.config.js` already turns an existing `themes/<name>/assets/app.js` into an entry and the inherited `html` template already connects the theme's built entries, so adding the script needs no build or PHP change (`docs/site-themes.md`, "Theme assets (Vite)"). The earlier "no JavaScript" wording came from the static design source, not from an owner decision.

## Goals / Non-Goals

**Goals:**
- The approved design stays pixel-identical from `lg` (1024 px) up; below it the page reads well on tablets and phones down to 360 px.
- One breakpoint system and a few shared patterns (fluid container, stacking rules) so later pages reuse them.
- The menu is a progressive enhancement: the page stays navigable if the script does not run.

**Non-Goals:**
- A separate mobile design from the designer: the narrow layouts are derived from the desktop design (same colours, type, order); sizes are tuned by eye and by the owner's review.
- Restyling inherited `default` templates (rich text, fields, pagination).
- Container queries, new fonts, new images, new npm packages, JS libraries (the script is plain and dependency-free).

## Decisions

1. **Desktop-first overrides with Tailwind's default breakpoints.** Existing component CSS stays the desktop base; narrower rules are added with `max-lg:` / `max-md:` variants inside the same `@apply` blocks (or short `@media` blocks where several properties change). `lg` (1024 px) is the switch between desktop and the rest; `md` (768 px) splits tablet from phone. No custom breakpoints.
   *Alternative:* rewrite every component mobile-first (`base`, `md:`, `lg:`). Rejected: it touches every desktop value and risks changing the approved 1440 px look; the desktop-first override keeps desktop code intact.

2. **Fluid container.** `container-site` becomes `padding-inline: 16px` on phones, `32px` from `md`, `64px` from `lg`, `120px` from `xl` (1280 px). With `max-width: 1440px` this keeps the 1200 px content width at 1440 px. The fixed `w-[1200px]` blocks (`.news-layout`, `.site-footer__inner`) switch to `container-site` (they are the same width and centring), so every section shares one container rule. `body { min-width }` is removed and `overflow-x: clip` is set on `body` only as a guard against decorative overflow (the hero/contest decor circles, the map image), not to hide layout bugs.

3. **Navigation: burger panel driven by a small `assets/app.js`.** The nav partial gets a `<button class="main-nav__toggle" type="button" aria-expanded="false" aria-controls="main-nav-list">Меню</button>` (shown below `lg`; the list gets `id="main-nav-list"`). `app.js` (plain ES module, no dependencies, JSDoc in English):
   - toggles `.is-open` on `.main-nav` and syncs `aria-expanded` on the button;
   - below `lg`, a click on a top-level link of an item with a dropdown prevents navigation (`href="#"` today) and toggles `.is-open` on that item with `aria-expanded` on the link, as an accordion (one open at a time);
   - closes the panel on `Escape` (focus returns to the button) and on a click outside `.main-nav`;
   - uses `matchMedia('(min-width: 1024px)')` to drop all open states when the screen grows to desktop, so the hover/focus CSS dropdowns are not affected;
   - sets `data-js` on `.main-nav` when it runs.
   CSS: from `lg` the button is hidden and the hover/`:focus-within` dropdowns work as now, with no JavaScript. Below `lg`, `.main-nav[data-js]:not(.is-open) .main-nav__list` is hidden and `.main-nav[data-js] .dropdown` is hidden until its item is `.is-open`; the dropdown is then a one-column list (no stripe, no column dividers). Without the script (no `data-js`) the panel and all dropdowns are shown expanded, so every link stays reachable. Panel: `max-height: calc(100vh - header)`, `overflow-y: auto`.
   *Why JS:* hover is unreliable on touch screens, iOS Safari does not focus links on tap, and `aria-expanded`, Escape and outside-tap closing cannot be done in CSS.
   *Alternatives:* checkbox hack without JS (rejected: no `aria-expanded`, no Esc/outside close, no accordion); `<details>/<summary>` (changes the desktop markup and hover behaviour).

4. **Top bar and header.** Below `md` the top bar keeps the phone and the accessibility link and hides the date, the status text and the separators (the status dot text is secondary); social links stay. Below `lg` the groups may wrap (`flex-wrap`, smaller gap). The header: below `lg` the search field leaves the row (`order`, `basis-full`, `ml-0`, `w-full`) and sits under the brand; below `md` the logo shrinks (`size-14`), the vertical divider and the tagline are hidden, and the name font sizes step down (`text-lg`/`text-base`).

5. **Hero.** `.hero__intro` padding becomes the container's fluid padding; `.hero__title` steps `text-3xl` → `text-4xl`; `.hero__lead` becomes `w-full max-w-[720px]` with `text-base` on phones. Photo strip: the item width and the loop distance are tied to CSS variables on `.photo-strip` (`--strip-item` and `--strip-step`, where the step is the real advance of one item including its negative margin; the keyframe uses `translateX(calc(var(--strip-step) * -6))` for the 6 unique items) so the strip can use narrower items on phones (`--strip-item: 200px`) without a visible jump. The existing literal `-1752px` is checked against the real advance first (the item is 300 px with `-mr-12` and a 4 px border); if the original value is inconsistent, the variable form fixes it for all widths.

6. **"About the city".** `.about-city__inner` stacks (`flex-col`, `gap-10`) below `lg`. `.about-city__media` is designed with absolute pixel offsets, so below `lg` it is scaled as a unit: `width: 100%; max-width: 552px; aspect-ratio: 552 / 322` with the plate, image, ring and badge positioned in percentages; the badge shrinks on phones (`size-24`) and is moved inward so it is not cut by the screen edge (it sits at `-top-9 -left-9`, which overflows by 36 px). The accent word steps from `text-[44px]` to `text-4xl`.

7. **News and poster.** `.news-layout` becomes a column below `lg` (`flex-col`, `gap-12`); `.afisha-column` is `w-full` (the poster image keeps `object-cover`, with a bounded height such as `max-md:h-[360px]`). `.news-hero` keeps its photo, its body takes `w-full` (`max-w` removed), the title steps down on phones; `.news-wide` stacks below `md` (image on top: `flex-col`, `w-full h-[200px]`); `.news-compact-grid` is one column below `md`. `.section-heading__text` loses `whitespace-nowrap` on phones and steps to `text-2xl`.

8. **Tourist banner, contests, map.** The banner keeps its photo as an `absolute` background but the content becomes the flow content that sets the height: `h-[420px]` → `min-h-[420px]` and the content is `relative` with vertical padding below `lg`; the overlay gradient becomes more opaque across the full width on phones (the text sits over the whole photo); the button is `w-full max-w-[352px]`; the title steps to `text-3xl`. Contest cards: `grid-cols-1` → `md:grid-cols-2` → `lg:grid-cols-3`; the section title steps to `text-3xl`. Map: below `lg` the section is `h-auto`: the address card becomes a normal block above the map (static, full container width, no absolute position) and the map iframe gets a fixed `h-[320px]`; from `lg` the current overlay is unchanged.

9. **Footer.** `.site-footer__cols` loses `h-[241px]`, is one column below `md`, two columns at `md`, three from `lg`; the schedule rows and long link texts are allowed to wrap. `.site-footer__inner` uses `container-site`.

10. **Docs.** `docs/theme-dcculture.md` replaces the "Desktop layout only" paragraph with a "Responsive layout" section: breakpoints, the container rule, the burger menu and its `app.js`, and the rule that every new component states its narrow layout, and corrects the "no JavaScript" sentences in its intro and CSS sections.

## Risks / Trade-offs

- [Derived mobile layout may differ from what the designer would draw] → the owner reviews screenshots at 360/768/1024 before the change is archived; values are isolated in component files and easy to tune.
- [Desktop regressions at 1440 px while editing shared component rules] → all narrow rules are scoped with `max-lg:` / `max-md:`; the final check compares 1440 px with the design and the previous build.
- [The theme gets its first JS file; a script error or a blocked bundle would leave the panel collapsed] → the collapsed state applies only after `app.js` sets `data-js`, so without the script everything is visible.
- [A menu from the database can be long or deeper than the design's two levels] → the panel scrolls (`max-height`, `overflow-y: auto`); deeper structures belong to the change that wires the `main` menu.
- [Percent-positioned "about the city" media looks slightly different from the pixel design when scaled] → it is identical at its design size (≥ 552 px wide) because it uses the same aspect ratio and the same proportions.
- [Photo-strip loop distance computed from variables could show a jump if the item advance is wrong] → verified visually over one full loop at 375 and 1440 px.
- [Decorative circles and the 1920 px map image overflow narrow screens] → they are already `overflow-hidden` inside their sections; `overflow-x: clip` on `body` is the last guard.

## Migration Plan

Theme CSS, markup and one new JS file; no PHP, data or schema change. Build with `npm run theme:build -- dcculture` (host) and reload. Rollback is reverting the commit; there is no data or schema change.
