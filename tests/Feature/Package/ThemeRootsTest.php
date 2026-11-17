<?php

namespace Webtack\Ecms\Tests\Feature\Package;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Webtack\Ecms\Tests\Feature\Theme\Concerns\UsesFixtureThemes;
use Webtack\Ecms\Tests\TestCase;
use Webtack\Ecms\Theme\ThemeException;
use Webtack\Ecms\Theme\ThemeManager;

/**
 * Themes are looked up in the application's `themes/` first and in the package's `themes/` second.
 */
class ThemeRootsTest extends TestCase
{
    use RefreshDatabase;
    use UsesFixtureThemes;

    public function test_the_package_provides_default_when_the_application_has_no_themes_directory(): void
    {
        $this->assertDirectoryDoesNotExist(config('theme.path'));

        $manager = $this->app->make(ThemeManager::class);

        $this->assertSame($this->packagePath('themes'), $manager->roots()[1]);
        $this->assertSame($this->packagePath('themes/default'), $manager->directory('default'));
        $this->assertSame([$this->packagePath('themes/default/views')], $manager->viewPaths());
    }

    public function test_an_application_theme_overrides_single_templates_and_the_rest_comes_from_the_package(): void
    {
        $directory = $this->makeTheme('custom', ['layout' => 'CUSTOM-LAYOUT']);
        $manager = $this->useTheme('custom');

        $this->assertSame($directory, $manager->directory('custom'));
        $this->assertSame([$directory.'/views', $this->packagePath('themes/default/views')], $manager->viewPaths());
        // `home` is not overridden: the package's template is used.
        $finder = $this->app['view']->getFinder();
        $this->assertSame($this->packagePath('themes/default/views/home.blade.php'), $finder->find('theme::home'));
        $this->assertSame($directory.'/views/layout.blade.php', $finder->find('theme::layout'));
    }

    public function test_an_application_theme_named_default_shadows_the_packaged_one(): void
    {
        $directory = $this->makeTheme('default', ['home' => 'APP-HOME', 'layout' => '@yield("content")']);
        $manager = $this->useTheme('default');

        $this->assertSame($directory, $manager->directory('default'));
        $this->assertSame([$directory.'/views'], array_values(array_unique($manager->viewPaths())));
    }

    public function test_a_directory_without_a_manifest_does_not_shadow_the_packaged_theme(): void
    {
        $this->makeTheme('default', manifest: false);
        $manager = $this->useTheme('default');

        $this->assertSame($this->packagePath('themes/default'), $manager->directory('default'));
    }

    public function test_a_theme_found_nowhere_is_an_error_on_the_first_public_render(): void
    {
        $this->makeTheme('other');
        $manager = $this->useTheme('missing-theme');

        $this->expectException(ThemeException::class);

        $manager->verify();
    }
}
