<?php

namespace Webtack\Ecms\Tests\Feature\Files;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Webtack\Ecms\Models\File;
use Webtack\Ecms\Tests\TestCase;

class FileModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_media_table_has_the_documented_columns(): void
    {
        $this->assertEqualsCanonicalizing(
            ['id', 'uuid', 'user_id', 'profile', 'disk', 'path', 'original_name', 'description', 'mime', 'size', 'hash', 'width', 'height', 'created_at', 'updated_at'],
            Schema::getColumnListing('media'),
        );
    }

    public function test_uuid_is_generated_on_creation(): void
    {
        $file = File::factory()->create(['uuid' => null]);

        $this->assertSame(1, preg_match('/^[0-9a-f]{32}$/', $file->uuid));
        $this->assertSame($file->id, File::findByUuid($file->uuid)?->id);
    }

    public function test_dimensions_are_optional_and_numbers_are_cast(): void
    {
        $file = File::factory()->create()->fresh();

        $this->assertNull($file->width);
        $this->assertNull($file->height);
        $this->assertIsInt($file->size);
    }

    public function test_identical_hashes_are_allowed(): void
    {
        File::factory()->count(2)->create(['hash' => str_repeat('a', 64)]);

        $this->assertSame(2, File::query()->where('hash', str_repeat('a', 64))->count());
    }

    public function test_find_by_uuid_returns_null_for_unknown_uuid(): void
    {
        $this->assertNull(File::findByUuid(str_repeat('0', 32)));
    }
}
