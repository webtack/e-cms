<?php

namespace Webtack\Ecms\Tests\Feature\Content;

use Illuminate\Support\Facades\Route;
use Webtack\Ecms\Content\PublicPaths;
use Webtack\Ecms\Tests\TestCase;

/**
 * Keeps `content.reserved_paths` in step with what the application owns on the first URL segment,
 * so a new service route or `public/` directory cannot be shadowed by a page or type slug.
 */
class ReservedPathsGuardTest extends TestCase
{
    public function test_the_first_segment_of_every_route_is_reserved(): void
    {
        $segments = collect(Route::getRoutes()->getRoutes())
            ->map(fn ($route) => explode('/', trim($route->uri(), '/'))[0])
            ->reject(fn (string $segment) => $segment === '' || str_starts_with($segment, '{'))
            ->unique();

        $missing = $segments->diff(config('content.reserved_paths'))->values()->all();

        $this->assertSame([], $missing, 'Add these first route segments to content.reserved_paths: '.implode(', ', $missing));
    }

    /**
     * The package cannot scan the `public/` of a host. It reserves the directories a Laravel public folder has
     * (`build`, `storage`, `vendor`) and the ones the package publishes to (`themes`, `app`, `vendor/ecms`).
     */
    public function test_the_directories_of_a_public_folder_and_the_publish_targets_are_reserved(): void
    {
        $reserved = app(PublicPaths::class)->reserved();

        foreach (['build', 'storage', 'themes', 'vendor', 'app'] as $directory) {
            $this->assertContains($directory, $reserved, "Add the public/ directory {$directory} to content.reserved_paths");
        }

        $this->assertSame([], array_values(array_diff(array_map('strtolower', config('content.reserved_paths')), $reserved)));
    }
}
