<?php

namespace Webtack\Ecms\Tests\Feature\Theme;

use Illuminate\Filesystem\Filesystem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Webtack\Ecms\Tests\Feature\Theme\Concerns\UsesFixtureThemes;
use Webtack\Ecms\Tests\TestCase;
use Webtack\Ecms\Theme\ThemeException;
use Webtack\Ecms\Theme\ThemeManager;

class ThemeManagerTest extends TestCase
{
    use RefreshDatabase;
    use UsesFixtureThemes;

    /** @var list<string> Built fixture theme directories under `public/themes/`. */
    private array $builtThemes = [];

    public function test_the_default_theme_is_active_without_configuration(): void
    {
        $this->assertSame('default', config('theme.active'));
        $this->assertSame('default', app(ThemeManager::class)->active());
        $this->assertSame([$this->packagePath('themes/default/views')], app(ThemeManager::class)->viewPaths());
    }

    public function test_another_theme_overrides_only_part_of_the_templates(): void
    {
        $this->makeTheme('default', ['layout' => 'DEFAULT-LAYOUT', 'home' => 'DEFAULT-HOME']);
        $this->makeTheme('custom', ['layout' => 'CUSTOM-LAYOUT']);
        $manager = $this->useTheme('custom');

        $this->assertSame('CUSTOM-LAYOUT', $manager->render(['layout']));
        $this->assertSame('DEFAULT-HOME', $manager->render(['home']));
    }

    public function test_a_missing_active_theme_throws_a_theme_exception(): void
    {
        $this->makeTheme('default', ['home' => 'HOME']);
        $manager = $this->useTheme('missing');

        $this->expectException(ThemeException::class);
        $this->expectExceptionMessage('The theme [missing] is missing');

        $manager->render(['home']);
    }

    public function test_a_theme_without_theme_json_is_rejected(): void
    {
        $this->makeTheme('default', ['home' => 'HOME']);
        $this->makeTheme('broken', ['home' => 'BROKEN'], manifest: false);
        $manager = $this->useTheme('broken');

        $this->expectException(ThemeException::class);

        $manager->render(['home']);
    }

    public function test_a_theme_with_an_invalid_theme_json_is_rejected(): void
    {
        $this->makeTheme('default', ['home' => 'HOME']);
        $directory = $this->makeTheme('broken', ['home' => 'BROKEN']);
        file_put_contents($directory.'/theme.json', '{not json');
        $manager = $this->useTheme('broken');

        $this->assertFalse($manager->isValidTheme('broken'));
        $this->assertTrue($manager->isValidTheme('default'));
    }

    public function test_the_admin_works_while_the_theme_is_broken(): void
    {
        config(['theme.active' => 'missing']);
        $this->app->forgetInstance(ThemeManager::class);
        $this->app->make(ThemeManager::class)->registerViews();

        $this->get('/login')->assertOk();
    }

    public function test_a_theme_without_assets_does_not_break_rendering(): void
    {
        $this->makeTheme('default', ['home' => 'HOME']);
        $this->makeTheme('bare');
        $manager = $this->useTheme('bare');

        $this->assertSame([], $manager->assets());
        $this->assertSame('HOME', $manager->render(['home']));
    }

    public function test_the_built_assets_of_the_default_theme_are_listed(): void
    {
        $manifest = public_path('themes/default/manifest.json');

        if (! is_file($manifest) || ! str_contains((string) file_get_contents($manifest), 'themes/default/assets/app.css')) {
            $this->markTestSkipped('Run `npm run theme:build` first: the theme has no manifest.');
        }

        $this->assertContains('themes/default/assets/app.css', app(ThemeManager::class)->assets());
    }

    public function test_vendor_entries_come_before_app_entries(): void
    {
        $name = $this->makeBuiltTheme([
            'themes/%s/assets/app.js' => ['file' => 'assets/app-1.js', 'isEntry' => true],
            'themes/%s/assets/app.scss' => ['file' => 'assets/app-1.css', 'isEntry' => true],
            'themes/%s/assets/vendor.js' => ['file' => 'assets/vendor-1.js', 'isEntry' => true],
            'themes/%s/assets/vendor.scss' => ['file' => 'assets/vendor-1.css', 'isEntry' => true],
            'themes/%s/assets/_partial.scss' => ['file' => 'assets/partial.css'],
        ]);

        $this->assertSame([
            "themes/{$name}/assets/vendor.js",
            "themes/{$name}/assets/vendor.scss",
            "themes/{$name}/assets/app.js",
            "themes/{$name}/assets/app.scss",
        ], $this->useTheme($name)->assets());

        $tags = $this->useTheme($name)->assetTags();

        $this->assertLessThan(strpos($tags, 'app-1.css'), strpos($tags, 'vendor-1.css'));
        $this->assertLessThan(strpos($tags, 'app-1.js'), strpos($tags, 'vendor-1.js'));
    }

    public function test_a_theme_without_a_built_manifest_has_no_assets(): void
    {
        $this->makeTheme('default', ['home' => 'HOME']);
        $this->makeTheme('unbuilt');
        $manager = $this->useTheme('unbuilt');

        $this->assertSame([], $manager->assets());
        $this->assertSame('', $manager->assetTags());
    }

    /**
     * Write a Vite manifest under `public/themes/<unique name>/` (removed in tearDown) and return the theme name.
     *
     * @param  array<string, array<string, mixed>>  $entries  Manifest key with a `%s` for the theme name => entry.
     */
    private function makeBuiltTheme(array $entries): string
    {
        $name = 'fixture-'.uniqid();
        $directory = public_path('themes/'.$name);
        $this->builtThemes[] = $directory;

        $manifest = [];

        foreach ($entries as $key => $entry) {
            $manifest[sprintf($key, $name)] = $entry + ['src' => sprintf($key, $name)];
        }

        (new Filesystem)->ensureDirectoryExists($directory);
        file_put_contents($directory.'/manifest.json', json_encode($manifest));

        $this->makeTheme('default', ['home' => 'HOME']);
        $this->makeTheme($name);

        return $name;
    }

    protected function tearDown(): void
    {
        foreach ($this->builtThemes as $directory) {
            (new Filesystem)->deleteDirectory($directory);
        }

        parent::tearDown();
    }
}
