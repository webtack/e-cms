<?php

namespace Webtack\Ecms\Tests\Feature\Package;

use Illuminate\Filesystem\Filesystem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Webtack\Ecms\Support\AdminAssets;
use Webtack\Ecms\Tests\TestCase;

/**
 * The admin build is published into the host's `public/vendor/ecms/build`: the root view explains how to publish it
 * while it is missing, and the Inertia asset version is the hash of the published manifest (not of the host's own
 * `public/build/manifest.json`).
 */
class AdminAssetsTest extends TestCase
{
    use RefreshDatabase;

    private function directory(): string
    {
        return public_path('vendor/ecms');
    }

    protected function setUp(): void
    {
        parent::setUp();

        (new Filesystem)->deleteDirectory($this->directory());
    }

    protected function tearDown(): void
    {
        (new Filesystem)->deleteDirectory($this->directory());

        parent::tearDown();
    }

    /** A published build: a manifest with the two entries of the admin and the hashed files they name. */
    private function publishBuild(): string
    {
        $files = new Filesystem;
        $files->ensureDirectoryExists($this->directory().'/build/assets');
        $manifest = json_encode([
            'resources/css/app.css' => ['file' => 'assets/app-1.css', 'src' => 'resources/css/app.css', 'isEntry' => true],
            'resources/js/app.tsx' => ['file' => 'assets/app-1.js', 'src' => 'resources/js/app.tsx', 'isEntry' => true],
        ]);
        $files->put($this->directory().'/build/manifest.json', $manifest);

        return $manifest;
    }

    public function test_the_root_view_explains_how_to_publish_the_missing_build(): void
    {
        $this->get('/login')
            ->assertOk()
            ->assertSee('The admin assets are not published')
            ->assertSee('vendor:publish --tag=ecms-assets --force')
            ->assertDontSee('data-page=', false);
    }

    public function test_there_is_no_asset_version_without_a_build(): void
    {
        $this->assertNull(app(AdminAssets::class)->version());
    }

    public function test_the_version_is_the_hash_of_the_published_manifest(): void
    {
        $manifest = $this->publishBuild();

        $this->assertSame(hash('xxh128', $manifest), app(AdminAssets::class)->version());
    }

    public function test_the_root_view_loads_the_published_build(): void
    {
        $this->publishBuild();

        $this->get('/login')
            ->assertOk()
            ->assertSee('vendor/ecms/build/assets/app-1.js', false)
            ->assertSee('vendor/ecms/build/assets/app-1.css', false)
            ->assertSee('data-page=', false)
            ->assertDontSee('The admin assets are not published');
    }

    public function test_inertia_compares_the_request_version_with_the_package_manifest(): void
    {
        $version = hash('xxh128', $this->publishBuild());

        $this->get('/login', ['X-Inertia' => 'true', 'X-Inertia-Version' => $version])
            ->assertOk()
            ->assertHeader('X-Inertia', 'true')
            ->assertJsonPath('version', $version)
            ->assertJsonPath('component', 'Auth/Login');

        // An outdated client is told to reload.
        $this->get('/login', ['X-Inertia' => 'true', 'X-Inertia-Version' => 'old'])->assertStatus(409)->assertHeader('X-Inertia-Location');
    }

    public function test_the_hot_file_serves_the_dev_server_instead_of_the_build(): void
    {
        $files = new Filesystem;
        $files->ensureDirectoryExists($this->directory());
        $files->put($this->directory().'/hot', 'http://localhost:5173');

        $this->assertNotNull(app(AdminAssets::class)->tags());
        $this->get('/login')->assertOk()->assertSee('http://localhost:5173', false);
    }
}
