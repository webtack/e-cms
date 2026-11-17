<?php

namespace Webtack\Ecms\Tests\Feature\Access;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Webtack\Ecms\Content\ContentNodeCreator;
use Webtack\Ecms\Models\ContentNode;
use Webtack\Ecms\Models\ContentType;
use Webtack\Ecms\Models\Role;
use Webtack\Ecms\Tests\Fixtures\User;
use Webtack\Ecms\Tests\TestCase;

/**
 * Levels `none` / `own` / `any` on a type decide the list, the forms and every write of its nodes.
 */
class ContentNodeAccessTest extends TestCase
{
    use RefreshDatabase;

    private const URL = '/admin/content-types/news/nodes';

    protected function setUp(): void
    {
        parent::setUp();

        ContentType::create(['key' => 'news', 'name' => 'News']);
    }

    private function userWith(array $permissions): User
    {
        return User::factory()->withRole(Role::factory()->withPermissions($permissions)->create())->create();
    }

    private function node(User $author, string $title = 'Lamp'): ContentNode
    {
        return app(ContentNodeCreator::class)->create(ContentType::findByKey('news'), ['title' => $title], authorId: $author->id);
    }

    private function payload(ContentNode $node): array
    {
        return ['slug' => $node->slug, 'published_at' => '2026-01-01T00:00', 'is_active' => '1', 'values' => ['title' => 'Changed']];
    }

    public function test_level_none_closes_the_list_the_forms_and_the_writes(): void
    {
        $user = $this->userWith(['node:other' => 'any']);
        $node = $this->node(User::factory()->create());
        $this->actingAs($user);

        $this->get(self::URL)->assertForbidden();
        $this->get(self::URL.'/create')->assertForbidden();
        $this->post(self::URL, ['slug' => '', 'values' => ['title' => 'x']])->assertForbidden();
        $this->get(self::URL."/{$node->uuid}/edit")->assertForbidden();
        $this->put(self::URL."/{$node->uuid}", $this->payload($node))->assertForbidden();
        $this->delete(self::URL."/{$node->uuid}")->assertForbidden();

        $this->assertSame(1, ContentNode::count());
    }

    public function test_level_own_changes_only_own_nodes_but_sees_all_in_the_list(): void
    {
        $user = $this->userWith(['node:news' => 'own']);
        $own = $this->node($user, 'Mine');
        $foreign = $this->node(User::factory()->create(), 'Theirs');
        $this->actingAs($user);

        $this->get(self::URL)->assertOk()->assertInertia(fn (Assert $page) => $page
            ->has('table.rows', 2)
            ->where('table.rows', fn ($rows) => collect($rows)->pluck('can_edit', 'uuid')->all() === [$foreign->uuid => false, $own->uuid => true]
                || collect($rows)->pluck('can_edit', 'uuid')->all() === [$own->uuid => true, $foreign->uuid => false]));

        $this->get(self::URL.'/create')->assertOk();
        $this->post(self::URL, ['slug' => '', 'values' => ['title' => 'New']])->assertRedirect();
        $this->assertSame($user->id, ContentNode::where('slug', 'new')->sole()->user_id);

        $this->get(self::URL."/{$own->uuid}/edit")->assertOk();
        $this->put(self::URL."/{$own->uuid}", $this->payload($own))->assertRedirect();
        $this->delete(self::URL."/{$own->uuid}")->assertRedirect();
        $this->assertNull(ContentNode::find($own->id));

        $this->get(self::URL."/{$foreign->uuid}/edit")->assertForbidden();
        $this->put(self::URL."/{$foreign->uuid}", $this->payload($foreign))->assertForbidden();
        $this->delete(self::URL."/{$foreign->uuid}")->assertForbidden();
        $this->assertNotNull(ContentNode::find($foreign->id));
    }

    public function test_the_author_publishes_own_node_with_the_same_right(): void
    {
        $user = $this->userWith(['node:news' => 'own']);
        $node = $this->node($user);

        $this->actingAs($user)->put(self::URL."/{$node->uuid}", [...$this->payload($node), 'published_at' => '2030-05-05T10:30'])->assertRedirect();

        $this->assertSame('2030-05-05 10:30', $node->fresh()->published_at->format('Y-m-d H:i'));
    }

    public function test_level_any_changes_and_deletes_foreign_nodes(): void
    {
        $node = $this->node(User::factory()->create());
        $this->actingAs($this->userWith(['node:news' => 'any']));

        $this->put(self::URL."/{$node->uuid}", $this->payload($node))->assertRedirect();
        $this->delete(self::URL."/{$node->uuid}")->assertRedirect();

        $this->assertNull(ContentNode::find($node->id));
    }

    public function test_a_level_on_another_type_does_not_open_this_one(): void
    {
        ContentType::create(['key' => 'faq', 'name' => 'FAQ']);

        $this->actingAs($this->userWith(['node:faq' => 'any']))->get(self::URL)->assertForbidden();
        $this->get('/admin/content-types/faq/nodes')->assertOk();
    }

    public function test_pages_and_blocks_follow_the_same_levels(): void
    {
        ContentType::create(['key' => 'home', 'name' => 'Home', 'kind' => 'page']);
        ContentType::create(['key' => 'hero', 'name' => 'Hero', 'kind' => 'block']);
        $this->actingAs($this->userWith(['node:home' => 'own']));

        $this->get('/admin/page-types/home/nodes')->assertOk();
        $this->get('/admin/block-types/hero/nodes')->assertForbidden();
    }

    public function test_admin_has_any_level_on_a_new_type(): void
    {
        $this->actingAs(User::factory()->create())->get(self::URL)->assertOk();
    }

    public function test_set_active_follows_the_edit_right(): void
    {
        $user = $this->userWith(['node:news' => 'own']);
        $own = $this->node($user, 'Mine');
        $foreign = $this->node(User::factory()->create(), 'Theirs');
        $this->actingAs($user);

        $this->patch(self::URL."/{$own->uuid}/active", ['is_active' => false])->assertRedirect();
        $this->patch(self::URL."/{$foreign->uuid}/active", ['is_active' => false])->assertForbidden();

        $this->assertFalse($own->fresh()->is_active);
        $this->assertTrue($foreign->fresh()->is_active);
    }

    public function test_set_active_is_closed_at_level_none(): void
    {
        $node = $this->node(User::factory()->create());
        $this->actingAs($this->userWith(['node:other' => 'any']));

        $this->patch(self::URL."/{$node->uuid}/active", ['is_active' => false])->assertForbidden();

        $this->assertTrue($node->fresh()->is_active);
    }
}
