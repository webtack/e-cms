<?php

namespace Webtack\Ecms\Tests\Feature\Theme;

use Webtack\Ecms\Tests\Feature\Theme\Concerns\UsesFixtureThemes;
use Webtack\Ecms\Tests\TestCase;
use Webtack\Ecms\Theme\ThemeException;
use Webtack\Ecms\Theme\ThemeHooks;

class ThemeHooksTest extends TestCase
{
    use UsesFixtureThemes;

    public function test_general_hooks_run_before_specific_ones(): void
    {
        $hooks = new ThemeHooks;
        $hooks->preprocess('field.youtube', function (array &$vars) {
            $vars['log'][] = 'field.youtube';
        });
        $hooks->preprocess('field', function (array &$vars) {
            $vars['log'][] = 'field';
        });

        $vars = $hooks->applyPreprocess('field.youtube', ['log' => []]);

        $this->assertSame(['field', 'field.youtube'], $vars['log']);
    }

    public function test_a_specific_hook_does_not_run_for_a_general_name(): void
    {
        $hooks = new ThemeHooks;
        $hooks->preprocess('field.youtube', function (array &$vars) {
            $vars['touched'] = true;
        });

        $this->assertArrayNotHasKey('touched', $hooks->applyPreprocess('field', []));
    }

    public function test_default_hooks_run_before_the_active_theme(): void
    {
        $log = '<?php return function (Webtack\Ecms\Theme\ThemeHooks $hooks): void { $hooks->preprocess(\'field\', function (array &$vars) { $vars[\'log\'][] = \'%s\'; }); };';
        $this->makeTheme('default', ['field' => '{{ implode(",", $log) }}'], sprintf($log, 'default'));
        $this->makeTheme('custom', [], sprintf($log, 'custom'));
        $manager = $this->useTheme('custom');

        $this->assertSame('default,custom', $manager->render(['field'], ['log' => []]));
    }

    public function test_suggest_adds_a_candidate(): void
    {
        $this->makeTheme('default', [
            'pages/page' => 'GENERAL',
            'pages/page-special' => 'SPECIAL',
        ], '<?php return function (Webtack\Ecms\Theme\ThemeHooks $hooks): void {
            $hooks->suggest(\'page\', function (array &$candidates) { array_unshift($candidates, \'pages/page-special\'); });
        };');
        $manager = $this->useTheme('default');

        $this->assertSame('SPECIAL', $manager->render(['pages/page'], [], 'page'));
    }

    public function test_the_documented_youtube_example_works(): void
    {
        $this->makeTheme('default', ['fields/field' => '{{ $autoplay ? "auto" : "manual" }}'], '<?php return function (Webtack\Ecms\Theme\ThemeHooks $hooks): void {
            $hooks->preprocess(\'field.youtube\', function (array &$vars) { $vars[\'autoplay\'] = false; });
        };');
        $manager = $this->useTheme('default');

        $this->assertSame('manual', $manager->render(['fields/field'], ['autoplay' => true], 'field.youtube'));
    }

    public function test_hooks_file_that_does_not_return_a_function_is_rejected(): void
    {
        $this->makeTheme('default', ['home' => 'HOME'], '<?php return 42;');
        $manager = $this->useTheme('default');

        $this->expectException(ThemeException::class);
        $this->expectExceptionMessage('hooks.php');

        $manager->render(['home']);
    }

    public function test_hook_files_are_loaded_once(): void
    {
        $this->makeTheme('default', ['home' => 'HOME'], '<?php $GLOBALS[\'hook_loads\'] = ($GLOBALS[\'hook_loads\'] ?? 0) + 1; return function (Webtack\Ecms\Theme\ThemeHooks $hooks): void {};');
        $GLOBALS['hook_loads'] = 0;
        $manager = $this->useTheme('default');

        $manager->render(['home']);
        $manager->render(['home']);

        $this->assertSame(1, $GLOBALS['hook_loads']);
    }
}
