<?php

namespace Webtack\Ecms\Tests\Feature\Package;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Router;
use Webtack\Ecms\Content\PublicPaths;
use Webtack\Ecms\Tests\Fixtures\User;
use Webtack\Ecms\Tests\TestCase;

/**
 * A language code cannot take the first segment of a route of the host (the prefix is stripped before routing, so
 * `/en/contact` of the host would stop working).
 */
class LanguagePrefixTest extends TestCase
{
    use RefreshDatabase;

    protected function defineRoutes($router): void
    {
        /** @var Router $router */
        // Closures: a route whose action class lives in `Webtack\Ecms\` counts as a route of the package, not of the host.
        $router->middleware('web')->get('/en/contact', fn () => 'host-page');
    }

    public function test_a_language_code_equal_to_the_first_segment_of_a_host_route_is_refused(): void
    {
        $this->assertSame(PublicPaths::REASON_ROUTE, app(PublicPaths::class)->languageCodeConflict('en'));
        $this->assertNull(app(PublicPaths::class)->languageCodeConflict('pl'));

        $this->actingAs(User::factory()->create())
            ->post('/admin/settings/languages', ['code' => 'en', 'name' => 'English'])
            ->assertSessionHasErrors('code');

        $this->assertDatabaseMissing('app_languages', ['code' => 'en']);
    }

    public function test_the_routes_of_the_package_do_not_block_a_language_code(): void
    {
        // `/admin/...` and `/login` belong to the package and are reserved paths; a code is checked against the host only.
        $this->assertSame(PublicPaths::REASON_RESERVED, app(PublicPaths::class)->languageCodeConflict('admin'));
        $this->assertNull(app(PublicPaths::class)->languageCodeConflict('de'));
    }
}
