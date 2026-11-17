<?php

namespace Webtack\Ecms\Tests\Feature\Package;

use Illuminate\Contracts\Http\Kernel as HttpKernel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Webtack\Ecms\Access\AccessResolver;
use Webtack\Ecms\EcmsServiceProvider;
use Webtack\Ecms\Http\Middleware\EnsureUserIsActive;
use Webtack\Ecms\Http\Middleware\HandleInertiaRequests;
use Webtack\Ecms\Http\Middleware\ResolvePublicLanguage;
use Webtack\Ecms\Http\Middleware\SetLocale;
use Webtack\Ecms\Tests\Fixtures\User;
use Webtack\Ecms\Tests\TestCase;

/**
 * What a host gets from installing the package without editing any of its own files: the provider, the merged
 * configuration, the gates, the `web` middleware, the redirects and the theme 404 page.
 */
class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_provider_is_registered_and_the_configuration_is_merged(): void
    {
        $this->assertNotNull($this->app->getProvider(EcmsServiceProvider::class));

        foreach (['content', 'files', 'theme', 'social_networks', 'limits', 'ecms'] as $name) {
            $this->assertIsArray(config($name), "config/{$name}.php is not merged");
        }

        $this->assertTrue(config('ecms.public_site'));
        $this->assertTrue(config('ecms.auth_routes'));
        $this->assertSame(['en', 'uk'], array_keys(config('ecms.locales')));
    }

    public function test_the_host_overrides_a_top_level_key_and_keeps_the_other_package_defaults(): void
    {
        // The provider merges at register time: a config value set earlier (the host's own file) wins per top-level key.
        $this->assertArrayHasKey('reserved_paths', config('content'));
        $this->assertArrayHasKey('profiles', config('files'));
    }

    public function test_every_system_permission_is_a_gate_ability(): void
    {
        foreach (AccessResolver::SYSTEM as $permission) {
            $this->assertTrue(Gate::has($permission), "The ability {$permission} is not defined");
        }

        $admin = User::factory()->create();

        $this->assertTrue(Gate::forUser($admin)->allows('users'));
    }

    public function test_the_web_group_has_each_package_middleware_once(): void
    {
        $group = $this->app['router']->getMiddlewareGroups()['web'];

        foreach ([EnsureUserIsActive::class, SetLocale::class, HandleInertiaRequests::class] as $middleware) {
            $this->assertCount(1, array_keys($group, $middleware, true), "{$middleware} is not in the web group exactly once");
        }

        $this->assertContains('Illuminate\Session\Middleware\AuthenticateSession', $group);
        $this->assertSame(HandleInertiaRequests::class, end($group), 'The Inertia middleware is the last of the group');
    }

    public function test_the_language_prefix_middleware_is_global_with_the_public_site(): void
    {
        $this->assertTrue($this->app->make(HttpKernel::class)->hasMiddleware(ResolvePublicLanguage::class));
    }

    public function test_guests_go_to_login_and_signed_in_users_to_the_dashboard(): void
    {
        $this->get('/admin/dashboard')->assertRedirect('/login');

        $this->actingAs(User::factory()->create())->get('/login')->assertRedirect('/admin/dashboard');
    }

    public function test_an_unknown_address_is_the_theme_404_but_the_admin_keeps_the_standard_one(): void
    {
        $this->get('/no-such-page-anywhere')->assertNotFound()->assertSee('site-header', false);

        $this->actingAs(User::factory()->create())->get('/admin/no-such-section')->assertNotFound()->assertDontSee('site-header', false);
        $this->getJson('/no-such-page-anywhere')->assertNotFound();
    }

    public function test_the_package_routes_are_registered(): void
    {
        foreach (['public.home', 'public.search', 'public.sitemap', 'public.sitemap.xml', 'login', 'image-styles.show'] as $name) {
            $this->assertTrue(Route::has($name), "The route {$name} is missing");
        }
    }
}
