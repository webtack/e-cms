<?php

namespace Webtack\Ecms\Tests\Feature\Package;

use Webtack\Ecms\Content\ThemeTemplateCopier;
use Webtack\Ecms\Content\ThemeTemplateCopyException;
use Webtack\Ecms\Tests\Feature\Theme\Concerns\UsesFixtureThemes;
use Webtack\Ecms\Tests\TestCase;

/**
 * Cloning a type copies its templates inside the themes of the application only (the package's own `themes/` is
 * read-only), and a host that has no `themes/` directory at all is not an error.
 */
class ThemeTemplateCopierPackageTest extends TestCase
{
    use UsesFixtureThemes;

    public function test_a_host_without_a_themes_directory_has_nothing_to_copy(): void
    {
        $this->assertDirectoryDoesNotExist(config('theme.path'));

        $this->assertSame([], app(ThemeTemplateCopier::class)->copy('article', 'news'));
    }

    public function test_the_templates_of_an_application_theme_are_copied(): void
    {
        $theme = $this->makeTheme('custom', ['article/index' => 'LIST', 'blocks/block-article' => 'BLOCK', 'other/index' => 'UNRELATED']);
        $this->useTheme('custom');

        $created = app(ThemeTemplateCopier::class)->copy('article', 'news');

        $this->assertEqualsCanonicalizing(["{$theme}/views/news", "{$theme}/views/blocks/block-news.blade.php"], $created);
        $this->assertSame('LIST', file_get_contents("{$theme}/views/news/index.blade.php"));
        $this->assertSame('BLOCK', file_get_contents("{$theme}/views/blocks/block-news.blade.php"));
        $this->assertDirectoryDoesNotExist("{$theme}/views/other-copy");
        // The package's theme is never written to.
        $this->assertDirectoryDoesNotExist($this->packagePath('themes/default/views/news'));
    }

    public function test_an_existing_target_stops_the_copy_and_leaves_nothing_behind(): void
    {
        $theme = $this->makeTheme('custom', ['article/index' => 'LIST', 'news/index' => 'EXISTING']);
        $this->useTheme('custom');

        try {
            app(ThemeTemplateCopier::class)->copy('article', 'news');
            $this->fail('The copy must be refused');
        } catch (ThemeTemplateCopyException) {
            $this->assertSame('EXISTING', file_get_contents("{$theme}/views/news/index.blade.php"));
        }
    }
}
