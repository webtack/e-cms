<?php

namespace Webtack\Ecms\Tests\Feature\Files;

use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Webtack\Ecms\Models\File;
use Webtack\Ecms\Models\MediaRelation;
use Webtack\Ecms\Tests\TestCase;

class MediaRelationTest extends TestCase
{
    use RefreshDatabase;

    private function relate(File $file, int $ownerId = 1, string $role = 'cover', string $lang = 'default'): MediaRelation
    {
        return MediaRelation::query()->create([
            'file_uuid' => $file->uuid,
            'owner_type' => 'content_node',
            'owner_id' => $ownerId,
            'role' => $role,
            'lang' => $lang,
        ]);
    }

    public function test_the_table_has_the_documented_columns(): void
    {
        $this->assertEqualsCanonicalizing(
            ['id', 'file_uuid', 'owner_type', 'owner_id', 'role', 'lang', 'created_at', 'updated_at'],
            Schema::getColumnListing('media_relations'),
        );
    }

    public function test_the_same_relation_cannot_exist_twice(): void
    {
        $file = File::factory()->create();
        $this->relate($file);

        $this->expectException(QueryException::class);
        $this->relate($file);
    }

    public function test_relations_differing_in_owner_role_or_language_coexist(): void
    {
        $file = File::factory()->create();

        $this->relate($file, 1);
        $this->relate($file, 2);
        $this->relate($file, 1, 'gallery');
        $this->relate($file, 1, 'cover', 'uk');

        $this->assertCount(4, $file->relations);
    }

    public function test_a_file_with_a_relation_cannot_be_deleted(): void
    {
        $file = File::factory()->create();
        $this->relate($file);

        try {
            $file->delete();
            $this->fail('A used file must not be deletable.');
        } catch (QueryException) {
            $this->assertDatabaseHas('media', ['uuid' => $file->uuid]);
        }
    }

    public function test_a_file_without_relations_can_be_deleted(): void
    {
        $file = File::factory()->create();

        $file->delete();

        $this->assertDatabaseMissing('media', ['uuid' => $file->uuid]);
    }

    public function test_a_file_is_found_from_its_relation_and_back(): void
    {
        $file = File::factory()->create();
        $relation = $this->relate($file);

        $this->assertTrue($relation->file->is($file));
        $this->assertTrue($file->relations->first()->is($relation));
    }
}
