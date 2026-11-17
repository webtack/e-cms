<?php

namespace Webtack\Ecms\Tests\Feature\Access;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Webtack\Ecms\Content\ContentNodeCreator;
use Webtack\Ecms\Models\ContentType;
use Webtack\Ecms\Models\File;
use Webtack\Ecms\Models\MediaRelation;
use Webtack\Ecms\Models\Role;
use Webtack\Ecms\Tests\Fixtures\User;
use Webtack\Ecms\Tests\TestCase;

/**
 * The media library follows the `media` level; the upload and YouTube endpoints need any write level;
 * `lookup/*` are open to every active user.
 */
class MediaAccessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
    }

    private function userWith(array $permissions): User
    {
        return User::factory()->withRole(Role::factory()->withPermissions($permissions)->create())->create();
    }

    public function test_media_level_none_closes_the_library(): void
    {
        $file = File::factory()->create();
        $this->actingAs($this->userWith(['node:x' => 'any']));

        $this->get('/admin/media')->assertForbidden();
        $this->getJson("/admin/media/{$file->uuid}")->assertForbidden();
        $this->delete("/admin/media/{$file->uuid}")->assertForbidden();
        $this->assertNotNull(File::find($file->id));
    }

    public function test_media_level_own_deletes_only_own_files_and_lists_all(): void
    {
        $user = $this->userWith(['media' => 'own']);
        $mine = File::factory()->create(['user_id' => $user->id]);
        $foreign = File::factory()->create(['user_id' => User::factory()->create()->id]);
        $this->actingAs($user);

        $this->get('/admin/media')->assertOk();
        $this->getJson("/admin/media/{$foreign->uuid}")->assertOk();

        $this->delete("/admin/media/{$foreign->uuid}")->assertForbidden();
        $this->assertNotNull(File::find($foreign->id));

        $this->delete("/admin/media/{$mine->uuid}")->assertRedirect();
        $this->assertNull(File::find($mine->id));
    }

    public function test_media_level_any_deletes_a_file_without_owner(): void
    {
        $orphan = File::factory()->create(['user_id' => null]);

        $this->actingAs($this->userWith(['media' => 'any']))->delete("/admin/media/{$orphan->uuid}")->assertRedirect();

        $this->assertNull(File::find($orphan->id));
    }

    public function test_upload_needs_a_write_level_somewhere(): void
    {
        ContentType::create(['key' => 'news', 'name' => 'News']);
        $png = fn () => UploadedFile::fake()->image('a.png', 20, 20);

        $this->actingAs($this->userWith(['menus' => 'allow']))
            ->postJson('/admin/files', ['file' => $png(), 'profile' => 'image'])->assertForbidden();
        $this->assertSame(0, File::count());

        $this->actingAs($this->userWith(['node:news' => 'own']))
            ->postJson('/admin/files', ['file' => $png(), 'profile' => 'image'])->assertCreated();
        $this->actingAs($this->userWith(['media' => 'own']))
            ->postJson('/admin/files', ['file' => $png(), 'profile' => 'image'])->assertCreated();
    }

    public function test_youtube_lookup_needs_a_write_level_somewhere(): void
    {
        $this->actingAs($this->userWith(['menus' => 'allow']))
            ->postJson('/admin/youtube/lookup', ['url' => 'https://youtu.be/dQw4w9WgXcQ'])->assertForbidden();
    }

    public function test_lookups_are_open_to_a_user_without_levels(): void
    {
        ContentType::create(['key' => 'news', 'name' => 'News']);
        $this->actingAs($this->userWith([]));

        $this->getJson('/admin/lookup/content-types?kind=content&q=')->assertOk();
        $this->getJson('/admin/lookup/nodes?content_type=news&q=')->assertOk();
    }

    public function test_the_details_hide_usages_the_viewer_may_not_open(): void
    {
        $type = ContentType::create(['key' => 'secret', 'name' => 'Secret']);
        $node = app(ContentNodeCreator::class)->create($type, ['title' => 'Top secret title'], authorId: User::factory()->create()->id);
        $viewer = $this->userWith(['media' => 'own']);
        $file = File::factory()->create(['user_id' => $viewer->id]);
        MediaRelation::create(['file_uuid' => $file->uuid, 'owner_type' => 'content_node', 'owner_id' => $node->id, 'role' => 'cover', 'lang' => 'default']);

        $response = $this->actingAs($viewer)->getJson("/admin/media/{$file->uuid}")->assertOk()
            ->assertJsonCount(1, 'usages')
            ->assertJsonPath('usages.0', ['restricted' => true, 'owner_type' => '', 'owner_id' => null, 'label' => 'Used elsewhere', 'url' => null, 'role' => '', 'lang' => '']);
        $this->assertStringNotContainsString('Top secret title', $response->getContent());

        // The administrator sees the node as before.
        $this->actingAs(User::factory()->create())->getJson("/admin/media/{$file->uuid}")
            ->assertJsonPath('usages.0.label', 'Top secret title')
            ->assertJsonPath('usages.0.role', 'cover');

        // A file whose only usage is hidden is still in use.
        $this->actingAs($viewer)->delete("/admin/media/{$file->uuid}")->assertSessionHas('error');
        $this->assertNotNull(File::find($file->id));
    }

    public function test_a_node_of_another_author_at_the_level_own_is_shown_without_a_link(): void
    {
        $type = ContentType::create(['key' => 'secret', 'name' => 'Secret']);
        $node = app(ContentNodeCreator::class)->create($type, ['title' => 'Visible title'], authorId: User::factory()->create()->id);
        $viewer = $this->userWith(['media' => 'own', 'node:secret' => 'own']);
        $file = File::factory()->create();
        MediaRelation::create(['file_uuid' => $file->uuid, 'owner_type' => 'content_node', 'owner_id' => $node->id, 'role' => 'cover', 'lang' => 'default']);

        $this->actingAs($viewer)->getJson("/admin/media/{$file->uuid}")
            ->assertJsonPath('usages.0.label', 'Visible title')
            ->assertJsonPath('usages.0.url', null);
    }
}
