<?php

namespace Webtack\Ecms\Tests\Feature\Content;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Webtack\Ecms\Models\ContentNode;
use Webtack\Ecms\Models\ContentType;
use Webtack\Ecms\Tests\Fixtures\User;
use Webtack\Ecms\Tests\TestCase;

class ServiceAttributesMigrationTest extends TestCase
{
    use RefreshDatabase;

    private const MIGRATION = 'database/migrations/2026_10_31_100000_add_service_attributes_to_content_nodes_table.php';

    private function rollback(): void
    {
        Artisan::call('migrate:rollback', ['--path' => $this->packagePath(self::MIGRATION), '--realpath' => true]);
    }

    private function migrate(): void
    {
        Artisan::call('migrate', ['--path' => $this->packagePath(self::MIGRATION), '--realpath' => true]);
    }

    private function insertLegacyNode(): void
    {
        ContentType::factory()->create(['key' => 'legacy']);
        DB::table('content_nodes')->insert([
            'uuid' => 'legacy-uuid', 'content_type' => 'legacy', 'slug' => 'legacy',
            'created_at' => '2026-01-02 10:00:00', 'updated_at' => '2026-01-03 10:00:00',
        ]);
    }

    public function test_backfills_published_at_and_author_from_the_administrator(): void
    {
        config(['app.env' => 'testing']);
        $admin = User::factory()->create(['email' => 'admin@example.com']);
        $this->rollback();
        $this->insertLegacyNode();

        $this->migrate();

        $node = ContentNode::query()->where('slug', 'legacy')->first();
        $this->assertSame($admin->id, $node->user_id);
        $this->assertSame('2026-01-02 10:00', $node->published_at->format('Y-m-d H:i'));
        $this->assertTrue($node->is_active);
    }

    public function test_fails_with_an_explanation_when_there_is_no_administrator(): void
    {
        $this->rollback();
        $this->insertLegacyNode();

        try {
            $this->migrate();
            $this->fail('The migration must fail without an administrator.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('ADMIN_EMAIL', $e->getMessage());
        }

        $this->assertFalse(Schema::hasColumn('content_nodes', 'user_id'));
    }

    public function test_an_empty_table_needs_no_administrator(): void
    {
        $this->rollback();

        $this->migrate();

        $this->assertTrue(Schema::hasColumns('content_nodes', ['published_at', 'is_active', 'user_id']));
    }
}
