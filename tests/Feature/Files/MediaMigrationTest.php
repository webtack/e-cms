<?php

namespace Webtack\Ecms\Tests\Feature\Files;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Webtack\Ecms\Tests\TestCase;

class MediaMigrationTest extends TestCase
{
    use RefreshDatabase;

    private function migration(): object
    {
        return require $this->packagePath('database/migrations/2026_10_30_110100_shorten_media_uuid.php');
    }

    private function legacyRow(string $uuid, string $path): int
    {
        return DB::table('media')->insertGetId([
            'uuid' => $uuid,
            'profile' => 'image',
            'disk' => 'public',
            'path' => $path,
            'original_name' => 'pic.png',
            'mime' => 'image/png',
            'size' => 3,
            'hash' => str_repeat('a', 64),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_uuids_lose_their_dashes_and_files_move_to_the_root_of_the_disk(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('images/0190a1b2-7c3e-7f0a-8b1c-2d3e4f5a6b7c.png', 'png');
        Storage::disk('public')->put('documents/0190a1b2-7c3e-7f0a-8b1c-2d3e4f5a6b7d.pdf', 'pdf');
        $image = $this->legacyRow('0190a1b2-7c3e-7f0a-8b1c-2d3e4f5a6b7c', 'images/0190a1b2-7c3e-7f0a-8b1c-2d3e4f5a6b7c.png');
        $document = $this->legacyRow('0190a1b2-7c3e-7f0a-8b1c-2d3e4f5a6b7d', 'documents/0190a1b2-7c3e-7f0a-8b1c-2d3e4f5a6b7d.pdf');

        $this->migration()->up();

        $this->assertDatabaseHas('media', ['id' => $image, 'uuid' => '0190a1b27c3e7f0a8b1c2d3e4f5a6b7c', 'path' => '0190a1b27c3e7f0a8b1c2d3e4f5a6b7c.png']);
        $this->assertDatabaseHas('media', ['id' => $document, 'uuid' => '0190a1b27c3e7f0a8b1c2d3e4f5a6b7d', 'path' => '0190a1b27c3e7f0a8b1c2d3e4f5a6b7d.pdf']);
        Storage::disk('public')->assertExists('0190a1b27c3e7f0a8b1c2d3e4f5a6b7c.png');
        Storage::disk('public')->assertExists('0190a1b27c3e7f0a8b1c2d3e4f5a6b7d.pdf');
        Storage::disk('public')->assertMissing('images/0190a1b2-7c3e-7f0a-8b1c-2d3e4f5a6b7c.png');
        Storage::disk('public')->assertMissing('documents/0190a1b2-7c3e-7f0a-8b1c-2d3e4f5a6b7d.pdf');
    }

    public function test_a_file_missing_on_disk_does_not_stop_the_migration(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('images/0190a1b2-7c3e-7f0a-8b1c-2d3e4f5a6b7d.png', 'png');
        $missing = $this->legacyRow('0190a1b2-7c3e-7f0a-8b1c-2d3e4f5a6b7c', 'images/0190a1b2-7c3e-7f0a-8b1c-2d3e4f5a6b7c.png');
        $present = $this->legacyRow('0190a1b2-7c3e-7f0a-8b1c-2d3e4f5a6b7d', 'images/0190a1b2-7c3e-7f0a-8b1c-2d3e4f5a6b7d.png');

        $this->migration()->up();

        $this->assertDatabaseHas('media', ['id' => $missing, 'uuid' => '0190a1b27c3e7f0a8b1c2d3e4f5a6b7c']);
        $this->assertDatabaseHas('media', ['id' => $present, 'path' => '0190a1b27c3e7f0a8b1c2d3e4f5a6b7d.png']);
        Storage::disk('public')->assertExists('0190a1b27c3e7f0a8b1c2d3e4f5a6b7d.png');
    }

    public function test_the_uuid_conversion_cannot_be_reverted(): void
    {
        $this->expectException(RuntimeException::class);

        $this->migration()->down();
    }
}
