<?php

namespace Webtack\Ecms\Tests\Feature\Admin;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Webtack\Ecms\Models\ContentNode;
use Webtack\Ecms\Models\ContentType;
use Webtack\Ecms\Models\Role;
use Webtack\Ecms\Tests\Fixtures\User;
use Webtack\Ecms\Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get('/admin/dashboard')->assertRedirect('/login');
        $this->get('/admin/profile')->assertRedirect('/login');
    }

    public function test_verified_user_sees_dashboard_with_shared_props(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/admin/dashboard')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Dashboard')
                ->where('auth.user.id', $user->id)
                ->where('auth.user.name', $user->name)
                ->where('auth.user.email', $user->email)
                ->missing('auth.user.password')
                ->missing('auth.user.remember_token'));
    }

    public function test_dashboard_props_have_the_widget_keys(): void
    {
        $this->actingAs(User::factory()->create())
            ->get('/admin/dashboard')
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Dashboard')
                ->has('recent')
                ->has('drafts')
                ->has('create')
                ->has('favorites')
                ->has('counters')
                ->has('top')
                ->has('scheduled')
                ->has('expired')
                ->has('media'));
    }

    public function test_a_user_without_access_gets_empty_widget_data(): void
    {
        ContentType::create(['key' => 'news', 'name' => 'News']);
        ContentNode::factory()->create(['content_type' => 'news']);
        $user = User::factory()->withRole(Role::factory()->create())->create();

        $this->actingAs($user)
            ->get('/admin/dashboard')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('recent', 0)
                ->has('drafts', 0)
                ->has('create', 0)
                ->has('favorites', 0)
                ->has('counters', 0)
                ->has('top', 0)
                ->has('scheduled', 0)
                ->has('expired', 0)
                ->where('media', null));
    }

    public function test_admin_root_redirects_to_dashboard(): void
    {
        $this->actingAs(User::factory()->create())
            ->get('/admin')
            ->assertRedirect('/admin/dashboard');
    }

    public function test_verified_user_sees_profile_placeholder(): void
    {
        $this->actingAs(User::factory()->create())
            ->get('/admin/profile')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Admin/Profile'));
    }

    public function test_flash_messages_are_shared_once(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            // Emulates data flashed by the previous request (aged to "old").
            ->withSession([
                'success' => 'Saved',
                'error' => 'Failed',
                '_flash' => ['old' => ['success', 'error'], 'new' => []],
            ])
            ->get('/admin/dashboard')
            ->assertInertia(fn (Assert $page) => $page
                ->where('flash.success', 'Saved')
                ->where('flash.error', 'Failed')
                ->where('flash.id', fn ($id) => is_string($id) && $id !== ''));

        $this->get('/admin/dashboard')
            ->assertInertia(fn (Assert $page) => $page
                ->where('flash.id', null)
                ->where('flash.success', null)
                ->where('flash.error', null));
    }

    public function test_flash_id_differs_for_equal_messages_in_consecutive_requests(): void
    {
        $user = User::factory()->create();
        $ids = [];

        foreach (range(1, 2) as $ignored) {
            $response = $this->actingAs($user)
                ->withSession([
                    'success' => 'Saved',
                    '_flash' => ['old' => ['success'], 'new' => []],
                ])
                ->get('/admin/dashboard');

            $ids[] = $response->viewData('page')['props']['flash']['id'];
        }

        $this->assertCount(2, $ids);
        $this->assertNotSame($ids[0], $ids[1]);
    }
}
