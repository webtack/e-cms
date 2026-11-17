<?php

namespace Webtack\Ecms\Theme;

/**
 * The registry of theme hooks. A theme's `hooks.php` returns a function that receives this object
 * and registers callbacks:
 *
 * - `preprocess(name, fn)` changes the variables of a template before it renders;
 * - `suggest(name, fn)` changes the list of candidate templates before one is chosen.
 *
 * A callback takes its argument by reference (`array &$vars` / `array &$candidates`). A hook name is a
 * dotted path from general to specific: calling `field.youtube` runs the hooks `field`, then
 * `field.youtube`. Within one name, callbacks run in registration order, and the files are loaded
 * `default` first, then the active theme. Files are loaded once, on first use.
 *
 * @example hooks.php
 *   use Webtack\Ecms\Theme\ThemeHooks;
 *
 *   return function (ThemeHooks $hooks): void {
 *       $hooks->preprocess('field.youtube', function (array &$vars) { $vars['autoplay'] = false; });
 *       $hooks->suggest('page', function (array &$candidates) { array_unshift($candidates, 'pages/page-special'); });
 *   };
 * @example
 *   $hooks = new ThemeHooks([base_path('themes/default/hooks.php')]);
 *   $vars = $hooks->applyPreprocess('field.youtube', ['id' => 'abc']);
 */
class ThemeHooks
{
    /** @var array<string, list<callable>> */
    private array $preprocess = [];

    /** @var array<string, list<callable>> */
    private array $suggest = [];

    private bool $loaded = false;

    /**
     * @param  list<string>  $files  Paths of `hooks.php` files in loading order; missing files are skipped.
     */
    public function __construct(private readonly array $files = []) {}

    /** Register a callback that changes the template variables: `function (array &$vars): void`. */
    public function preprocess(string $name, callable $callback): void
    {
        $this->preprocess[$name][] = $callback;
    }

    /** Register a callback that changes the candidate list: `function (array &$candidates): void`. */
    public function suggest(string $name, callable $callback): void
    {
        $this->suggest[$name][] = $callback;
    }

    /**
     * Load the hook files (once).
     *
     * @throws ThemeException When a file does not return a function.
     */
    public function load(): void
    {
        if ($this->loaded) {
            return;
        }

        $this->loaded = true;

        foreach ($this->files as $file) {
            if (! is_file($file)) {
                continue;
            }

            $register = (static fn (string $path) => require $path)($file);

            if (! $register instanceof \Closure) {
                throw ThemeException::invalidHooks($file);
            }

            $register($this);
        }
    }

    /**
     * Run the `preprocess` hooks of a name, general first.
     *
     * @param  array<string, mixed>  $vars
     * @return array<string, mixed>
     *
     * @throws ThemeException
     */
    public function applyPreprocess(string $name, array $vars): array
    {
        $this->load();

        foreach (self::chain($name) as $hook) {
            foreach ($this->preprocess[$hook] ?? [] as $callback) {
                $callback($vars);
            }
        }

        return $vars;
    }

    /**
     * Run the `suggest` hooks of a name, general first.
     *
     * @param  list<string>  $candidates
     * @return list<string>
     *
     * @throws ThemeException
     */
    public function applySuggest(string $name, array $candidates): array
    {
        $this->load();

        foreach (self::chain($name) as $hook) {
            foreach ($this->suggest[$hook] ?? [] as $callback) {
                $callback($candidates);
            }
        }

        return array_values($candidates);
    }

    /**
     * The hook names a call runs, from general to specific.
     *
     * @return list<string>
     *
     * @example ThemeHooks::chain('field.youtube'); // ['field', 'field.youtube']
     */
    public static function chain(string $name): array
    {
        $parts = explode('.', $name);
        $chain = [];

        foreach ($parts as $i => $_) {
            $chain[] = implode('.', array_slice($parts, 0, $i + 1));
        }

        return $chain;
    }
}
