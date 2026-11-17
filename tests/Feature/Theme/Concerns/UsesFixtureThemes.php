<?php

namespace Webtack\Ecms\Tests\Feature\Theme\Concerns;

use Illuminate\Filesystem\Filesystem;
use Webtack\Ecms\Theme\ThemeManager;

/**
 * Builds themes in a temporary directory so tests never touch the real `themes/`.
 *
 * @example
 *   $this->makeTheme('default', ['layout' => 'LAYOUT']);
 *   $this->makeTheme('custom', ['layout' => 'CUSTOM']);
 *   $this->useTheme('custom');
 */
trait UsesFixtureThemes
{
    private ?string $themesPath = null;

    protected function tearDown(): void
    {
        if ($this->themesPath !== null) {
            (new Filesystem)->deleteDirectory($this->themesPath);
            $this->themesPath = null;
        }

        parent::tearDown();
    }

    /**
     * Create a theme with `theme.json` and the given views.
     *
     * @param  array<string, string>  $views  Template name (`errors/404`) => Blade source.
     * @param  string|null  $hooks  Source of `hooks.php` (starting with `<?php`), none by default.
     */
    protected function makeTheme(string $name, array $views = [], ?string $hooks = null, bool $manifest = true): string
    {
        $this->themesPath ??= storage_path('framework/testing/themes-'.uniqid());
        $directory = $this->themesPath.'/'.$name;
        $files = new Filesystem;

        $files->ensureDirectoryExists($directory.'/views');

        if ($manifest) {
            $files->put($directory.'/theme.json', json_encode(['name' => ucfirst($name), 'version' => '1.0.0']));
        }

        foreach ($views as $view => $source) {
            $files->ensureDirectoryExists(dirname($directory.'/views/'.$view));
            $files->put($directory.'/views/'.$view.'.blade.php', $source);
        }

        if ($hooks !== null) {
            $files->put($directory.'/hooks.php', $hooks);
        }

        return $directory;
    }

    /**
     * Make a fixture theme active, with `default` as the fallback, as the real application does.
     */
    protected function useTheme(string $name): ThemeManager
    {
        // Debug off by default, so rendered output has no candidate comment; tests of the comment turn it on.
        config(['theme.path' => $this->themesPath, 'theme.active' => $name, 'theme.fallback' => 'default', 'app.debug' => false]);
        $this->app->forgetInstance(ThemeManager::class);
        // The renderers keep the manager they were built with.
        $this->app->forgetScopedInstances();

        $manager = $this->app->make(ThemeManager::class);
        $manager->registerViews();

        return $manager;
    }
}
