<?php

namespace Webtack\Ecms\Tests\Feature\Admin;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Webtack\Ecms\Access\AccessResolver;
use Webtack\Ecms\Models\ContentField;
use Webtack\Ecms\Models\ContentNode;
use Webtack\Ecms\Models\ContentType;
use Webtack\Ecms\Models\Role;
use Webtack\Ecms\Tests\Fixtures\User;
use Webtack\Ecms\Tests\TestCase;

class ContentTypeDestroyTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): static
    {
        return $this->actingAs(User::factory()->create());
    }

    public function test_guest_is_redirected_to_login(): void
    {
        ContentType::create(['key' => 'news', 'name' => 'News']);

        $this->delete('/admin/content-types/news')->assertRedirect('/login');

        $this->assertNotNull(ContentType::findByKey('news'));
    }

    public function test_the_route_has_its_name(): void
    {
        $this->assertSame('/admin/content-types/news', route('admin.content-types.destroy', 'news', false));
    }

    public function test_an_empty_type_is_deleted_with_its_fields(): void
    {
        ContentType::create(['key' => 'news', 'name' => 'News']);
        ContentField::factory()->create(['content_type' => 'news', 'key' => 'body']);

        $this->admin()->delete('/admin/content-types/news')
            ->assertRedirect('/admin/content-types')
            ->assertSessionHas('success');

        $this->assertNull(ContentType::findByKey('news'));
        $this->assertSame(0, ContentField::where('content_type', 'news')->count());
    }

    public function test_a_type_with_nodes_is_kept(): void
    {
        ContentType::create(['key' => 'news', 'name' => 'News']);
        ContentNode::factory()->create(['content_type' => 'news']);

        $this->admin()->from('/admin/content-types')->delete('/admin/content-types/news')
            ->assertRedirect('/admin/content-types')
            ->assertSessionHas('error')
            ->assertSessionMissing('success');

        $this->assertNotNull(ContentType::findByKey('news'));
        $this->assertSame(1, ContentNode::count());
        $this->assertSame(7, ContentType::findByKey('news')->fields()->count());
    }

    public function test_a_type_with_child_types_is_kept(): void
    {
        ContentType::create(['key' => 'article', 'name' => 'Article']);
        ContentType::create(['key' => 'news', 'name' => 'News', 'parent_type' => 'article']);

        $this->admin()->from('/admin/content-types')->delete('/admin/content-types/article')
            ->assertRedirect('/admin/content-types')
            ->assertSessionHas('error');

        $this->assertNotNull(ContentType::findByKey('article'));
        $this->assertNotNull(ContentType::findByKey('news'));
    }

    public function test_a_missing_or_non_content_type_is_not_found(): void
    {
        ContentType::create(['key' => 'home', 'name' => 'Home', 'kind' => 'page']);
        ContentType::create(['key' => 'hero', 'name' => 'Hero', 'kind' => 'block']);

        foreach (['missing', 'home', 'hero'] as $key) {
            $this->admin()->delete("/admin/content-types/{$key}")->assertNotFound();
        }

        $this->assertNotNull(ContentType::findByKey('home'));
        $this->assertNotNull(ContentType::findByKey('hero'));
    }

    public function test_role_levels_of_the_type_are_removed_and_not_restored_for_a_new_type_with_the_same_key(): void
    {
        ContentType::create(['key' => 'news', 'name' => 'News']);
        $role = Role::factory()->withPermissions(['node:news' => 'any', 'node:other' => 'own'])->create();

        $this->admin()->delete('/admin/content-types/news')->assertRedirect();

        $this->assertSame(['node:other'], $role->permissions()->pluck('scope')->all());

        ContentType::create(['key' => 'news', 'name' => 'News']);
        $user = User::factory()->withRole($role)->create();
        $this->assertSame('none', app(AccessResolver::class)->nodeLevel($user, 'news'));
    }
}
