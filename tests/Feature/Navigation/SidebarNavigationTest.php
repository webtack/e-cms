<?php

namespace Webtack\Ecms\Tests\Feature\Navigation;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Inertia\Testing\AssertableInertia as Assert;
use Webtack\Ecms\Access\AccessResolver;
use Webtack\Ecms\Models\AdminMenuEntry;
use Webtack\Ecms\Models\ContentNode;
use Webtack\Ecms\Models\ContentType;
use Webtack\Ecms\Models\Role;
use Webtack\Ecms\Models\UserFavoriteType;
use Webtack\Ecms\Tests\Fixtures\User;
use Webtack\Ecms\Tests\TestCase;

class SidebarNavigationTest extends TestCase
{
    use RefreshDatabase;

    private User $editor;

    private Role $role;

    protected function setUp(): void
    {
        parent::setUp();

        ContentType::create(['key' => 'a', 'name' => 'A']);
        ContentType::create(['key' => 'b', 'name' => 'B', 'kind' => 'page']);
        $this->role = Role::factory()->withPermissions(['node:a' => 'any', 'node:b' => 'own'])->create();
        $this->editor = User::factory()->withRole($this->role)->create();

        $group = AdminMenuEntry::create(['title' => ['default' => 'Group', 'uk' => 'Група'], 'position' => 1]);
        AdminMenuEntry::create(['type_key' => 'a', 'position' => 0]);
        AdminMenuEntry::create(['type_key' => 'b', 'parent_id' => $group->id, 'position' => 0]);
    }

    public function test_the_menu_and_favorites_are_shared_with_links_and_kinds(): void
    {
        UserFavoriteType::create(['user_id' => $this->editor->id, 'type_key' => 'b']);
        UserFavoriteType::create(['user_id' => $this->editor->id, 'type_key' => 'a']);

        $this->actingAs($this->editor)->get('/admin/dashboard')->assertInertia(fn (Assert $page) => $page
            ->where('navigation.favorites.0.key', 'b')
            ->where('navigation.favorites.0.kind', 'page')
            ->where('navigation.favorites.0.href', '/admin/page-types/b/nodes')
            ->where('navigation.favorites.1.key', 'a')
            ->where('navigation.menu.0.type', 'link')
            ->where('navigation.menu.0.href', '/admin/content-types/a/nodes')
            ->where('navigation.menu.1.type', 'group')
            ->where('navigation.menu.1.title', 'Group')
            ->where('navigation.menu.1.children.0.key', 'b'));
    }

    public function test_the_group_title_follows_the_locale_with_a_fallback(): void
    {
        $this->editor->forceFill(['locale' => 'uk'])->save();

        $this->actingAs($this->editor)->get('/admin/dashboard')
            ->assertInertia(fn (Assert $page) => $page->where('navigation.menu.1.title', 'Група'));
    }

    public function test_the_order_of_favorites_is_the_order_of_adding(): void
    {
        UserFavoriteType::create(['user_id' => $this->editor->id, 'type_key' => 'a']);
        UserFavoriteType::create(['user_id' => $this->editor->id, 'type_key' => 'b']);

        $this->actingAs($this->editor)->get('/admin/dashboard')
            ->assertInertia(fn (Assert $page) => $page->where('navigation.favorites', fn ($items) => collect($items)->pluck('key')->all() === ['a', 'b']));
    }

    public function test_a_revoked_type_disappears_and_returns_with_the_access(): void
    {
        UserFavoriteType::create(['user_id' => $this->editor->id, 'type_key' => 'a']);
        $this->role->permissions()->where('scope', 'node:a')->update(['level' => 'none']);

        $this->actingAs($this->editor)->get('/admin/dashboard')->assertInertia(fn (Assert $page) => $page
            ->has('navigation.favorites', 0)
            ->where('navigation.menu', fn ($items) => collect($items)->pluck('type')->all() === ['group']));
        $this->assertSame(1, UserFavoriteType::count());
        $this->assertSame(3, AdminMenuEntry::count());

        $this->role->permissions()->where('scope', 'node:a')->update(['level' => 'any']);
        // One user object serves both requests of the test; a real request loads the permissions afresh.
        app(AccessResolver::class)->flush();

        $this->get('/admin/dashboard')->assertInertia(fn (Assert $page) => $page
            ->has('navigation.favorites', 1)
            ->where('navigation.menu', fn ($items) => collect($items)->pluck('type')->all() === ['link', 'group']));
    }

    public function test_a_group_without_visible_links_is_hidden(): void
    {
        $this->role->permissions()->where('scope', 'node:b')->update(['level' => 'none']);

        $this->actingAs($this->editor)->get('/admin/dashboard')
            ->assertInertia(fn (Assert $page) => $page->where('navigation.menu', fn ($items) => collect($items)->pluck('type')->all() === ['link']));
    }

    public function test_a_deleted_type_leaves_the_menu_and_favorites(): void
    {
        UserFavoriteType::create(['user_id' => $this->editor->id, 'type_key' => 'b']);
        ContentType::where('key', 'b')->first()->delete();

        $this->actingAs($this->editor)->get('/admin/dashboard')->assertInertia(fn (Assert $page) => $page
            ->has('navigation.favorites', 0)
            ->where('navigation.menu', fn ($items) => collect($items)->pluck('type')->all() === ['link']));
    }

    public function test_the_administrator_sees_every_type(): void
    {
        $this->actingAs(User::factory()->create())->get('/admin/dashboard')
            ->assertInertia(fn (Assert $page) => $page->where('navigation.menu', fn ($items) => collect($items)->pluck('type')->all() === ['link', 'group']));
    }

    /** A node of a type with a `title` value in a language, linked from the menu at the root (after the setUp items). */
    private function nodeLink(string $type, ?int $authorId = null, ?string $title = 'Node', string $lang = 'default', ?int $position = null): ContentNode
    {
        $node = ContentNode::factory()->create(['content_type' => $type, 'user_id' => $authorId ?? User::factory()->create()->id]);

        if ($title !== null) {
            DB::table('content_values_string')->insert([
                'content_node_id' => $node->id, 'content_node_uuid' => $node->uuid, 'content_type' => $type,
                'field_key' => 'title', 'lang' => $lang, 'weight' => 0, 'value' => $title,
            ]);
        }

        AdminMenuEntry::create(['node_uuid' => $node->uuid, 'position' => $position ?? 10 + AdminMenuEntry::count()]);

        return $node;
    }

    /** @return list<array<string, mixed>> The menu items the user gets. */
    private function menuFor(User $user): array
    {
        $menu = [];

        $this->actingAs($user)->get('/admin/dashboard')->assertInertia(function (Assert $page) use (&$menu) {
            $menu = $page->toArray()['props']['navigation']['menu'];
        });

        return $menu;
    }

    public function test_a_node_link_has_the_title_and_the_edit_href(): void
    {
        $node = $this->nodeLink('a', title: 'About us');

        $link = collect($this->menuFor($this->editor))->firstWhere('node', true);

        $this->assertSame(['link', true, 'About us', 'content', "/admin/content-types/a/nodes/{$node->uuid}/edit"], [$link['type'], $link['node'], $link['name'], $link['kind'], $link['href']]);
    }

    public function test_a_page_type_node_links_into_its_own_section(): void
    {
        $node = $this->nodeLink('b', $this->editor->id);

        $link = collect($this->menuFor($this->editor))->firstWhere('node', true);

        $this->assertSame(["/admin/page-types/b/nodes/{$node->uuid}/edit", 'page'], [$link['href'], $link['kind']]);
    }

    public function test_the_node_link_follows_the_edit_right_per_level(): void
    {
        $this->nodeLink('a', title: 'Any level, another author');
        $this->nodeLink('b', $this->editor->id, 'Own, written by me');
        $this->nodeLink('b', title: 'Own, written by another');
        ContentType::create(['key' => 'c', 'name' => 'C']);
        $this->nodeLink('c', $this->editor->id, 'No access to the type');

        $names = collect($this->menuFor($this->editor))->where('node', true)->pluck('name')->all();

        $this->assertSame(['Any level, another author', 'Own, written by me'], $names);
    }

    public function test_the_sidebar_decision_matches_the_node_policy(): void
    {
        $others = [
            $this->nodeLink('a'),
            $this->nodeLink('b'),
            $this->nodeLink('b', $this->editor->id),
        ];
        $shown = collect($this->menuFor($this->editor))->where('node', true)->pluck('key')->all();

        foreach ($others as $node) {
            $this->assertSame(Gate::forUser($this->editor)->allows('update', $node), in_array($node->uuid, $shown, true));
        }
    }

    public function test_a_group_with_only_hidden_node_links_is_hidden(): void
    {
        $group = AdminMenuEntry::create(['title' => ['default' => 'Only nodes'], 'position' => 5]);
        $node = $this->nodeLink('b');
        AdminMenuEntry::where('node_uuid', $node->uuid)->update(['parent_id' => $group->id, 'position' => 0]);

        $titles = collect($this->menuFor($this->editor))->where('type', 'group')->pluck('title')->all();

        $this->assertSame(['Group'], $titles);
        $this->assertSame(1, AdminMenuEntry::where('node_uuid', $node->uuid)->count());
    }

    public function test_a_node_link_inside_a_group_is_a_child(): void
    {
        $node = $this->nodeLink('a', title: 'Inside');
        AdminMenuEntry::where('node_uuid', $node->uuid)->update(['parent_id' => AdminMenuEntry::where('type_key', null)->where('node_uuid', null)->value('id'), 'position' => 1]);

        $group = collect($this->menuFor($this->editor))->firstWhere('type', 'group');

        $this->assertSame(['b', $node->uuid], collect($group['children'])->pluck('key')->all());
        $this->assertTrue($group['children'][1]['node']);
    }

    public function test_the_label_follows_the_node_title_the_locale_and_the_slug(): void
    {
        $node = $this->nodeLink('a', title: 'Old');
        $label = fn () => collect($this->menuFor($this->editor))->firstWhere('key', $node->uuid)['name'];

        $this->assertSame('Old', $label());

        DB::table('content_values_string')->where('content_node_id', $node->id)->update(['value' => 'Renamed']);
        $this->assertSame('Renamed', $label());

        DB::table('content_values_string')->insert([
            'content_node_id' => $node->id, 'content_node_uuid' => $node->uuid, 'content_type' => 'a',
            'field_key' => 'title', 'lang' => 'uk', 'weight' => 0, 'value' => 'Перейменована',
        ]);
        $this->editor->forceFill(['locale' => 'uk'])->save();
        $this->assertSame('Перейменована', $label());

        DB::table('content_values_string')->where('content_node_id', $node->id)->delete();
        $this->assertSame($node->slug, $label());
    }

    public function test_a_deleted_node_leaves_the_menu(): void
    {
        $node = $this->nodeLink('a');

        $node->delete();

        $this->assertSame([], collect($this->menuFor($this->editor))->where('node', true)->all());
    }

    public function test_the_query_count_does_not_grow_with_the_number_of_node_links(): void
    {
        $count = function (): int {
            DB::flushQueryLog();
            DB::enableQueryLog();
            $this->actingAs($this->editor)->get('/admin/dashboard')->assertOk();

            return count(DB::getQueryLog());
        };

        $this->nodeLink('a');
        $count(); // warms the role and settings caches, which the first request fills
        $before = $count();

        foreach (range(1, 6) as $_) {
            $this->nodeLink('a');
        }

        $this->assertSame($before, $count());
    }
}
