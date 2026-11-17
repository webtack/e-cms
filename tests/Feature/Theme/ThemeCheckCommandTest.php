<?php

namespace Webtack\Ecms\Tests\Feature\Theme;

use Webtack\Ecms\Content\FieldTypeRegistry;
use Webtack\Ecms\Tests\Feature\Theme\Concerns\UsesFixtureThemes;
use Webtack\Ecms\Tests\TestCase;
use Webtack\Ecms\Theme\ThemeManager;

class ThemeCheckCommandTest extends TestCase
{
    use UsesFixtureThemes;

    /** @return array<string, string> */
    private function fullViews(): array
    {
        $fieldViews = array_map(fn (string $type) => "fields/field-{$type}", app(FieldTypeRegistry::class)->keys());

        return array_fill_keys([...ThemeManager::REQUIRED_VIEWS, ...$fieldViews], 'x');
    }

    public function test_a_complete_theme_passes(): void
    {
        $this->makeTheme('default', $this->fullViews());
        $this->useTheme('default');

        $this->artisan('theme:check')->expectsOutputToContain('Theme [default]: OK')->assertExitCode(0);
    }

    public function test_the_real_default_theme_is_complete(): void
    {
        $this->artisan('theme:check')->assertExitCode(0);
    }

    public function test_a_partial_theme_is_complete_thanks_to_default(): void
    {
        $this->makeTheme('default', $this->fullViews());
        $this->makeTheme('custom', ['layout' => 'x']);
        $this->useTheme('custom');

        $this->artisan('theme:check')->assertExitCode(0);
    }

    public function test_a_missing_layout_is_reported(): void
    {
        $views = $this->fullViews();
        unset($views['layout']);
        $this->makeTheme('default', $views);
        $this->useTheme('default');

        $this->artisan('theme:check')
            ->expectsOutputToContain('Template [layout] is missing')
            ->assertExitCode(1);
    }

    public function test_a_hooks_file_without_a_function_is_reported(): void
    {
        $this->makeTheme('default', $this->fullViews());
        $this->makeTheme('custom', [], '<?php return 42;');
        $this->useTheme('custom');

        $this->artisan('theme:check')
            ->expectsOutputToContain('hooks.php')
            ->assertExitCode(1);
    }

    public function test_a_missing_theme_is_reported(): void
    {
        $this->makeTheme('default', $this->fullViews());
        $this->useTheme('missing');

        $this->artisan('theme:check')
            ->expectsOutputToContain('Theme [missing]: directory')
            ->assertExitCode(1);
    }

    public function test_a_missing_template_of_a_field_type_in_default_is_reported(): void
    {
        $views = $this->fullViews();
        unset($views['fields/field-youtube']);
        $this->makeTheme('default', $views);
        $this->useTheme('default');

        $this->artisan('theme:check')
            ->expectsOutputToContain('Template [fields/field-youtube] for the field type [youtube] is missing in the theme [default]')
            ->assertExitCode(1);
    }

    public function test_a_field_type_template_in_another_theme_does_not_replace_the_one_in_default(): void
    {
        $views = $this->fullViews();
        unset($views['fields/field-youtube']);
        $this->makeTheme('default', $views);
        $this->makeTheme('custom', ['fields/field-youtube' => 'x']);
        $this->useTheme('custom');

        $this->artisan('theme:check')->assertExitCode(1);
    }
}
