<?php

namespace Webtack\Ecms\Tests\Feature\Package;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Artisan;
use Webtack\Ecms\Models\ContentNode;
use Webtack\Ecms\Models\ContentType;
use Webtack\Ecms\Tests\Fixtures\HostController;
use Webtack\Ecms\Tests\TestCase;

/**
 * The public catch-all `/{path}` is registered after every route of the host, with and without cached routes.
 */
class RouteOrderTest extends TestCase
{
    use RefreshDatabase;

    /**
     * The host's routes (Testbench loads them before the booted callbacks, as `routes/web.php` of a host is loaded
     * by its route service provider before `Application::booted()` callbacks run).
     */
    protected function defineRoutes($router): void
    {
        /** @var Router $router */
        $router->middleware('web')->group(function (Router $router) {
            $router->get('/contact', HostController::class);
            $router->get('/offers/{slug}', HostController::class);
        });
    }

    protected function tearDown(): void
    {
        @unlink($this->app->getCachedRoutesPath());

        parent::tearDown();
    }

    public function test_a_host_route_is_answered_before_the_public_catch_all(): void
    {
        $this->get('/contact')->assertOk()->assertSee('host-page');
        $this->get('/offers/spring')->assertOk()->assertSee('host-page');
    }

    public function test_the_public_routes_are_the_last_of_the_route_table(): void
    {
        $uris = collect($this->app['router']->getRoutes()->getRoutes())->map->uri()->values();

        $this->assertLessThan($uris->search('{path}'), $uris->search('contact'));
        $this->assertSame($uris->count() - 1, $uris->search(fn (string $uri) => str_starts_with($uri, '{path}')), 'The catch-all is the last route');
    }

    public function test_a_page_is_still_served_by_the_catch_all(): void
    {
        $page = ContentNode::factory()->create(['content_type' => $this->pageType(), 'slug' => 'about-us']);
        $this->assertNotNull($page);

        $this->get('/about-us')->assertOk();
    }

    public function test_the_order_survives_route_cache(): void
    {
        $this->cacheRoutes();

        // Boot the application again: the provider sees the cache and loads no route itself. The host's route service
        // provider loads the cache file (Testbench has none), so the test does it.
        $this->refreshApplication();
        $this->assertTrue($this->app->routesAreCached());
        require $this->app->getCachedRoutesPath();

        // The new application has a new in-memory database.
        $this->defineDatabaseMigrations();
        Artisan::call('migrate');

        $this->get('/contact')->assertOk()->assertSee('host-page');
        $this->get('/offers/spring')->assertOk()->assertSee('host-page');
        $this->get('/login')->assertOk();

        $uris = collect($this->app['router']->getRoutes()->getRoutes())->map->uri()->values();
        $this->assertLessThan($uris->search(fn (string $uri) => str_starts_with($uri, '{path}')), $uris->search('contact'));
    }

    /**
     * Writes the cache file of the routes the running application has, the way `route:cache` does for a host. The
     * Artisan command itself boots a separate skeleton application in Testbench, which has neither the package nor
     * the routes of the test.
     */
    private function cacheRoutes(): void
    {
        $routes = $this->app['router']->getRoutes();
        $routes->refreshNameLookups();
        $routes->refreshActionLookups();

        foreach ($routes as $route) {
            $route->prepareForSerialization();
        }

        file_put_contents(
            $this->app->getCachedRoutesPath(),
            "<?php\n\napp('router')->setCompiledRoutes(\n".var_export($routes->compile(), true)."\n);\n",
        );
    }

    private function pageType(): string
    {
        return ContentType::factory()->create(['kind' => 'page'])->key;
    }
}
