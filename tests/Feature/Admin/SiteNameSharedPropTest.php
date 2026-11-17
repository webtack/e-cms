<?php

namespace Webtack\Ecms\Tests\Feature\Admin;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Webtack\Ecms\Support\AppSettings;
use Webtack\Ecms\Tests\Fixtures\User;
use Webtack\Ecms\Tests\TestCase;

class SiteNameSharedPropTest extends TestCase
{
    use RefreshDatabase;

    public function test_stored_site_name_is_shared_with_admin_pages(): void
    {
        app(AppSettings::class)->set('site.name', ['default' => 'My site']);

        $this->actingAs(User::factory()->create())
            ->get('/admin/dashboard')
            ->assertInertia(fn (Assert $page) => $page->where('site.name', 'My site'));
    }

    public function test_site_name_falls_back_to_the_application_name(): void
    {
        config(['app.name' => 'Fallback app']);

        $this->actingAs(User::factory()->create())
            ->get('/admin/dashboard')
            ->assertInertia(fn (Assert $page) => $page->where('site.name', 'Fallback app'));
    }

    public function test_site_name_is_available_to_a_guest_on_the_login_page(): void
    {
        app(AppSettings::class)->set('site.name', ['default' => 'My site']);

        $this->get('/login')
            ->assertInertia(fn (Assert $page) => $page->where('site.name', 'My site'));
    }
}
