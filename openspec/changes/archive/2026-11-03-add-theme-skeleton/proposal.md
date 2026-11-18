# Proposal

## Why

The public site does not exist yet: `/` renders `welcome.blade.php`, there are no other public routes, while the data (types, pages, blocks, menus) is already ready and protected from collisions of the first URL segment (the change `reserve-type-keys-and-public-paths`). A skeleton is needed, on which the templates will lie: a theme system in the style of Drupal 7 (`themes/<name>/views`, a cascade of candidates «from specific to general», the hooks `preprocess`/`suggest`), public routes and the shell of the page. Without the skeleton it is impossible to write the field templates (the next change) nor to check that a theme is complete.

The decisions on the themes were agreed with the owner in the explore mode; this change is the second of three: keys and paths (done) → **the theme skeleton** → the templates `default` and `present()` in `FieldType`.

## What Changes

- A theme is a directory `themes/<name>/` with `theme.json`, `hooks.php`, `assets/`, `views/` (Blade, without Inertia). The active theme is taken from `APP_THEME` (`.env`, `config/theme.php`); the theme `default` is mandatory and always lies at the end of the list of paths, so another theme contains only what is overridden.
- `ThemeManager` registers the Blade namespace `theme` with the paths `[active/views, default/views]` and resolves the template by a **list of candidates**: first the candidate, then the theme (as in D7), through `view()->first($candidates)`. If there is no candidate: an exception `ThemeException` with the list of the attempted names. Under `app.debug` the HTML gets comments with the list of candidates and the chosen file.
- Hooks: `themes/<t>/hooks.php` returns a function with the registry `preprocess('field'|'field.youtube'|…)` (from general to specific; `themes/default/hooks.php` is always loaded, then the active theme) and `suggest(...)` for changing the list of candidates. The class `ThemeHooks`.
- Public routes: `/` → `home`; `/{slug}` → a record of the kind `page`; `/{type.slug}` → the list of the type `content`; `/{type.slug}/{node.slug}` → the details of a record. The routes are registered last, do not overlap the service paths (`content.reserved_paths`), read only visible records (`onlyVisible: true`), an unknown path gives `errors/404` with the status 404. The blocks are not routed.
- The shell: `html` (the document), `layout` (the header/menu/footer; `home` and the other pages are rendered inside), `home`, `errors/404`. For the routes of a page, a list and the details in `default` there are minimal stub templates (`pages/page`, `content/list`, `content/details`), which the next change will replace with the full ones.
- Vite: the entries from `themes/*/assets` (css/js) are built together with the admin; the templates connect the active theme by the directive `@vite` from its directory.
- The command `php artisan theme:check`: checks the active theme (there is `theme.json`, `default`, the required templates, `hooks.php` returns a function) and outputs the errors; it becomes more useful as the number of templates grows.
- `.env.example` gets `APP_THEME=default`.
- **BREAKING** (only for the code and the tests): `/` no longer gives `welcome.blade.php` (it is removed), the tests/e2e that depended on `welcome` move to `home`.

Out of scope (deliberately): the languages of the site (a prefix in the path), the regions of blocks, switching the theme from the admin, the field templates and `present()` in `FieldType` (change 3), the full templates `pages/blocks/content/menus` (change 3).

## Capabilities

### New Capabilities

- `site-themes`: the structure of the theme, `ThemeManager`, the cascade of candidates and the exception `ThemeException`, the hooks `preprocess`/`suggest`, the resources of the theme through Vite, the command `theme:check`, the configuration `APP_THEME`.
- `public-site`: the public routes (`/`, `/{slug}`, `/{type.slug}`, `/{type.slug}/{node.slug}`), the order and the priority with respect to the service paths, the visibility of records, 404, the shell `html`/`layout`/`home`, the variables that the templates receive.

### Modified Capabilities

None: the behaviour of the existing capabilities (the content, the admin) does not change; `content.reserved_paths` is only read.

## Impact

- Code: the new `app/Theme/` (`ThemeManager`, `ThemeHooks`, `ThemeException`, `CandidateResolver`, the command `ThemeCheckCommand`), `app/Http/Controllers/Public/*` (home, page, content list/details, the fallback 404), `app/Providers/AppServiceProvider.php` (registration of the manager and of the Blade directives), `routes/web.php` (the public routes at the end; remove the route `welcome`), `config/theme.php`, `.env.example`, `vite.config.js`, the directory `themes/default/{theme.json,hooks.php,assets,views}`, removal of `resources/views/welcome.blade.php`.
- Tests: `tests/Feature/Theme` (the cascade of candidates, `ThemeException`, hooks, `theme:check`, a fixture theme in a tmp directory), `tests/Feature/Public` (routes, visibility, 404, the priority of the service paths); `tests/Feature/Content/ReservedPathsGuardTest.php` remains in force.
- e2e: a new spec `e2e/public-site.spec.js` (the home page, a page, the list/details, 404); rows in `docs/e2e-testing.md`.
- Dependencies: no new npm/composer packages. No migrations (only reading the existing data); a full run of the PHP tests is needed because of the change of `config`, the provider and `routes/web.php`.
- Docs: new `docs/site-themes.md` (the structure, the cascade, the hooks, `theme:check`, `APP_THEME`) and `docs/public-site.md` (the routes, visibility, the shell); update `docs/README.md` (the index), `docs/content-model.md` (a reference to the public routes next to the slug), `docs/e2e-testing.md` (the table «область → спек»), `docs/admin-shell.md` (the Vite build from `themes/*/assets`).
