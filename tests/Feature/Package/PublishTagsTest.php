<?php

namespace Webtack\Ecms\Tests\Feature\Package;

use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\ServiceProvider;
use Webtack\Ecms\Tests\Concerns\OverridesHostFiles;
use Webtack\Ecms\Tests\TestCase;

/**
 * The publish tags of the package: `ecms-theme` (the reference theme, sources), `ecms-theme-tooling` (Vite config and
 * runner to build a theme in the host) and `ecms-assets` (the admin build, the built theme and, when it has any, the
 * static files of the theme).
 */
class PublishTagsTest extends TestCase
{
    use OverridesHostFiles;

    /** The static files of the theme `default` exist only when `dist/app/default` does. */
    private bool $createdStatic = false;

    protected function setUp(): void
    {
        // The registered publish groups live in static properties of the framework's ServiceProvider and survive an
        // application restart; the conditional `app/default` entry must be decided by this test's own boot.
        foreach (['publishes', 'publishGroups'] as $property) {
            (new \ReflectionProperty(ServiceProvider::class, $property))->setValue(null, []);
        }

        if (str_starts_with($this->name(), 'test_assets_with_static')) {
            $this->createStaticFile();
        }

        parent::setUp();

        $this->forgetHostPath('public/vendor/ecms');
        $this->forgetHostPath('public/app/default');
        (new Filesystem)->deleteDirectory(public_path('vendor/ecms'));
    }

    protected function tearDown(): void
    {
        if ($this->createdStatic) {
            (new Filesystem)->deleteDirectory($this->packagePath('dist/app'));
            $this->createdStatic = false;
        }

        parent::tearDown();
    }

    private function createStaticFile(): void
    {
        if (is_dir($this->packagePath('dist/app/default'))) {
            return;
        }

        (new Filesystem)->ensureDirectoryExists($this->packagePath('dist/app/default/fonts'));
        file_put_contents($this->packagePath('dist/app/default/fonts/test.txt'), 'static');
        $this->createdStatic = true;
    }

    /** @return list<string> Relative paths of the files below a directory, sorted. */
    private function tree(string $directory): array
    {
        $files = [];

        foreach ((new Filesystem)->allFiles($directory, true) as $file) {
            $files[] = $file->getRelativePathname();
        }

        sort($files);

        return $files;
    }

    public function test_the_reference_theme_is_published_as_a_complete_tree(): void
    {
        $this->assertDirectoryDoesNotExist(base_path('themes'));
        $this->forgetHostPath('themes');

        $this->assertSame(0, Artisan::call('vendor:publish', ['--tag' => 'ecms-theme']), Artisan::output());

        $this->assertSame($this->tree($this->packagePath('themes/default')), $this->tree(base_path('themes/default')));
        $this->assertFileExists(base_path('themes/default/theme.json'));
        $this->assertFileExists(base_path('themes/default/hooks.php'));
        $this->assertFileExists(base_path('themes/default/views/layout.blade.php'));
    }

    public function test_a_repeat_with_force_restores_an_edited_file_and_without_force_keeps_it(): void
    {
        $this->forgetHostPath('themes');
        Artisan::call('vendor:publish', ['--tag' => 'ecms-theme']);
        $layout = base_path('themes/default/views/layout.blade.php');
        $original = file_get_contents($layout);

        file_put_contents($layout, 'EDITED');
        Artisan::call('vendor:publish', ['--tag' => 'ecms-theme']);
        $this->assertSame('EDITED', file_get_contents($layout), 'An existing file is kept without --force');

        Artisan::call('vendor:publish', ['--tag' => 'ecms-theme', '--force' => true]);
        $this->assertSame($original, file_get_contents($layout));
    }

    public function test_the_theme_tooling_is_published(): void
    {
        $this->forgetHostPath('vite.theme.config.js');
        $this->forgetHostPath('scripts/theme.mjs');

        if (! is_dir(base_path('scripts'))) {
            $this->forgetHostPath('scripts');
        }

        $this->assertSame(0, Artisan::call('vendor:publish', ['--tag' => 'ecms-theme-tooling']), Artisan::output());

        $this->assertFileEquals($this->packagePath('vite.theme.config.js'), base_path('vite.theme.config.js'));
        $this->assertFileEquals($this->packagePath('scripts/theme.mjs'), base_path('scripts/theme.mjs'));
    }

    public function test_assets_without_static_files_publish_the_build_and_the_built_theme(): void
    {
        if (is_dir($this->packagePath('dist/app/default'))) {
            $this->markTestSkipped('The theme has static files now; test_assets_with_static_files covers it.');
        }

        $this->assertSame(0, Artisan::call('vendor:publish', ['--tag' => 'ecms-assets', '--force' => true]), Artisan::output());
        $this->assertStringNotContainsString("Can't locate path", Artisan::output());

        $this->assertSame($this->tree($this->packagePath('dist/build')), $this->tree(public_path('vendor/ecms/build')));
        $this->assertSame($this->tree($this->packagePath('dist/themes/default')), $this->tree(public_path('themes/default')));
        $this->assertDirectoryDoesNotExist(public_path('app/default'));
    }

    public function test_assets_with_static_files_publish_them(): void
    {
        $this->assertSame(0, Artisan::call('vendor:publish', ['--tag' => 'ecms-assets', '--force' => true]), Artisan::output());
        $this->assertStringNotContainsString("Can't locate path", Artisan::output());

        $this->assertSame($this->tree($this->packagePath('dist/app/default')), $this->tree(public_path('app/default')));
        $this->assertFileExists(public_path('vendor/ecms/build/manifest.json'));
    }
}
