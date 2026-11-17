<?php

namespace Webtack\Ecms\Tests\Feature\Navigation;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Webtack\Ecms\Models\AdminMenuEntry;
use Webtack\Ecms\Models\ContentNode;
use Webtack\Ecms\Models\ContentType;
use Webtack\Ecms\Models\Role;
use Webtack\Ecms\Tests\Fixtures\User;
use Webtack\Ecms\Tests\TestCase;

class AdminMenuTest extends TestCase
{
    use RefreshDatabase;

    private const BASE = '/admin/settings/navigation';

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['a', 'b', 'c'] as $key) {
            ContentType::create(['key' => $key, 'name' => strtoupper($key)]);
        }

        $this->actingAs(User::factory()->create());
    }

    public function test_the_page_needs_the_settings_permission(): void
    {
        $this->actingAs(User::factory()->withRole(Role::factory()->withPermissions(['menus' => 'allow'])->create())->create());

        $this->get(self::BASE)->assertForbidden();
        $this->post(self::BASE.'/groups', ['title' => 'X'])->assertForbidden();
        $this->post(self::BASE.'/links', ['type_key' => 'a'])->assertForbidden();
        $this->put(self::BASE.'/order', ['items' => []])->assertForbidden();
        $this->assertSame(0, AdminMenuEntry::count());
    }

    public function test_a_group_is_created_and_renamed(): void
    {
        $this->post(self::BASE.'/groups', ['title' => 'Catalog'])->assertRedirect();
        $group = AdminMenuEntry::first();
        $this->assertSame(['default' => 'Catalog'], $group->title);

        $this->put(self::BASE."/groups/{$group->id}", ['title' => 'Shop'])->assertRedirect();
        $this->assertSame('Shop', $group->fresh()->title['default']);
    }

    public function test_a_blank_group_title_is_rejected(): void
    {
        $this->post(self::BASE.'/groups', ['title' => '  '])->assertSessionHasErrors('title');
        $this->assertSame(0, AdminMenuEntry::count());
    }

    public function test_links_go_to_the_root_or_a_group_and_a_type_only_once(): void
    {
        $this->post(self::BASE.'/groups', ['title' => 'G']);
        $group = AdminMenuEntry::first();

        $this->post(self::BASE.'/links', ['type_key' => 'a']);
        $this->post(self::BASE.'/links', ['type_key' => 'b', 'parent_id' => $group->id]);
        $this->post(self::BASE.'/links', ['type_key' => 'a', 'parent_id' => $group->id])->assertSessionHasErrors('type_key');
        $this->post(self::BASE.'/links', ['type_key' => 'zzz'])->assertSessionHasErrors('type_key');

        $this->assertSame(3, AdminMenuEntry::count());
        $this->assertSame($group->id, AdminMenuEntry::where('type_key', 'b')->value('parent_id'));
    }

    public function test_the_structure_is_saved_with_order_and_parents(): void
    {
        $this->post(self::BASE.'/groups', ['title' => 'G']);
        $group = AdminMenuEntry::first();
        $this->post(self::BASE.'/links', ['type_key' => 'a']);
        $this->post(self::BASE.'/links', ['type_key' => 'b']);
        $a = AdminMenuEntry::where('type_key', 'a')->first();
        $b = AdminMenuEntry::where('type_key', 'b')->first();

        $this->put(self::BASE.'/order', ['items' => [
            ['id' => $b->id],
            ['id' => $group->id, 'children' => [$a->id]],
        ]])->assertRedirect();

        $this->assertSame([0, null], [$b->fresh()->position, $b->fresh()->parent_id]);
        $this->assertSame([1, null], [$group->fresh()->position, $group->fresh()->parent_id]);
        $this->assertSame([0, $group->id], [$a->fresh()->position, $a->fresh()->parent_id]);
    }

    public function test_a_group_cannot_be_nested_and_nothing_changes(): void
    {
        $this->post(self::BASE.'/groups', ['title' => 'One']);
        $this->post(self::BASE.'/groups', ['title' => 'Two']);
        [$one, $two] = AdminMenuEntry::orderBy('id')->get()->all();

        $this->put(self::BASE.'/order', ['items' => [['id' => $one->id, 'children' => [$two->id]]]])
            ->assertSessionHasErrors('items');

        $this->assertNull($two->fresh()->parent_id);
    }

    public function test_the_structure_must_list_every_entry_once(): void
    {
        $this->post(self::BASE.'/groups', ['title' => 'G']);
        $this->post(self::BASE.'/links', ['type_key' => 'a']);
        $group = AdminMenuEntry::first();

        $this->put(self::BASE.'/order', ['items' => [['id' => $group->id]]])->assertSessionHasErrors('items');
        $this->put(self::BASE.'/order', ['items' => [['id' => $group->id], ['id' => $group->id]]])->assertSessionHasErrors('items');
    }

    public function test_deleting_a_group_deletes_its_links_but_not_the_types(): void
    {
        $this->post(self::BASE.'/groups', ['title' => 'G']);
        $group = AdminMenuEntry::first();
        $this->post(self::BASE.'/links', ['type_key' => 'a', 'parent_id' => $group->id]);

        $this->delete(self::BASE."/entries/{$group->id}")->assertRedirect();

        $this->assertSame(0, AdminMenuEntry::count());
        $this->assertSame(3, ContentType::count());
    }

    public function test_deleting_a_type_removes_its_link_only(): void
    {
        $this->post(self::BASE.'/groups', ['title' => 'G']);
        $this->post(self::BASE.'/links', ['type_key' => 'a']);
        $this->post(self::BASE.'/links', ['type_key' => 'b']);

        ContentType::where('key', 'a')->first()->delete();

        $this->assertSame([null, 'b'], AdminMenuEntry::orderBy('id')->pluck('type_key')->all());
    }

    public function test_deleting_a_node_removes_its_link_only(): void
    {
        $node = ContentNode::factory()->create(['content_type' => 'a']);
        $other = ContentNode::factory()->create(['content_type' => 'a']);
        AdminMenuEntry::create(['node_uuid' => $node->uuid, 'position' => 0]);
        AdminMenuEntry::create(['node_uuid' => $other->uuid, 'position' => 1]);

        $node->delete();

        $this->assertSame([$other->uuid], AdminMenuEntry::pluck('node_uuid')->all());
    }

    public function test_entry_kinds_are_told_apart(): void
    {
        $node = ContentNode::factory()->create(['content_type' => 'a']);
        $group = AdminMenuEntry::create(['title' => ['default' => 'G']]);
        $type = AdminMenuEntry::create(['type_key' => 'a']);
        $link = AdminMenuEntry::create(['node_uuid' => $node->uuid]);

        $this->assertSame([true, false, false], [$group->isGroup(), $group->isTypeLink(), $group->isNodeLink()]);
        $this->assertSame([false, true, false], [$type->isGroup(), $type->isTypeLink(), $type->isNodeLink()]);
        $this->assertSame([false, false, true], [$link->isGroup(), $link->isTypeLink(), $link->isNodeLink()]);
    }

    public function test_a_node_link_goes_to_the_root_or_a_group_and_a_node_only_once(): void
    {
        $this->post(self::BASE.'/groups', ['title' => 'G']);
        $group = AdminMenuEntry::first();
        $first = ContentNode::factory()->create(['content_type' => 'a']);
        $second = ContentNode::factory()->create(['content_type' => 'a']);

        $this->post(self::BASE.'/links', ['node_uuid' => $first->uuid])->assertRedirect();
        $this->post(self::BASE.'/links', ['node_uuid' => $second->uuid, 'parent_id' => $group->id])->assertRedirect();
        $this->post(self::BASE.'/links', ['node_uuid' => $first->uuid, 'parent_id' => $group->id])->assertSessionHasErrors('node_uuid');

        $this->assertSame(3, AdminMenuEntry::count());
        $this->assertNull(AdminMenuEntry::where('node_uuid', $first->uuid)->value('parent_id'));
        $this->assertSame($group->id, AdminMenuEntry::where('node_uuid', $second->uuid)->value('parent_id'));
    }

    public function test_an_unknown_node_is_rejected(): void
    {
        $this->post(self::BASE.'/links', ['node_uuid' => '0190a1b2-0000-7000-8000-000000000000'])->assertSessionHasErrors('node_uuid');

        $this->assertSame(0, AdminMenuEntry::count());
    }

    public function test_a_node_link_cannot_go_into_a_nested_group_or_a_link(): void
    {
        $node = ContentNode::factory()->create(['content_type' => 'a']);
        $link = AdminMenuEntry::create(['type_key' => 'a']);

        $this->post(self::BASE.'/links', ['node_uuid' => $node->uuid, 'parent_id' => $link->id])->assertSessionHasErrors('title');

        $nested = AdminMenuEntry::create(['title' => ['default' => 'N'], 'parent_id' => AdminMenuEntry::create(['title' => ['default' => 'P']])->id]);
        $this->post(self::BASE.'/links', ['node_uuid' => $node->uuid, 'parent_id' => $nested->id])->assertSessionHasErrors('parent_id');

        $this->assertSame(0, AdminMenuEntry::where('node_uuid', $node->uuid)->count());
    }

    public function test_a_type_and_one_of_its_nodes_can_both_be_in_the_menu(): void
    {
        $node = ContentNode::factory()->create(['content_type' => 'a']);

        $this->post(self::BASE.'/links', ['type_key' => 'a'])->assertRedirect();
        $this->post(self::BASE.'/links', ['node_uuid' => $node->uuid])->assertRedirect();

        $this->assertSame(2, AdminMenuEntry::count());
    }

    public function test_a_link_needs_exactly_one_of_type_and_node(): void
    {
        $node = ContentNode::factory()->create(['content_type' => 'a']);

        $this->post(self::BASE.'/links', [])->assertSessionHasErrors(['type_key', 'node_uuid']);
        $this->post(self::BASE.'/links', ['type_key' => 'a', 'node_uuid' => $node->uuid])->assertSessionHasErrors();

        $this->assertSame(0, AdminMenuEntry::count());
    }

    public function test_node_links_are_reordered_among_groups_and_the_root(): void
    {
        $group = AdminMenuEntry::create(['title' => ['default' => 'G']]);
        $type = AdminMenuEntry::create(['type_key' => 'a', 'position' => 1]);
        $nodeA = AdminMenuEntry::create(['node_uuid' => ContentNode::factory()->create(['content_type' => 'a'])->uuid, 'position' => 2]);
        $nodeB = AdminMenuEntry::create(['node_uuid' => ContentNode::factory()->create(['content_type' => 'a'])->uuid, 'position' => 3]);

        $this->put(self::BASE.'/order', ['items' => [
            ['id' => $nodeB->id],
            ['id' => $group->id, 'children' => [$nodeA->id, $type->id]],
        ]])->assertRedirect();

        $this->assertSame([0, null], [$nodeB->fresh()->position, $nodeB->fresh()->parent_id]);
        $this->assertSame([0, $group->id], [$nodeA->fresh()->position, $nodeA->fresh()->parent_id]);
        $this->assertSame([1, $group->id], [$type->fresh()->position, $type->fresh()->parent_id]);
    }

    public function test_the_order_must_list_every_node_link(): void
    {
        $group = AdminMenuEntry::create(['title' => ['default' => 'G']]);
        AdminMenuEntry::create(['node_uuid' => ContentNode::factory()->create(['content_type' => 'a'])->uuid, 'position' => 1]);

        $this->put(self::BASE.'/order', ['items' => [['id' => $group->id]]])->assertSessionHasErrors('items');
    }

    public function test_the_page_lists_node_links_with_title_and_type(): void
    {
        $node = ContentNode::factory()->create(['content_type' => 'b', 'slug' => 'about-us']);
        $titled = ContentNode::factory()->create(['content_type' => 'a']);
        DB::table('content_values_string')->insert([
            'content_node_id' => $titled->id, 'content_node_uuid' => $titled->uuid, 'content_type' => 'a', 'field_key' => 'title', 'lang' => 'default', 'weight' => 0, 'value' => 'About us',
        ]);
        AdminMenuEntry::create(['node_uuid' => $titled->uuid, 'position' => 0]);
        AdminMenuEntry::create(['node_uuid' => $node->uuid, 'position' => 1]);

        $this->get(self::BASE)->assertInertia(fn (Assert $page) => $page
            ->where('tree.0.node_uuid', $titled->uuid)
            ->where('tree.0.node_title', 'About us')
            ->where('tree.0.name', 'A')
            ->where('tree.1.node_title', 'about-us')
            ->where('tree.1.name', 'B')
            ->where('tree.1.type_key', null)
            ->has('availableTypes', 3));
    }

    public function test_the_page_lists_the_tree_and_the_free_types(): void
    {
        $this->post(self::BASE.'/groups', ['title' => 'G']);
        $this->post(self::BASE.'/links', ['type_key' => 'a', 'parent_id' => AdminMenuEntry::first()->id]);

        $this->get(self::BASE)->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Settings/Navigation')
            ->where('tree.0.title', 'G')
            ->where('tree.0.children.0.type_key', 'a')
            ->where('availableTypes.0.key', 'b')
            ->has('availableTypes', 2));
    }
}
