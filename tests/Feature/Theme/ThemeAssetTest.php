<?php

namespace Webtack\Ecms\Tests\Feature\Theme;

use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Blade;
use Webtack\Ecms\Tests\Feature\Theme\Concerns\UsesFixtureThemes;
use Webtack\Ecms\Tests\TestCase;

/**
 * `theme_asset()`: versioned URLs of theme static files with the active → `default` cascade.
 * Files are created under `public/app/` in uniquely named theme directories and removed afterwards.
 */
class ThemeAssetTest extends TestCase
{
    use UsesFixtureThemes;

    /** @var list<string> Files created by the test. */
    private array $created = [];

    private string $custom;

    protected function setUp(): void
    {
        parent::setUp();

        $suffix = uniqid();
        $this->custom = 'assettest-'.$suffix;
        $this->makeTheme('default');
        $this->makeTheme($this->custom);
        $this->useTheme($this->custom);
    }

    protected function tearDown(): void
    {
        // Remove only what the test created; `public/app/default` may hold real theme files.
        foreach ($this->created as $file) {
            @unlink($file);

            for ($dir = dirname($file); $dir !== public_path('app') && str_starts_with($dir, public_path('app')); $dir = dirname($dir)) {
                if (! @rmdir($dir)) {
                    break;
                }
            }
        }

        parent::tearDown();
    }

    private function makeAsset(string $theme, string $path, ?int $mtime = null): string
    {
        $file = public_path('app/'.$theme.'/'.$path);
        $this->created[] = $file;

        (new Filesystem)->ensureDirectoryExists(dirname($file));
        file_put_contents($file, 'x');
        touch($file, $mtime ?? time());

        return $file;
    }

    public function test_a_file_of_the_active_theme_gets_its_mtime(): void
    {
        $this->makeAsset($this->custom, 'images/logo.png', 1700000000);

        $this->assertSame('/app/'.$this->custom.'/images/logo.png?v=1700000000', theme_asset('images/logo.png'));
    }

    public function test_a_file_only_in_default_falls_back_to_it(): void
    {
        $this->makeAsset('default', 'images/'.$this->custom.'.png', 1700000001);

        $this->assertSame('/app/default/images/'.$this->custom.'.png?v=1700000001', theme_asset('images/'.$this->custom.'.png'));
    }

    public function test_a_changed_mtime_changes_the_version(): void
    {
        $file = $this->makeAsset($this->custom, 'images/logo.png', 1700000000);
        $first = theme_asset('images/logo.png');

        clearstatcache();
        touch($file, 1700000500);

        $this->assertNotSame($first, theme_asset('images/logo.png'));
        $this->assertStringEndsWith('?v=1700000500', theme_asset('images/logo.png'));
    }

    public function test_a_missing_file_gives_the_url_without_a_version(): void
    {
        $this->assertSame('/app/'.$this->custom.'/images/missing.png', theme_asset('images/missing.png'));
    }

    public function test_a_path_with_dots_is_treated_as_missing(): void
    {
        $this->assertSame('/app/'.$this->custom.'/../../.env', theme_asset('../../.env'));
        $this->assertSame('/app/'.$this->custom.'/images/../../.env', theme_asset('images/../../.env'));
    }

    public function test_a_leading_slash_is_ignored(): void
    {
        $this->makeAsset($this->custom, 'images/logo.png', 1700000000);

        $this->assertSame(theme_asset('images/logo.png'), theme_asset('/images/logo.png'));
    }

    public function test_it_renders_in_a_blade_template_and_is_escaped(): void
    {
        $this->makeAsset($this->custom, 'images/logo.png', 1700000000);

        $this->assertSame(
            '<img src="/app/'.$this->custom.'/images/logo.png?v=1700000000">',
            Blade::render('<img src="{{ theme_asset(\'images/logo.png\') }}">'),
        );

        // `{{ }}` escapes the URL, so a quote in the path cannot break out of the attribute.
        $this->assertStringNotContainsString('"x', Blade::render('<img src="{{ theme_asset(\'a"x.png\') }}">'));
        $this->assertStringContainsString('&quot;', Blade::render('{{ theme_asset(\'a"x.png\') }}'));
    }
}
