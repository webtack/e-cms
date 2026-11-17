<?php

namespace Webtack\Ecms\Tests\Feature\Content;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Webtack\Ecms\Content\ContentNodeCreator;
use Webtack\Ecms\Models\ContentField;
use Webtack\Ecms\Models\ContentType;
use Webtack\Ecms\Tests\TestCase;

class SystemFieldsBackfillMigrationTest extends TestCase
{
    use RefreshDatabase;

    private const MIGRATION = 'database/migrations/2026_11_02_100100_backfill_system_metadata_fields.php';

    private function rollback(): void
    {
        Artisan::call('migrate:rollback', ['--path' => $this->packagePath(self::MIGRATION), '--realpath' => true]);
    }

    private function migrate(): void
    {
        Artisan::call('migrate', ['--path' => $this->packagePath(self::MIGRATION), '--realpath' => true]);
    }

    /** @return list<string> */
    private function keys(string $type): array
    {
        return ContentField::where('content_type', $type)->orderBy('weight')->pluck('key')->all();
    }

    public function test_existing_types_get_the_fields_of_their_kind_after_their_own_fields(): void
    {
        foreach (['content', 'page', 'block'] as $kind) {
            ContentType::create(['key' => "t_{$kind}", 'name' => $kind, 'kind' => $kind]);
            ContentField::create(['content_type' => "t_{$kind}", 'key' => 'body', 'name' => 'Body', 'type' => 'text', 'weight' => 10]);
        }
        $node = app(ContentNodeCreator::class)->create(ContentType::findByKey('t_page'), ['title' => 'Home', 'body' => 'Text'], authorId: $this->authorId());

        $this->rollback();

        $this->assertSame(['title', 'body'], $this->keys('t_content'));
        $this->assertSame(['title', 'body'], $this->keys('t_page'));

        $this->migrate();

        $this->assertSame(
            ['title', 'body', 'meta_title', 'meta_description', 'meta_image', 'section_title', 'section_description', 'section_cover'],
            $this->keys('t_content'),
        );
        $this->assertSame(['title', 'body', 'meta_title', 'meta_description', 'meta_image'], $this->keys('t_page'));
        $this->assertSame(['title', 'body'], $this->keys('t_block'));
        $this->assertSame([11, 12, 13, 14, 15, 16], ContentField::where('content_type', 't_content')->where('is_system', true)->where('key', '!=', 'title')->orderBy('weight')->pluck('weight')->all());

        $field = ContentField::where('content_type', 't_content')->where('key', 'meta_image')->sole();
        $this->assertSame('image', $field->type);
        $this->assertTrue($field->is_system);
        $this->assertTrue($field->is_translatable);
        $this->assertFalse($field->is_required);
        $this->assertFalse($field->is_multiple);

        // Existing fields and values stay.
        $this->assertSame('Text', DB::table('content_values_text')->where('content_node_id', $node->id)->where('field_key', 'body')->value('value'));
    }

    public function test_running_it_again_adds_nothing(): void
    {
        ContentType::create(['key' => 'news', 'name' => 'News']);
        $before = ContentField::count();

        $this->rollback();
        $this->migrate();

        $this->assertSame($before, ContentField::count());
    }

    public function test_a_user_field_with_a_reserved_key_stops_the_migration_before_any_change(): void
    {
        ContentType::create(['key' => 'news', 'name' => 'News']);
        ContentType::create(['key' => 'blog', 'name' => 'Blog']);
        $this->rollback();
        DB::table('content_fields')->insert([
            'content_type' => 'blog', 'key' => 'meta_title', 'name' => 'Own', 'type' => 'string', 'is_system' => false,
        ]);

        try {
            $this->migrate();
            $this->fail('The migration must report the conflict.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('blog.meta_title', $e->getMessage());
        }

        $this->assertSame(['title'], $this->keys('news'));
        $this->assertEqualsCanonicalizing(['title', 'meta_title'], $this->keys('blog'));
    }

    public function test_the_rollback_removes_the_metadata_fields_with_their_values(): void
    {
        $type = ContentType::create(['key' => 'news', 'name' => 'News']);
        $node = app(ContentNodeCreator::class)->create($type, ['title' => 'A', 'meta_title' => 'SEO'], authorId: $this->authorId());

        $this->rollback();

        $this->assertSame(['title'], $this->keys('news'));
        $this->assertSame(['title'], DB::table('content_values_string')->where('content_node_id', $node->id)->pluck('field_key')->all());
    }
}
