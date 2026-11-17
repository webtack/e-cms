<?php

namespace Webtack\Ecms\Tests\Feature\Package;

use Illuminate\Support\Facades\Route;
use Webtack\Ecms\Tests\TestCase;

/**
 * `ecms.auth_routes` off: the host keeps `/login` for itself, the package registers no authentication route.
 */
class AuthRoutesOffTest extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('ecms.auth_routes', false);
    }

    public function test_no_authentication_route_exists(): void
    {
        $this->assertFalse(Route::has('login'));
        $this->assertFalse(Route::has('logout'));
        $this->assertFalse(Route::has('verification.notice'));
    }

    public function test_the_public_site_and_the_admin_are_still_registered(): void
    {
        $this->assertTrue(Route::has('public.home'));
        $this->assertTrue(Route::has('image-styles.show'));
    }
}
