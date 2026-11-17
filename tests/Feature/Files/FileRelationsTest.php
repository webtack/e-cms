<?php

namespace Webtack\Ecms\Tests\Feature\Files;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Webtack\Ecms\Files\FileRelations;
use Webtack\Ecms\Models\File;
use Webtack\Ecms\Models\MediaRelation;
use Webtack\Ecms\Tests\TestCase;

class FileRelationsTest extends TestCase
{
    use RefreshDatabase;

    private FileRelations $relations;

    protected function setUp(): void
    {
        parent::setUp();

        $this->relations = new FileRelations;
    }

    /** @return list<string> */
    private function uuids(int $ownerId = 1, string $role = 'cover', string $lang = 'default'): array
    {
        return MediaRelation::query()
            ->where(['owner_type' => 'content_node', 'owner_id' => $ownerId, 'role' => $role, 'lang' => $lang])
            ->orderBy('id')
            ->pluck('file_uuid')
            ->all();
    }

    public function test_sync_binds_a_file(): void
    {
        $file = File::factory()->create();

        $this->relations->sync('content_node', 1, 'cover', 'default', [$file->uuid]);

        $this->assertSame([$file->uuid], $this->uuids());
    }

    public function test_sync_replaces_one_file_with_another(): void
    {
        [$a, $b] = File::factory()->count(2)->create();
        $this->relations->sync('content_node', 1, 'cover', 'default', [$a->uuid]);

        $this->relations->sync('content_node', 1, 'cover', 'default', [$b->uuid]);

        $this->assertSame([$b->uuid], $this->uuids());
        $this->assertDatabaseHas('media', ['uuid' => $a->uuid]);
    }

    public function test_sync_with_an_empty_list_clears_the_role(): void
    {
        $file = File::factory()->create();
        $this->relations->sync('content_node', 1, 'cover', 'default', [$file->uuid]);

        $this->relations->sync('content_node', 1, 'cover', 'default', []);

        $this->assertSame([], $this->uuids());
        $this->assertDatabaseHas('media', ['uuid' => $file->uuid]);
    }

    public function test_sync_keeps_several_files_and_ignores_duplicates(): void
    {
        [$a, $b, $c] = File::factory()->count(3)->create();

        $this->relations->sync('content_node', 1, 'gallery', 'default', [$a->uuid, $b->uuid, $c->uuid, $a->uuid]);

        $this->assertEqualsCanonicalizing([$a->uuid, $b->uuid, $c->uuid], $this->uuids(role: 'gallery'));
    }

    public function test_sync_is_idempotent(): void
    {
        $file = File::factory()->create();
        $this->relations->sync('content_node', 1, 'cover', 'default', [$file->uuid]);
        $id = MediaRelation::query()->value('id');

        $this->relations->sync('content_node', 1, 'cover', 'default', [$file->uuid]);

        $this->assertSame(1, MediaRelation::query()->count());
        $this->assertSame($id, MediaRelation::query()->value('id'));
    }

    public function test_sync_touches_only_its_own_owner_role_and_language(): void
    {
        $file = File::factory()->create();
        $this->relations->sync('content_node', 2, 'cover', 'default', [$file->uuid]);
        $this->relations->sync('content_node', 1, 'other', 'default', [$file->uuid]);
        $this->relations->sync('content_node', 1, 'cover', 'uk', [$file->uuid]);

        $this->relations->sync('content_node', 1, 'cover', 'default', []);

        $this->assertSame(3, MediaRelation::query()->count());
    }

    public function test_forget_owner_removes_all_its_relations_and_keeps_files(): void
    {
        $file = File::factory()->create();
        $this->relations->sync('content_node', 1, 'cover', 'default', [$file->uuid]);
        $this->relations->sync('content_node', 1, 'gallery', 'uk', [$file->uuid]);
        $this->relations->sync('content_node', 2, 'cover', 'default', [$file->uuid]);

        $this->relations->forgetOwner('content_node', 1);

        $this->assertSame([2], MediaRelation::query()->pluck('owner_id')->all());
        $this->assertDatabaseHas('media', ['uuid' => $file->uuid]);
    }

    public function test_forget_role_can_be_limited_to_some_owners(): void
    {
        $file = File::factory()->create();
        foreach ([1, 2, 3] as $owner) {
            $this->relations->sync('content_node', $owner, 'cover', 'default', [$file->uuid]);
        }
        $this->relations->sync('content_node', 1, 'gallery', 'default', [$file->uuid]);

        $this->relations->forgetRole('content_node', 'cover', [1, 2]);

        $this->assertSame([3], MediaRelation::query()->where('role', 'cover')->pluck('owner_id')->all());
        $this->assertSame(1, MediaRelation::query()->where('role', 'gallery')->count());

        $this->relations->forgetRole('content_node', 'cover');

        $this->assertSame(0, MediaRelation::query()->where('role', 'cover')->count());
    }

    public function test_usage_counts_include_unused_files_as_zero(): void
    {
        [$used, $twice, $unused] = File::factory()->count(3)->create();
        $this->relations->sync('content_node', 1, 'cover', 'default', [$used->uuid, $twice->uuid]);
        $this->relations->sync('content_node', 2, 'cover', 'default', [$twice->uuid]);

        $this->assertSame(
            [$used->uuid => 1, $twice->uuid => 2, $unused->uuid => 0],
            $this->relations->usageCounts([$used->uuid, $twice->uuid, $unused->uuid]),
        );
    }

    public function test_usages_lists_the_relations_of_a_file(): void
    {
        $file = File::factory()->create();
        $this->relations->sync('content_node', 1, 'cover', 'default', [$file->uuid]);
        $this->relations->sync('content_node', 2, 'cover', 'uk', [$file->uuid]);

        $usages = $this->relations->usages($file->uuid);

        $this->assertSame([1, 2], $usages->pluck('owner_id')->all());
        $this->assertSame(['default', 'uk'], $usages->pluck('lang')->all());
    }
}
