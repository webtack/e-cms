<?php

namespace Webtack\Ecms\Tests\Feature\Package;

use Illuminate\Contracts\Http\Kernel as HttpKernel;
use Illuminate\Support\Facades\Route;
use Webtack\Ecms\Http\Middleware\ResolvePublicLanguage;
use Webtack\Ecms\Tests\TestCase;

/**
 * `ecms.public_site` off: no public routes, no language prefix, the standard 404 for an unknown address.
 */
class PublicSiteOffTest extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('ecms.public_site', false);
    }

    public function test_no_public_route_exists(): void
    {
        foreach (['public.home', 'public.search', 'public.sitemap', 'public.sitemap.xml'] as $name) {
            $this->assertFalse(Route::has($name), "The route {$name} must not exist");
        }

        $this->get('/')->assertNotFound();
        $this->get('/some-page')->assertNotFound();
    }

    public function test_the_language_prefix_middleware_is_not_registered(): void
    {
        $this->assertFalse($this->app->make(HttpKernel::class)->hasMiddleware(ResolvePublicLanguage::class));
    }

    public function test_the_admin_and_login_still_work(): void
    {
        $this->assertTrue(Route::has('login'));
        $this->assertTrue(Route::has('image-styles.show'));
    }

    public function test_an_unknown_address_gets_the_standard_404_not_the_theme(): void
    {
        $this->get('/no-such-page-anywhere')->assertNotFound()->assertDontSee('site-header', false);
    }
}
