# Theming guide

How to write an application theme for `webtack/e-cms`: where it lives, which files it needs, how templates are chosen, hooks, Blade directives, assets, building, and how to override the shipped `default` theme. This is the entry point; the reference pages are [site-themes.md](site-themes.md) (catalog, cascade, hooks, Vite), [theme-templates.md](theme-templates.md) (field, page, block and menu templates, directives) and [public-site.md](public-site.md) (routes and template data).

## What a theme is

A theme is a directory `themes/<name>/` in the host application. The templates are plain Blade, without Inertia; the admin panel never uses them.

```
themes/<name>/
├── theme.json        # {"name": "...", "version": "..."}  (required, valid JSON with a name)
├── hooks.php         # optional: a function that registers hooks
├── assets/           # app.{css,scss,js} and vendor.{css,scss,js} (built by Vite), fonts/, images/
└── views/            # Blade templates: html, layout, home, errors/404, pages/*, content/*, fields/*, blocks/*, menus/*
```

The package ships the theme `default`. It is always the last source of templates, so **your theme holds only what it overrides**: with one file `themes/mysite/views/pages/page.blade.php` every other template comes from `default`. The required templates (found in your theme or in `default`) are `html`, `layout`, `home`, `errors/404`, `pages/page`, `content/list` and `content/details`.

The active theme is set by `APP_THEME` in `.env` ([configuration.md](configuration.md#themephp)).

## A minimal theme

The smallest theme overrides one template and is activated with one variable.

1. Create `themes/mysite/theme.json`:

   ```json
   { "name": "My site", "version": "1.0.0" }
   ```

2. Override the page template, `themes/mysite/views/pages/page.blade.php`:

   ```blade
   @extends('theme::layout')

   @section('content')
       <article class="page">
           <h1>{{ $values['title'] ?? $node->slug }}</h1>
           @fields($values)
       </article>
   @endsection
   ```

   `@extends('theme::layout')` resolves through the cascade, so the layout of `default` is used. `@fields($values)` prints every field of the page through the field templates.

3. Activate it in `.env`:

   ```
   APP_THEME=mysite
   ```

4. Check it:

   ```bash
   php artisan theme:check
   ```

   It prints `Theme [mysite]: OK` (and the same for `default`) or one line per problem: a missing `theme.json`, a missing required template, a `hooks.php` that returns no function.

A theme without an `assets/` directory renders without stylesheets or scripts: the assets come from the **active** theme only, not from `default`. To style it, add assets and build them ([below](#building-the-assets)).

## Template cascade

Every public page asks for a list of template names from the specific to the general, for example `pages/page-landing--5`, `pages/page-landing`, `pages/page`. For each name the active theme is searched first, then `default`, and the first file found is used ("candidate → theme", as in Drupal 7). So a specific template of `default` beats a general one of your theme. In `APP_DEBUG` mode an HTML comment shows which candidates were tried and which was used.

The candidates of each page are in [public-site.md](public-site.md#template-candidates); the candidates of fields, blocks and menus are in [theme-templates.md](theme-templates.md). A few useful names:

| Template | Used for |
|----------|----------|
| `pages/page-{type}`, `pages/page-{type}--{id}` | A page of the type `{type}`, or one record of it (`{id}` is the numeric node id). |
| `{type}/list`, `{type}/details`, `{type}/details--{id}` | The list and the detail page of a content type; `content/list` and `content/details` are the general ones. |
| `{type}/preview`, `{type}/preview--{id}` | The card of a node in a list (`content/preview` is the general one). |
| `fields/field-{ftype}`, `fields/field--{key}` | The output of a field type or of one field (a type directory `{type}/field-…` outranks `fields/`). |
| `blocks/block-{type}`, `blocks/block-{type}--{slug}` | A block of a type, or one block. |

## Template variables and directives

Every public page gets `$lang`, `$site`, `$title`, `$meta`, `$assetTags`, `$languages`, `$alternates`, `$canonical`, `$breadcrumbs` and more ([public-site.md](public-site.md#layout-and-template-data)). The top of each template of `default` documents its variables.

The directives available in every theme ([theme-templates.md](theme-templates.md#blade-directives)):

| Directive | Output |
|-----------|--------|
| `@field($values, 'key')` | One field through its template. |
| `@fields($values)` / `@fields($values, ['a', 'b'])` | All the fields of the record, or the listed ones. |
| `@preview($item)` | The card of one node of a list. |
| `@block('block-footer')` | A block node by its full slug (with the `block-` prefix). |
| `@menu('main')` | A menu by its key. |

The helpers `theme_asset('images/logo.png')` (a versioned URL of a theme file), `image_style($url, 'thumb')` and `file_url($value)` are global. A theme that overrides `html` must print `<html lang="{{ $lang }}">` and the `canonical` / `alternate` links, otherwise it drops the SEO signals of a multilingual site ([site-themes.md](site-themes.md#languages-in-a-theme)).

## Hooks

`hooks.php` returns a function that receives the registry. The hooks of `default` load first, then yours.

```php
<?php

use Webtack\Ecms\Theme\ThemeHooks;

return function (ThemeHooks $hooks): void {
    // Change the variables of a template before it renders.
    $hooks->preprocess('field.youtube', function (array &$vars) {
        $vars['autoplay'] = false;
    });

    // Change the list of candidates before the template is chosen.
    $hooks->suggest('page', function (array &$candidates) {
        array_unshift($candidates, 'pages/page-special');
    });
};
```

A hook name is a dot path from general to specific (`field.youtube` runs `field`, then `field.youtube`). A `preprocess` callback may throw `HttpResponseException` to end the request, for example with a redirect. See [site-themes.md](site-themes.md#hooks).

## Building the assets

The theme is built separately from the admin panel. Without a build the page renders without the theme's stylesheet and scripts (no error). Entry points follow a convention: only `assets/app.{css,scss,js}` and `assets/vendor.{css,scss,js}` are built, in that order (`vendor` before `app`); everything else in `assets/` is a partial that you import. `assets/fonts/` and `assets/images/` are copied to `public/app/<name>/` and used through `theme_asset()`.

The host needs Node only for this step. The package ships the tooling:

1. Publish the Vite configuration and the runner (the host's `package.json` is never edited by the package):

   ```bash
   php artisan vendor:publish --tag=ecms-theme-tooling
   ```

   This copies `vite.theme.config.js` and `scripts/theme.mjs` to the host root.

2. Install the build dependencies and add the two scripts:

   ```bash
   npm install -D vite laravel-vite-plugin @tailwindcss/vite tailwindcss sass
   npm pkg set scripts.theme:dev="node scripts/theme.mjs dev" scripts.theme:build="node scripts/theme.mjs build"
   ```

   `@tailwindcss/vite` and `tailwindcss` are needed even for a theme that does not use Tailwind, because the shared configuration imports the plugin. `sass` is needed only for `.scss` files.

3. Build:

   ```bash
   npm run theme:build -- mysite      # one theme: public/themes/mysite/manifest.json
   npm run theme:build                # every theme that has an assets/ directory
   npm run theme:dev -- mysite        # the dev server with hot reload, port 5174 (THEME_DEV_PORT)
   ```

   `THEME=<name>` is how the runner passes the theme to Vite; set it yourself to run `npx vite -c vite.theme.config.js` directly.

A plain-CSS example, `themes/mysite/assets/app.css`:

```css
body { font-family: system-ui, sans-serif; margin: 2rem auto; max-width: 40rem; }
.page h1 { font-size: 2rem; }
```

After `npm run theme:build -- mysite` the layout prints the stylesheet through `$assetTags`. The templates inherited from `default` carry Tailwind utility classes, so a plain stylesheet like this one styles only your own markup; see [below](#using-tailwind-like-default) to reuse the utilities. The build output (`public/themes/`) and the copied static files (`public/app/`) are generated and belong in `.gitignore`; the theme directory is the source of truth. Details, including SCSS, static files and the dev server port: [site-themes.md](site-themes.md#theme-assets-vite).

### Using Tailwind like `default`

`default` builds Tailwind 4 with `@import 'tailwindcss' source(none)` plus explicit `@source` lines for the views. To reuse its utilities in your theme, copy `themes/default/assets/app.css` (published, see below) into `themes/mysite/assets/app.css` and keep the `@source` lines pointing at your views and at `../../default/views`.

## Creating a theme by hand from `default`

Publish the shipped theme as a model:

```bash
php artisan vendor:publish --tag=ecms-theme
```

This copies `themes/default` (`views/`, `assets/`, `hooks.php`, `theme.json`) into the host. Then create your theme as a **new directory** next to it:

1. `cp -r themes/default themes/mysite` and edit `theme.json` (`name`);
2. delete every template you do not want to change (the cascade falls back to `default` for the rest);
3. keep the `assets/` entries you need and build them as above;
4. set `APP_THEME=mysite`.

Do not edit `themes/default` itself ([below](#refreshing-the-published-default)).

## Refreshing the published `default`

A directory `themes/default` in the host **shadows** the package's theme completely. A copy published with `ecms-theme` that is not refreshed after a package update keeps serving the old templates, and the theme's own fixes and new templates never reach the site. After updating the package publish it again:

```bash
php artisan vendor:publish --tag=ecms-theme --force
php artisan vendor:publish --tag=ecms-assets --force
```

`--force` replaces the files, so **do not edit the published `default`**: your changes would be overwritten. Put them in your own theme, which overrides single templates and inherits the rest. If you only need `default` as a reference, delete `themes/default` from the host afterwards: the package's copy is used again, and the built assets come from `ecms-assets`.

## Overriding `default`

Two different things are called "overriding `default`":

- **An application theme overrides single templates** of `default` (the recommended way): create `themes/mysite/views/...` and set `APP_THEME=mysite`. Everything you do not provide is inherited.
- **A host theme named `default`** with a `theme.json` shadows the packaged theme entirely (`themes/default` is searched before the package's `themes/`). This is what `ecms-theme` creates. A theme found nowhere throws `ThemeException`, and a directory without `theme.json` shadows nothing.

The theme `default` stays the fallback of every theme: `theme.fallback` is required and is not meant to change.

## Checking a theme

```bash
php artisan theme:check
```

It validates `theme.json`, the required templates, that `default` has a `fields/field-{type}` template for each registered field type, and that every `hooks.php` returns a function; the exit code is `1` on any problem.

Cloning a type in the admin also copies its type-level templates to every theme in the host's `themes/` ([site-themes.md](site-themes.md#cloning-a-type)).
