<?php

namespace Webtack\Ecms\Tests\Feature\Access;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Webtack\Ecms\Content\ContentNodeCreator;
use Webtack\Ecms\Content\ValueOwner;
use Webtack\Ecms\Files\AttachedFileGuard;
use Webtack\Ecms\Models\ContentField;
use Webtack\Ecms\Models\ContentNode;
use Webtack\Ecms\Models\ContentType;
use Webtack\Ecms\Models\File;
use Webtack\Ecms\Models\MediaRelation;
use Webtack\Ecms\Models\Role;
use Webtack\Ecms\Tests\Fixtures\User;
use Webtack\Ecms\Tests\TestCase;

/**
 * The guard compares the files of a node before and after a write and refuses new ones the user may not attach.
 */
class AttachedFileGuardTest extends TestCase
{
    use RefreshDatabase;

    private ContentNode $node;

    protected function setUp(): void
    {
        parent::setUp();

        $type = ContentType::create(['key' => 'news', 'name' => 'News']);
        ContentField::create(['content_type' => 'news', 'key' => 'cover', 'name' => 'Cover', 'type' => 'image', 'weight' => 10]);
        $this->node = app(ContentNodeCreator::class)->create($type, ['title' => 'A'], authorId: User::factory()->create()->id);
    }

    private function userWith(array $permissions): User
    {
        return User::factory()->withRole(Role::factory()->withPermissions($permissions)->create())->create();
    }

    private function file(?int $userId): File
    {
        $uuid = File::newUuid();

        return File::factory()->create(['uuid' => $uuid, 'profile' => 'image', 'mime' => 'image/png', 'path' => "{$uuid}.png", 'user_id' => $userId]);
    }

    private function attach(File $file, string $role = 'cover'): void
    {
        MediaRelation::create(['file_uuid' => $file->uuid, 'owner_type' => ContentNode::MEDIA_OWNER, 'owner_id' => $this->node->id, 'role' => $role, 'lang' => 'default']);
    }

    private function check(User $user, array $before = []): void
    {
        app(AttachedFileGuard::class)->assertAllowed($user, ValueOwner::forNode($this->node), $before);
    }

    public function test_the_snapshot_lists_the_files_of_the_owner(): void
    {
        $file = $this->file(null);
        $this->attach($file);

        $this->assertSame([$file->uuid], app(AttachedFileGuard::class)->snapshot(ValueOwner::forNode($this->node)));
    }

    public function test_an_own_upload_is_accepted(): void
    {
        $user = $this->userWith(['node:news' => 'own']);
        $this->attach($this->file($user->id));

        $this->check($user);
        $this->addToAssertionCount(1);
    }

    public function test_a_foreign_file_is_refused_under_the_role_of_the_field(): void
    {
        $user = $this->userWith(['node:news' => 'own']);
        $this->attach($this->file(User::factory()->create()->id));

        try {
            $this->check($user);
            $this->fail('A foreign file must be refused.');
        } catch (ValidationException $e) {
            $this->assertSame(['cover'], array_keys($e->errors()));
        }
    }

    public function test_a_file_already_in_the_node_is_accepted(): void
    {
        $user = $this->userWith(['node:news' => 'any']);
        $file = $this->file(User::factory()->create()->id);
        $this->attach($file);

        $this->check($user, [$file->uuid]);
        $this->addToAssertionCount(1);
    }

    public function test_media_any_accepts_any_file_and_media_own_only_own_files(): void
    {
        $foreign = $this->file(User::factory()->create()->id);
        $this->attach($foreign);

        $this->check($this->userWith(['node:news' => 'own', 'media' => 'any']));

        $this->expectException(ValidationException::class);
        $this->check($this->userWith(['node:news' => 'own', 'media' => 'own']));
    }

    public function test_media_own_accepts_the_users_upload(): void
    {
        $user = $this->userWith(['node:news' => 'own', 'media' => 'own']);
        $this->attach($this->file($user->id));

        $this->check($user);
        $this->addToAssertionCount(1);
    }

    public function test_a_file_without_an_owner_needs_media_any(): void
    {
        $this->attach($this->file(null));

        $this->check($this->userWith(['node:news' => 'own', 'media' => 'any']));

        $this->expectException(ValidationException::class);
        $this->check($this->userWith(['node:news' => 'own', 'media' => 'own']));
    }

    public function test_an_administrator_is_unrestricted(): void
    {
        $this->attach($this->file(User::factory()->create()->id));

        $this->check(User::factory()->create());
        $this->addToAssertionCount(1);
    }
}
