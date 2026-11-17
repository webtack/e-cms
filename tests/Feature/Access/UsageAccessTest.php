<?php

namespace Webtack\Ecms\Tests\Feature\Access;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Webtack\Ecms\Content\ContentNodeCreator;
use Webtack\Ecms\Files\UsageAccess;
use Webtack\Ecms\Models\ContentType;
use Webtack\Ecms\Models\MediaRelation;
use Webtack\Ecms\Models\Role;
use Webtack\Ecms\Tests\Fixtures\User;
use Webtack\Ecms\Tests\TestCase;

/**
 * Which places of use a viewer may see in the media details.
 */
class UsageAccessTest extends TestCase
{
    use RefreshDatabase;

    private function userWith(array $permissions): User
    {
        return User::factory()->withRole(Role::factory()->withPermissions($permissions)->create())->create();
    }

    private function usage(string $type, int $id): MediaRelation
    {
        return new MediaRelation(['owner_type' => $type, 'owner_id' => $id, 'role' => 'x', 'lang' => 'default']);
    }

    /** @return array{visible: bool, linkable: bool} */
    private function decide(User $viewer, string $type, int $id = 1): array
    {
        return app(UsageAccess::class)->for($viewer, collect([$this->usage($type, $id)]))[0];
    }

    private function node(int $authorId): int
    {
        $type = ContentType::findByKey('secret') ?? ContentType::create(['key' => 'secret', 'name' => 'Secret']);

        return app(ContentNodeCreator::class)->create($type, ['title' => 'T'], authorId: $authorId)->id;
    }

    public function test_node_levels_none_own_and_any(): void
    {
        $author = User::factory()->create();
        $id = $this->node($author->id);

        $this->assertSame(['visible' => false, 'linkable' => false], $this->decide($this->userWith(['media' => 'own']), 'content_node', $id));

        $own = $this->userWith(['node:secret' => 'own']);
        $this->assertSame(['visible' => true, 'linkable' => false], $this->decide($own, 'content_node', $id));
        $this->assertSame(['visible' => true, 'linkable' => true], $this->decide($own, 'content_node', $this->node($own->id)));

        $this->assertSame(['visible' => true, 'linkable' => true], $this->decide($this->userWith(['node:secret' => 'any']), 'content_node', $id));
    }

    public function test_a_missing_node_is_hidden_from_a_non_administrator(): void
    {
        $this->assertFalse($this->decide($this->userWith(['node:secret' => 'any']), 'content_node', 9999)['visible']);
    }

    public function test_system_owners_follow_their_rights(): void
    {
        $none = $this->userWith(['media' => 'own']);

        foreach (['user' => 'users', 'site_setting' => 'settings', 'taxonomy_term' => 'taxonomy', 'menu_item' => 'menus', 'content_type' => 'types'] as $owner => $right) {
            $this->assertFalse($this->decide($none, $owner)['visible'], "{$owner} hidden without {$right}");
            $this->assertTrue($this->decide($this->userWith([$right => 'allow']), $owner)['visible'], "{$owner} visible with {$right}");
        }
    }

    public function test_an_administrator_sees_every_known_owner_and_nobody_sees_an_unknown_one(): void
    {
        $admin = User::factory()->create();

        foreach (['content_node', 'user', 'site_setting', 'taxonomy_term', 'menu_item', 'content_type'] as $owner) {
            $this->assertTrue($this->decide($admin, $owner)['visible'], $owner);
        }

        $this->assertSame(['visible' => false, 'linkable' => false], $this->decide($admin, 'mystery'));
    }
}
