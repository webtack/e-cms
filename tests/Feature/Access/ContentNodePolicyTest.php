<?php

namespace Webtack\Ecms\Tests\Feature\Access;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Webtack\Ecms\Content\ContentNodeCreator;
use Webtack\Ecms\Models\ContentNode;
use Webtack\Ecms\Models\ContentType;
use Webtack\Ecms\Models\Role;
use Webtack\Ecms\Tests\Fixtures\User;
use Webtack\Ecms\Tests\TestCase;

class ContentNodePolicyTest extends TestCase
{
    use RefreshDatabase;

    private function userWith(array $permissions): User
    {
        return User::factory()->withRole(Role::factory()->withPermissions($permissions)->create())->create();
    }

    private function node(ContentType $type, User $author): ContentNode
    {
        return app(ContentNodeCreator::class)->create($type, ['title' => 'T'], authorId: $author->id);
    }

    public function test_levels_decide_by_author(): void
    {
        $type = ContentType::create(['key' => 'news', 'name' => 'News']);
        $author = User::factory()->create();
        $mine = $this->userWith(['node:news' => 'own']);
        $own = $this->node($type, $mine);
        $foreign = $this->node($type, $author);

        $gate = Gate::forUser($mine);
        $this->assertTrue($gate->allows('viewAny', [ContentNode::class, $type]));
        $this->assertTrue($gate->allows('create', [ContentNode::class, $type]));
        $this->assertTrue($gate->allows('update', $own));
        $this->assertTrue($gate->allows('delete', $own));
        $this->assertFalse($gate->allows('update', $foreign));
        $this->assertFalse($gate->allows('delete', $foreign));

        $any = Gate::forUser($this->userWith(['node:news' => 'any']));
        $this->assertTrue($any->allows('update', $foreign));
        $this->assertTrue($any->allows('delete', $foreign));
    }

    public function test_none_denies_everything(): void
    {
        $type = ContentType::create(['key' => 'news', 'name' => 'News']);
        $user = $this->userWith(['node:other' => 'any']);
        $node = $this->node($type, $user);

        $gate = Gate::forUser($user);
        $this->assertFalse($gate->allows('viewAny', [ContentNode::class, $type]));
        $this->assertFalse($gate->allows('create', [ContentNode::class, $type]));
        $this->assertFalse($gate->allows('update', $node));
        $this->assertFalse($gate->allows('delete', $node));
    }

    public function test_admin_may_everything(): void
    {
        $type = ContentType::create(['key' => 'news', 'name' => 'News']);
        $node = $this->node($type, User::factory()->create());

        $this->assertTrue(Gate::forUser(User::factory()->create())->allows('update', $node));
    }
}
