<?php

namespace Webtack\Ecms\Tests;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\Http\Kernel as HttpKernel;
use Illuminate\Filesystem\Filesystem;
use Inertia\ServiceProvider as InertiaServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;
use Webtack\Ecms\EcmsServiceProvider;
use Webtack\Ecms\Tests\Fixtures\User;

use function Orchestra\Testbench\default_migration_path;

/**
 * Base class of the package suite: a Laravel application skeleton (Testbench) with the package provider, the
 * fixture user model, the host's default `users` migration and the package migrations, on in-memory SQLite.
 */
abstract class TestCase extends Orchestra
{
    private ?int $authorId = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->publishDefaultThemeAssets();
    }

    /**
     * Puts the built assets of the theme `default` where a host has them after `vendor:publish --tag=ecms-assets`
     * (`public/themes/default`): the theme tests read the Vite manifest from there. The committed `dist/` is the
     * source, so no frontend build is needed. The Testbench public directory outlives a test run, so the copy is
     * refreshed only when the manifest differs from the one in `dist/`.
     */
    private function publishDefaultThemeAssets(): void
    {
        $source = $this->packagePath('dist/themes/default');
        $target = public_path('themes/default');

        if (! is_file($target.'/manifest.json') || md5_file($target.'/manifest.json') !== md5_file($source.'/manifest.json')) {
            $files = new Filesystem;
            $files->deleteDirectory($target);
            $files->copyDirectory($source, $target);
        }
    }

    /**
     * @param  Application  $app
     * @return list<class-string>
     */
    protected function getPackageProviders($app): array
    {
        // Testbench does not discover the providers of the package's own dependencies (a host does, through
        // `package:discover`), so Inertia's provider is listed here.
        return [InertiaServiceProvider::class, EcmsServiceProvider::class];
    }

    /**
     * @param  Application  $app
     */
    protected function defineEnvironment($app): void
    {
        // A real host resolves the HTTP kernel before the providers boot (it handles the request), and the kernel copies
        // its middleware groups to the router when it is constructed, which discards what a provider pushed to the
        // router earlier. Testbench resolves it lazily, after the boot, so it is resolved here, before the providers.
        $app->make(HttpKernel::class);

        $app['config']->set('auth.providers.users.model', User::class);
        // `assertInertia()` checks that the page component exists; the admin pages live in the package, not in the skeleton.
        $app['config']->set('inertia.pages.paths', [dirname(__DIR__).'/resources/js/Pages']);
        $app['config']->set('database.default', 'testing');
        // A Laravel application enforces foreign keys on SQLite (`DB_FOREIGN_KEYS`), and tests rely on the restrictions.
        $app['config']->set('database.connections.testing.foreign_key_constraints', true);
        $app['config']->set('app.key', 'base64:'.base64_encode(str_repeat('t', 32)));
    }

    /**
     * The host's default migrations (`users`, which the package migrations extend) join the package migrations on the
     * migrator path. They sort first by file name, and `migrate:fresh` of `RefreshDatabase` runs them as well.
     */
    protected function defineDatabaseMigrations(): void
    {
        $this->loadMigrationsFrom(default_migration_path());
    }

    /**
     * Absolute path of a file or directory of the package.
     *
     * @example $this->packagePath('themes/default/views')
     */
    protected function packagePath(string $relative = ''): string
    {
        return dirname(__DIR__).($relative === '' ? '' : '/'.ltrim($relative, '/'));
    }

    /**
     * Id of a user that authors nodes created through `ContentNodeCreator` in tests (one per test).
     */
    protected function authorId(): int
    {
        return $this->authorId ??= User::factory()->create()->id;
    }
}
