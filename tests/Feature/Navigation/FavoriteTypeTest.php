<?php

namespace Webtack\Ecms\Tests\Feature\Navigation;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Webtack\Ecms\Models\ContentType;
use Webtack\Ecms\Models\Role;
use Webtack\Ecms\Models\UserFavoriteType;
use Webtack\Ecms\Tests\Fixtures\User;
use Webtack\Ecms\Tests\TestCase;

class FavoriteTypeTest extends TestCase
{
    use RefreshDatabase;

    private function editor(array $permissions = ['node:news' => 'any']): User
    {
        return User::factory()->withRole(Role::factory()->withPermissions($permissions)->create())->create();
    }

    public function test_a_user_adds_and_removes_a_type(): void
    {
        ContentType::create(['key' => 'news', 'name' => 'News']);
        $user = $this->editor();

        $this->actingAs($user)->post('/admin/favorites/news')->assertRedirect();
        $this->post('/admin/favorites/news')->assertRedirect();
        $this->assertSame(['news'], $user->favoriteTypes()->pluck('type_key')->all());

        $this->delete('/admin/favorites/news')->assertRedirect();
        $this->assertSame(0, UserFavoriteType::count());
    }

    public function test_adding_a_type_without_access_is_forbidden(): void
    {
        ContentType::create(['key' => 'news', 'name' => 'News']);
        ContentType::create(['key' => 'blog', 'name' => 'Blog']);

        $this->actingAs($this->editor())->post('/admin/favorites/blog')->assertForbidden();
        $this->assertSame(0, UserFavoriteType::count());
    }

    public function test_an_unknown_type_is_not_found_for_admin(): void
    {
        $this->actingAs(User::factory()->create())->post('/admin/favorites/nope')->assertNotFound();
    }

    public function test_another_users_list_is_not_changed(): void
    {
        ContentType::create(['key' => 'news', 'name' => 'News']);
        $first = $this->editor();
        $second = $this->editor();

        $this->actingAs($first)->post('/admin/favorites/news');
        $this->actingAs($second)->delete('/admin/favorites/news');

        $this->assertSame(['news'], $first->favoriteTypes()->pluck('type_key')->all());
    }

    public function test_deleting_a_type_removes_it_from_every_list(): void
    {
        $type = ContentType::create(['key' => 'news', 'name' => 'News']);
        $user = User::factory()->create();
        UserFavoriteType::create(['user_id' => $user->id, 'type_key' => 'news']);

        $type->delete();

        $this->assertSame(0, UserFavoriteType::count());
    }

    public function test_the_node_list_tells_whether_the_type_is_a_favorite(): void
    {
        ContentType::create(['key' => 'news', 'name' => 'News']);
        $user = User::factory()->create();

        $this->actingAs($user)->get('/admin/content-types/news/nodes')
            ->assertInertia(fn (Assert $page) => $page->where('isFavorite', false));

        UserFavoriteType::create(['user_id' => $user->id, 'type_key' => 'news']);

        $this->get('/admin/content-types/news/nodes')
            ->assertInertia(fn (Assert $page) => $page->where('isFavorite', true));
    }

    public function test_the_type_list_passes_favorite_keys_and_star_availability(): void
    {
        ContentType::create(['key' => 'news', 'name' => 'News']);
        ContentType::create(['key' => 'blog', 'name' => 'Blog']);
        $admin = User::factory()->withRole(Role::factory()->withPermissions(['types' => 'allow', 'node:news' => 'any'])->create())->create();
        UserFavoriteType::create(['user_id' => $admin->id, 'type_key' => 'news']);

        $this->actingAs($admin)->get('/admin/content-types')
            ->assertInertia(fn (Assert $page) => $page
                ->where('favoriteKeys', ['news'])
                ->where('table.rows.0.key', 'blog')
                ->where('table.rows.0.can_favorite', false)
                ->where('table.rows.1.key', 'news')
                ->where('table.rows.1.can_favorite', true));
    }

    public function test_the_type_list_without_the_types_permission_passes_favorite_keys(): void
    {
        ContentType::create(['key' => 'news', 'name' => 'News']);
        $user = $this->editor();
        UserFavoriteType::create(['user_id' => $user->id, 'type_key' => 'news']);

        $this->actingAs($user)->get('/admin/content-types')
            ->assertInertia(fn (Assert $page) => $page->where('favoriteKeys', ['news'])->where('canManage', false));
    }
}
