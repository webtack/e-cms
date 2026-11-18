# Design

## Context

The motivation and the scope: see `proposal.md`; the behaviour: `specs/site-themes/spec.md`, `specs/public-site/spec.md`. The current state:

- Publicly there is only `Route::get('/', fn () => view('welcome'))`; `resources/views/` contains `app.blade.php` (the Inertia shell of the admin) and `welcome.blade.php`. There is no directory `themes/`, and `APP_THEME` is absent from `.env.example`.
- The global group `web` has `EnsureUserIsActive`, `SetLocale`, `HandleInertiaRequests`; the last one shares only lazy data, so on Blade routes it costs nothing.
- The data for a page: `ContentNode::findBySlug($slug, onlyVisible: true)`, `ContentNodeQuery::paginate($type, NodeQueryParams(onlyVisible: true))`, `ContentValueReader::load()`; the site settings (`SiteSettings`: the name, the logos, the contacts), the menus (`MenuTree::tree($key)`).
- There are no collisions of the first URL segment between the service paths, the slugs of the pages and the slugs of the types `content` by construction (`PublicPaths`, `content.reserved_paths`). The priority during routing is left to this change.
- The language of the content on the public side: `default` (the languages of the site are out of scope).
- Vite: the entries `resources/css/app.css` and `resources/js/app.tsx`; the build on the host (`npm run build`).

## Goals / Non-Goals

**Goals:**
- One mechanism of choosing a template for the whole site (a page, a block, a field, a content): a list of candidates in the order «specific → general», first the candidate, then the theme.
- The theme `default` as the guaranteed basis; a failure of the choice gives a clear exception, not an empty page.
- The hooks change the template variables and the list of candidates without depending on the chosen view (unlike View Composers).
- Public routes that do not conflict with the service ones and need no new migrations.

**Non-Goals:**
- Field templates, `present()`, the full `pages/blocks/content/menus`: change 3 (here only stubs, so that the routes work).
- The languages of the site, the regions of blocks, switching the theme in the admin, the render cache.
- The menu in `layout`: the rendering of the menu belongs to change 3 (`menus/`); here `layout` has only the site name/logo/contacts.

## Decisions

### 1. The directory of the theme and the config

`themes/<name>/{theme.json, hooks.php, assets/, views/}`. `config/theme.php`: `active` (from `APP_THEME`, `default`), `path` (`base_path('themes')`), `fallback` (`default`, does not change). `theme.json` contains only `name` and `version`; unknown keys are ignored, only an existing valid JSON with `name` is required.

*Alternative:* the theme as a Composer package. Rejected: a simple copy of the directory is needed, without an additional environment.

### 2. `ThemeManager` and the Blade namespace

`ThemeManager` (a singleton) knows `active()`, `fallback()`, `viewPaths()` = `[active/views, default/views]` (only one element when the active theme is `default`). During `boot` the provider calls `View::addNamespace('theme', $manager->viewPaths())`. Therefore `theme::layout` and `@include('theme::fields.field')` automatically give the first found file in the order of the paths, and `@extends`/`@include` between the templates of the theme work without our own code.

The check of the active theme (the directory exists, there is `theme.json`) is performed at the first access to `ThemeManager` of a public request, not in `boot`, so that the admin and the console commands do not fall because of a broken `APP_THEME`.

*Alternative:* `config('view.paths')` with a change of the order. Rejected: it mixes the admin and the public templates, and does not give a separate namespace.

### 3. The list of candidates and the resolution: the order «candidate → theme»

`ThemeManager::render(array $candidates, array $data)`:
1. the candidates pass the `suggest` hooks (decision 4);
2. for each candidate and **each** path of the theme (`active`, then `default`) `view()->exists("theme::$candidate")` is checked, i.e. the Blade finder already gives priority to the active theme;
3. the first existing one is executed through `view()->first(...)`; an empty match gives a `ThemeException` with the whole list.

In fact this is `view()->first(array_map(fn ($c) => "theme::$c", $candidates), $data)`: the namespace already brings «first the theme, then default» inside each candidate, and `first()` provides the order «the candidate is more important than the theme», as in D7. `ThemeException` is built from the `InvalidArgumentException` of `first()` and returns the list of names in the order of the enumeration.

Under `app.debug` a comment `<!-- theme: candidates [...] ; used: ... -->` is added to the rendered HTML. It is added in `render()`, not in the templates, so the theme cannot forget it. Candidate names with dots are converted into paths (`pages/page` ↔ `pages.page`) in one place.

*Alternative:* a custom `ViewFinder`. Rejected: Blade already gives the namespace and `first()`; extra code for the same guarantees.

### 4. Hooks: `ThemeHooks`

`themes/default/hooks.php` and `themes/<active>/hooks.php` return `function (ThemeHooks $hooks): void { ... }`. The registry contains `preprocess(string $name, callable $fn)` and `suggest(string $name, callable $fn)`. A call `preprocess('field.youtube', $vars)`: the hooks `field`, then `field.youtube` (by the prefixes of the dotted name), in the order of registration (first `default`, then the active theme); each receives `array &$vars`. `suggest` receives `array &$candidates` and works in the same way. The hook files are loaded once at the first call and cached in the singleton.

The hooks are deliberately not View Composers: a composer is bound to the name of the view, while the candidate becomes known only after the resolution; the hooks work before it and can change the choice itself.

*Alternative:* Laravel events. Rejected: there is no convenient ordering «general → specific» and the order «default → active».

### 5. Public routes

At the end of `routes/web.php`, after the admin and the service routes, **one** pair of fallback routes is registered:

- `GET /` → `HomeController`;
- `GET /{first}` → `PublicNodeController@show`;
- `GET /{first}/{second}` → `PublicNodeController@details`;
- the rest (three or more segments, other HTTP methods on public paths) → `Route::fallback` with the 404 of the theme.

Since the service routes are declared earlier, they win by the segments in the order of registration (Laravel gives the first match). The priority decided here: **a service path → a page/type → 404**. There are no conflicts between a page and a type (guaranteed by `PublicPaths`); for determinism the controller looks first for a page, then for a type `content`.

`{first}` is limited by the regular expression `ContentNode::SLUG_PATTERN` without anchors (`[a-z0-9]+(-[a-z0-9]+)*`), so paths like `/favicon.ico` do not get into the controller and quickly get the 404 of the site through the fallback. The reserved paths are additionally cut off in the controller in case of a row in the DB that bypassed the validation (the scenario `login`).

Visibility: `ContentNode::findBySlug($slug, onlyVisible: true)` plus a check of `kind = page` (a query of `ContentType::kind` of the node); the list of a type: `ContentNodeQuery::paginate($type, new NodeQueryParams(onlyVisible: true, ...))`; the details: a node by slug within `content_type = $type->key` and `visible()`.

*Alternative:* `Route::fallback` as the only entry, with a manual parsing of the path. Rejected: we lose the named routes and `Route::getRoutes()` for the guard test (`ReservedPathsGuardTest` catches only the routes with the first segment without a parameter; our `{first}` does not get into it, which is correct).

### 6. The shell and the data of the templates

Templates: `html` (the document, `<title>`, `@vite` of the theme), `layout` (`@extends('theme::html')`, the header/footer, `@yield('content')`), `home`, `errors/404`, the stubs `pages/page`, `content/list`, `content/details`. The controller calls `ThemeManager::render()`, passing the shared data through one assembling method (`PublicData`): `$site` (`SiteSettings`), `$theme` (the name), `$title` (of the document), `$node`/`$type`/`$paginator` where applicable, the values of the node from `ContentValueReader::load()` in `$values`. The heading: `{title of the record} — {name of the site}`; without a record — the name of the site.

`welcome.blade.php` is removed. The candidates of a page are formed already by the full cascade (`{t}/page`, `pages/page-{t}--{id}`, `pages/page-{t}`, `pages/page`; for the details of a content `{t}/details--{id}`, `{t}/details`, `content/details`; for the list `{t}/list`, `content/list`), so change 3 only adds the templates, without changing the controllers. The identifier in the file name: for a page and the details of a content — the numeric `id` of the record.

### 7. Vite

`vite.config.js` adds to `input` all files `themes/*/assets/{css,js}/*.{css,js}` (a glob in the config). In the templates `html` the active theme is connected by `@vite(['themes/'.$theme.'/assets/css/app.css', ...])` only when such a file is in the manifest (`ThemeManager::assets()` checks the presence of the files before the output). The `dev` mode works without changes.

### 8. `theme:check`

The command `App\Console\Commands\ThemeCheckCommand` checks: `theme.json`, `themes/default`, the presence of each required template through `view()->exists`, the correctness of `hooks.php` (it is loaded into an isolated `ThemeHooks`). It outputs the errors as a list and returns `Command::FAILURE` if there are any.

## Risks / Trade-offs

- [The fallback route `/{first}` catches the paths that will later become service ones] → the guard test `ReservedPathsGuardTest` remains; the new route is registered **before** the public block, so it wins.
- [`view()->first()` enumerates the candidates on the file system] → the Blade finder caches the found paths in the process; the number of candidates is ≤ 8 per field, to be measured in change 3.
- [Hooks with side effects break the rendering] → the exceptions of the hooks are not caught, but `ThemeException` indicates the file; the debug comment shows the candidates.
- [A broken `APP_THEME` takes the whole site down] → the admin does not depend on the theme (the check is only on the public request); `theme:check` shows the reason.
- [The stubs `pages/page`, `content/list`, `content/details` remain minimal and will look raw] → closed by change 3.
- [The public `GET /` disappears from `welcome`] → update the tests and e2e that relied on it.

## Migration Plan

1. Add `config/theme.php`, `APP_THEME=default` in `.env.example` (and the local `.env`).
2. Create `themes/default` with a minimal set of templates, remove `welcome.blade.php`.
3. Register the services and the public routes; build `npm run build`.
4. Rollback: return the route `welcome` and remove the public block of routes; there are no data and no migrations.

## Open Questions

- The lifetime of `ThemeManager` under Octane (if it ever appears): the hooks are cached in the singleton, so for a long-living process they must be reset between requests; Octane is not used now.
