<?php

namespace Webtack\Ecms\Tests\Feature\Theme;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Webtack\Ecms\Tests\TestCase;

/**
 * The styled `default` theme keeps its hook classes, and its stylesheet is the Vite build of `assets/app.css`.
 */
class DefaultThemeStylingTest extends TestCase
{
    use RefreshDatabase;

    /** The built file of the theme stylesheet, from the Vite manifest (the build is run on the host). */
    private function builtThemeStylesheet(): string
    {
        $manifest = public_path('themes/default/manifest.json');

        $this->assertFileExists($manifest, 'Run `npm run theme:build` on the host before this test.');

        return json_decode((string) file_get_contents($manifest), true, flags: JSON_THROW_ON_ERROR)['themes/default/assets/app.css']['file'];
    }

    public function test_the_home_page_loads_the_theme_stylesheet_and_keeps_its_hook_classes(): void
    {
        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee('site-header', false);
        $response->assertSee('site-main', false);
        $response->assertSee('site-footer', false);
        $response->assertSee($this->builtThemeStylesheet(), false);
    }

    public function test_an_unknown_address_answers_404_inside_the_styled_layout(): void
    {
        $response = $this->get('/no-such-page-for-styling-test');

        $response->assertNotFound();
        $response->assertSee('site-main', false);
        $response->assertSee($this->builtThemeStylesheet(), false);
    }
}
