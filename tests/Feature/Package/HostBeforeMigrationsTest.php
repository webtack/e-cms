<?php

namespace Webtack\Ecms\Tests\Feature\Package;

use Illuminate\Routing\Router;
use Webtack\Ecms\Http\Middleware\ResolvePublicLanguage;
use Webtack\Ecms\Tests\TestCase;

/**
 * The language prefix middleware is global, so it runs for every request of the host, also for one made before the
 * package migrations ran (`composer require` and a deploy happen before `migrate`). It must not touch the database then.
 * The test has no `RefreshDatabase`: only the host's own tables exist.
 */
class HostBeforeMigrationsTest extends TestCase
{
    protected function defineRoutes($router): void
    {
        /** @var Router $router */
        $router->get('/api/x', fn () => 'host-api');
    }

    protected function setUp(): void
    {
        parent::setUp();

        // The middleware remembers that the tables exist for the rest of the process; an earlier test may have set it.
        (new \ReflectionProperty(ResolvePublicLanguage::class, 'tablesExist'))->setValue(null, false);
    }

    public function test_a_host_url_is_answered_without_a_database_error(): void
    {
        $schema = $this->app['db']->connection()->getSchemaBuilder();
        $this->assertFalse($schema->hasTable('app_languages'));
        $this->assertFalse($schema->hasTable('app_settings'));

        $this->get('/api/x')->assertOk()->assertSee('host-api');
    }
}
