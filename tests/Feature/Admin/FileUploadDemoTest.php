<?php

namespace Webtack\Ecms\Tests\Feature\Admin;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Webtack\Ecms\Tests\Fixtures\User;
use Webtack\Ecms\Tests\TestCase;

class FileUploadDemoTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get('/admin/ui/file-upload')->assertRedirect('/login');
    }

    public function test_authenticated_user_gets_the_demo_page(): void
    {
        $this->actingAs(User::factory()->create())
            ->get('/admin/ui/file-upload')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Admin/Ui/FileUploadDemo'));
    }

    public function test_the_route_has_its_name(): void
    {
        $this->assertSame('/admin/ui/file-upload', route('admin.ui.file-upload', absolute: false));
    }

    public function test_it_is_not_found_in_production(): void
    {
        $this->app->detectEnvironment(fn () => 'production');

        $this->actingAs(User::factory()->create())->get('/admin/ui/file-upload')->assertNotFound();
    }
}
