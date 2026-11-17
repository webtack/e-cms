<?php

namespace Webtack\Ecms\Theme;

use Illuminate\Foundation\Vite;
use Illuminate\Support\Facades\View;

/**
 * The active site theme: where its templates live, which template a page uses and how it renders.
 *
 * A theme is a directory `themes/<name>/{theme.json, hooks.php, assets/, views/}`. Themes are looked up in an
 * ordered list of roots: the application's `themes/` first, then the package's own `themes/`. A theme found
 * in an earlier root (one with a `theme.json`) shadows a theme of the same name in a later one, so an application
 * can override the packaged `default` completely. The active theme
 * (`config('theme.active')`, from `APP_THEME`) is always backed by `default`, so another theme holds only
 * the templates it overrides. Both are registered as the Blade namespace `theme` (`theme::layout`),
 * the active one first.
 *
 * {@see self::render()} takes a list of candidate templates, from the most specific to the most general.
 * The candidate decides first, then the theme: the first candidate that exists in the active theme or in
 * `default` wins. When none exists, a {@see ThemeException} lists every name tried. With `app.debug` on,
 * the HTML ends with a comment naming the candidates and the template used.
 *
 * The active theme is checked on the first {@see self::render()}, never at boot, so a broken `APP_THEME`
 * breaks only the public site, not the admin or the console.
 *
 * @example
 *   $themes = new ThemeManager([base_path('themes'), '/path/to/package/themes'], 'custom', 'default');
 *   $themes->directory('default'); // '/path/to/package/themes/default' when the application has no such theme
 *   $html = app(ThemeManager::class)->render(
 *       ['pages/page-landing--5', 'pages/page-landing', 'pages/page'],
 *       ['node' => $node],
 *       'page',
 *   );
 */
class ThemeManager
{
    /** Templates every theme (the active one or `default`) must provide; checked by `theme:check`. */
    public const REQUIRED_VIEWS = ['html', 'layout', 'home', 'errors/404', 'pages/page', 'content/list', 'content/details'];

    private ?ThemeHooks $hooks = null;

    private bool $verified = false;

    /** @var list<string> Theme roots, the highest priority first. */
    private readonly array $paths;

    /**
     * @param  string|list<string>  $paths  One themes directory or several, the highest priority first.
     */
    public function __construct(
        string|array $paths,
        private readonly string $active,
        private readonly string $fallback,
    ) {
        $this->paths = array_values(array_map(fn (string $path) => rtrim($path, '/'), (array) $paths));
    }

    /** Name of the active theme. */
    public function active(): string
    {
        return $this->active;
    }

    /** Name of the fallback theme (`default`). */
    public function fallback(): string
    {
        return $this->fallback;
    }

    /**
     * The directory of a theme: the first root that holds `<theme>/theme.json`; when no root has the manifest, the
     * first root that has the directory at all, and the first root when there is none (so the error names a path).
     *
     * @example app(ThemeManager::class)->directory('default'); // '/var/www/vendor/webtack/e-cms/themes/default'
     */
    public function directory(string $theme): string
    {
        return $this->locate($theme);
    }

    /**
     * The theme roots in lookup order.
     *
     * @return list<string>
     */
    public function roots(): array
    {
        return $this->paths;
    }

    /**
     * The root a theme was found in (see {@see self::directory()}), or null when no root has the theme directory.
     *
     * @example app(ThemeManager::class)->rootOf('default'); // '/var/www/vendor/webtack/e-cms/themes'
     */
    public function rootOf(string $theme): ?string
    {
        $directory = $this->directory($theme);

        return is_dir($directory) ? dirname($directory) : null;
    }

    private function locate(string $theme): string
    {
        foreach ($this->paths as $root) {
            if (is_file("{$root}/{$theme}/theme.json")) {
                return "{$root}/{$theme}";
            }
        }

        foreach ($this->paths as $root) {
            if (is_dir("{$root}/{$theme}")) {
                return "{$root}/{$theme}";
            }
        }

        return ($this->paths[0] ?? '').'/'.$theme;
    }

    /**
     * Template directories in lookup order: the active theme, then `default` (once if they are the same).
     *
     * @return list<string>
     *
     * @example app(ThemeManager::class)->viewPaths(); // ['.../themes/custom/views', '.../themes/default/views']
     */
    public function viewPaths(): array
    {
        return array_values(array_unique([
            $this->directory($this->active).'/views',
            $this->directory($this->fallback).'/views',
        ]));
    }

    /** Point the Blade namespace `theme` at the theme directories. Does not touch the file system. */
    public function registerViews(): void
    {
        View::replaceNamespace('theme', $this->viewPaths());
        View::flushFinderCache();
    }

    /**
     * Make sure the active theme and `default` exist (once).
     *
     * @throws ThemeException
     */
    public function verify(): void
    {
        if ($this->verified) {
            return;
        }

        foreach (array_unique([$this->active, $this->fallback]) as $theme) {
            $this->assertTheme($theme);
        }

        $this->verified = true;
    }

    /**
     * Whether a theme directory has a `theme.json` with valid JSON and a `name`.
     */
    public function isValidTheme(string $theme): bool
    {
        $manifest = $this->directory($theme).'/theme.json';

        if (! is_file($manifest)) {
            return false;
        }

        $data = json_decode((string) file_get_contents($manifest), true);

        return is_array($data) && is_string($data['name'] ?? null) && $data['name'] !== '';
    }

    /**
     * The hook registry of the loaded themes: `default` first, then the active one.
     */
    public function hooks(): ThemeHooks
    {
        return $this->hooks ??= new ThemeHooks(array_values(array_unique([
            $this->directory($this->fallback).'/hooks.php',
            $this->directory($this->active).'/hooks.php',
        ])));
    }

    /**
     * Render the first existing candidate template.
     *
     * @param  list<string>  $candidates  Template names without the namespace, e.g. `pages/page-about`, most specific first.
     * @param  array<string, mixed>  $data  Template variables.
     * @param  string|null  $hook  Dotted hook name for `suggest` and `preprocess`; the last candidate by default
     *                             (`pages/page` → `pages.page`).
     *
     * @throws ThemeException When the theme is broken or no candidate exists.
     *
     * @example
     *   app(ThemeManager::class)->render(['content/list'], ['type' => $type], 'content.list');
     */
    public function render(array $candidates, array $data = [], ?string $hook = null): string
    {
        $this->verify();

        $hook ??= str_replace('/', '.', (string) end($candidates));
        $tried = $this->hooks()->applySuggest($hook, array_values($candidates));

        foreach ($tried as $candidate) {
            $view = 'theme::'.str_replace('/', '.', $candidate);

            if (! View::exists($view)) {
                continue;
            }

            $html = View::make($view, $this->hooks()->applyPreprocess($hook, $data))->render();

            return config('app.debug') ? $html.$this->comment($tried, $candidate) : $html;
        }

        throw ThemeException::noTemplate($tried);
    }

    /**
     * Vite entries of the active theme, `vendor` before `app`.
     *
     * Under the theme dev server (`public/themes/<name>.hot` exists) the entries are the files present in the
     * theme's `assets/`; otherwise they are the entries of `public/themes/<name>/manifest.json`. A theme without
     * assets or without a built manifest gives an empty list, which is not an error.
     *
     * @return list<string> Paths relative to the project root, e.g. `['themes/x/assets/vendor.scss', 'themes/x/assets/app.js']`.
     *
     * @example app(ThemeManager::class)->assets(); // ['themes/default/assets/app.css']
     */
    public function assets(): array
    {
        $prefix = 'themes/'.$this->active.'/assets/';

        if (is_file($this->hotFile())) {
            $files = glob($this->directory($this->active).'/assets/{vendor,app}.{css,scss,js}', GLOB_BRACE) ?: [];
            $keys = array_map(fn (string $file) => $prefix.basename($file), $files);
        } else {
            $manifest = public_path('themes/'.$this->active.'/manifest.json');
            $entries = is_file($manifest) ? json_decode((string) file_get_contents($manifest), true) : null;

            if (! is_array($entries)) {
                return [];
            }

            $keys = array_filter(
                array_keys($entries),
                fn (string $key) => str_starts_with($key, $prefix) && ($entries[$key]['isEntry'] ?? false),
            );
        }

        // `vendor` first, then `app`; the same bundle keeps its stylesheet and script in key order.
        usort($keys, fn (string $a, string $b) => [str_starts_with(basename($a), 'vendor.') ? 0 : 1, $a] <=> [str_starts_with(basename($b), 'vendor.') ? 0 : 1, $b]);

        return array_values($keys);
    }

    /**
     * HTML tags (stylesheets and scripts) of the active theme's entries, empty when it has none.
     *
     * Uses its own {@see Vite} instance bound to the theme's build directory and hot file, so the theme dev
     * server never replaces the admin's `public/hot`.
     *
     * @example {!! app(ThemeManager::class)->assetTags() !!}
     */
    public function assetTags(): string
    {
        $assets = $this->assets();

        if ($assets === []) {
            return '';
        }

        return (string) (new Vite)
            ->useHotFile($this->hotFile())
            ->useBuildDirectory('themes/'.$this->active)($assets);
    }

    /**
     * URL of a theme static file (image, font) with a version that changes when the file changes.
     *
     * `$path` is relative to the theme's static directory (`images/logo1.png`). The copied file in
     * `public/app/<theme>/` is looked up in the active theme, then in `default`; the first hit gives
     * `/app/<theme>/<path>?v=<filemtime>`. A file found in neither gives `/app/<active>/<path>` without
     * `?v` (no error). A leading `/` is ignored; a `..` segment or an empty path counts as a missing file,
     * so nothing outside `public/app/` is read.
     *
     * @example
     *   app(ThemeManager::class)->assetUrl('images/logo1.png'); // '/app/dcculture/images/logo1.png?v=1731000000'
     *   app(ThemeManager::class)->assetUrl('images/missing.png'); // '/app/dcculture/images/missing.png'
     */
    public function assetUrl(string $path): string
    {
        $path = ltrim($path, '/');
        $unversioned = '/app/'.$this->active.'/'.$path;

        if ($path === '' || in_array('..', explode('/', str_replace('\\', '/', $path)), true)) {
            return $unversioned;
        }

        foreach (array_unique([$this->active, $this->fallback]) as $theme) {
            $file = public_path('app/'.$theme.'/'.$path);

            if (is_file($file)) {
                return '/app/'.$theme.'/'.$path.'?v='.filemtime($file);
            }
        }

        return $unversioned;
    }

    /** The hot file written by the dev server of the active theme. */
    private function hotFile(): string
    {
        return public_path('themes/'.$this->active.'.hot');
    }

    /** @throws ThemeException */
    private function assertTheme(string $theme): void
    {
        if (! is_dir($this->directory($theme)) || ! $this->isValidTheme($theme)) {
            throw ThemeException::missingTheme($theme, $this->directory($theme));
        }
    }

    /** @param  list<string>  $candidates */
    private function comment(array $candidates, string $used): string
    {
        $clean = fn (string $name) => str_replace(['<', '>'], '', $name);

        return "\n<!-- theme: candidates [".implode(', ', array_map($clean, $candidates)).'] ; used: '.$clean($used).' -->';
    }
}
