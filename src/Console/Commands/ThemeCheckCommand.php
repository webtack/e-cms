<?php

namespace Webtack\Ecms\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\View;
use Webtack\Ecms\Content\FieldTypeRegistry;
use Webtack\Ecms\Theme\ThemeException;
use Webtack\Ecms\Theme\ThemeHooks;
use Webtack\Ecms\Theme\ThemeManager;

/**
 * Checks the active theme: `theme.json` of the theme and of `default`, the required templates (in the
 * active theme or in `default`), that `default` has a `fields/field-{type}` template for every registered
 * field type (so a new type without a template is caught here), and that each `hooks.php` returns a function. Every problem is printed on
 * its own line, preceded by one line per theme naming the root it was found in (the application's `themes/` or the
 * package's); the exit code is 0 without problems and 1 with any.
 *
 * @example
 *   php artisan theme:check
 *   // Theme [custom]: found in /var/www/html/themes
 *   // Theme [default]: found in /var/www/html/vendor/webtack/e-cms/themes
 *   // Theme [custom]: OK
 */
#[Signature('theme:check')]
#[Description('Check that the active site theme is complete')]
class ThemeCheckCommand extends Command
{
    public function handle(ThemeManager $themes, FieldTypeRegistry $fieldTypes): int
    {
        $problems = [];

        foreach (array_unique([$themes->active(), $themes->fallback()]) as $theme) {
            if (! is_dir($themes->directory($theme))) {
                $problems[] = "Theme [{$theme}]: directory {$themes->directory($theme)} does not exist.";
            } elseif (! $themes->isValidTheme($theme)) {
                $problems[] = "Theme [{$theme}]: theme.json is missing or has no valid \"name\".";
            }
        }

        // Where each theme was found (the application's themes directory wins over the package's).
        foreach (array_unique([$themes->active(), $themes->fallback()]) as $theme) {
            if (($root = $themes->rootOf($theme)) !== null) {
                $this->line("Theme [{$theme}]: found in {$root}");
            }
        }

        foreach (ThemeManager::REQUIRED_VIEWS as $view) {
            if (! View::exists('theme::'.str_replace('/', '.', $view))) {
                $problems[] = "Template [{$view}] is missing in the theme [{$themes->active()}] and in [{$themes->fallback()}].";
            }
        }

        foreach ($fieldTypes->keys() as $type) {
            $template = $themes->directory($themes->fallback())."/views/fields/field-{$type}.blade.php";

            if (! is_file($template)) {
                $problems[] = "Template [fields/field-{$type}] for the field type [{$type}] is missing in the theme [{$themes->fallback()}].";
            }
        }

        foreach (array_unique([$themes->fallback(), $themes->active()]) as $theme) {
            $file = $themes->directory($theme).'/hooks.php';

            if (! is_file($file)) {
                continue;
            }

            try {
                (new ThemeHooks([$file]))->load();
            } catch (ThemeException $e) {
                $problems[] = "Theme [{$theme}]: ".$e->getMessage();
            }
        }

        if ($problems === []) {
            $this->info("Theme [{$themes->active()}]: OK");

            return self::SUCCESS;
        }

        foreach ($problems as $problem) {
            $this->error($problem);
        }

        return self::FAILURE;
    }
}
