<?php

namespace Webtack\Ecms\Tests\Feature\Content;

use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Webtack\Ecms\Tests\TestCase;

/**
 * Schema-level guarantees of value rows owned by a type instead of a node.
 */
class TypeValuesSchemaTest extends TestCase
{
    use RefreshDatabase;

    private const VALUE_TYPES = ['string', 'text', 'integer', 'decimal', 'boolean', 'date', 'datetime'];

    public function test_value_tables_have_the_owner_columns(): void
    {
        foreach (self::VALUE_TYPES as $type) {
            $columns = collect(Schema::getColumns("content_values_{$type}"))->keyBy('name');

            $this->assertTrue($columns['content_node_id']['nullable'], "{$type}.content_node_id");
            $this->assertTrue($columns['content_node_uuid']['nullable'], "{$type}.content_node_uuid");
            $this->assertFalse($columns['entity']['nullable'], "{$type}.entity");
            $this->assertStringContainsString('node', (string) $columns['entity']['default']);
        }
    }

    public function test_a_type_row_without_a_node_is_stored(): void
    {
        $this->seedType('news');

        $this->insertTypeValue('news', 'section_title');

        $this->assertSame(1, DB::table('content_values_string')->where('entity', 'type')->count());
    }

    public function test_a_node_row_without_a_node_is_rejected(): void
    {
        $this->seedType('news');

        $this->expectException(QueryException::class);
        $this->insertRow('news', 'section_title', entity: 'node', nodeId: null, nodeUuid: null);
    }

    public function test_a_type_row_with_a_node_is_rejected(): void
    {
        $this->seedType('news');
        $nodeId = $this->insertNode('news');

        $this->expectException(QueryException::class);
        $this->insertRow('news', 'section_title', entity: 'type', nodeId: $nodeId, nodeUuid: (string) Str::uuid7());
    }

    public function test_an_unknown_entity_is_rejected(): void
    {
        $this->seedType('news');

        $this->expectException(QueryException::class);
        $this->insertRow('news', 'section_title', entity: 'user', nodeId: null, nodeUuid: null);
    }

    public function test_a_duplicate_type_value_is_rejected(): void
    {
        $this->seedType('news');
        $this->insertTypeValue('news', 'section_title');

        $this->expectException(QueryException::class);
        $this->insertTypeValue('news', 'section_title');
    }

    public function test_a_type_value_may_repeat_in_another_language_or_weight(): void
    {
        $this->seedType('news');
        $this->insertTypeValue('news', 'section_title');

        $this->insertTypeValue('news', 'section_title', lang: 'en');
        $this->insertTypeValue('news', 'section_title', weight: 1);

        $this->assertSame(3, DB::table('content_values_string')->where('entity', 'type')->count());
    }

    public function test_a_duplicate_node_value_stays_rejected(): void
    {
        $this->seedType('news');
        $nodeId = $this->insertNode('news');
        $this->insertNodeValue($nodeId, 'news', 'section_title');

        $this->expectException(QueryException::class);
        $this->insertNodeValue($nodeId, 'news', 'section_title');
    }

    public function test_a_type_value_and_a_node_value_of_one_field_coexist(): void
    {
        $this->seedType('news');
        $nodeId = $this->insertNode('news');

        $this->insertTypeValue('news', 'section_title');
        $this->insertNodeValue($nodeId, 'news', 'section_title');

        $this->assertSame(2, DB::table('content_values_string')->count());
    }

    public function test_deleting_a_type_deletes_its_values(): void
    {
        $this->seedType('news');
        $this->seedType('blog');
        $this->insertTypeValue('news', 'section_title');
        $this->insertTypeValue('blog', 'section_title');

        DB::table('content_types')->where('key', 'news')->delete();

        $this->assertSame(
            ['blog'],
            DB::table('content_values_string')->where('entity', 'type')->pluck('content_type')->all(),
        );
    }

    private function seedType(string $key): void
    {
        DB::table('content_types')->insert(['key' => $key, 'name' => $key]);
        DB::table('content_fields')->insert([
            'content_type' => $key, 'key' => 'section_title', 'name' => 'Section title', 'type' => 'string',
        ]);
    }

    private function insertNode(string $type): int
    {
        return DB::table('content_nodes')->insertGetId([
            'uuid' => (string) Str::uuid7(),
            'content_type' => $type,
            'slug' => 'node-'.Str::random(6),
            'published_at' => now(),
            'user_id' => $this->authorId(),
        ]);
    }

    private function insertTypeValue(string $type, string $fieldKey, string $lang = 'default', int $weight = 0): void
    {
        $this->insertRow($type, $fieldKey, 'type', null, null, $lang, $weight);
    }

    private function insertNodeValue(int $nodeId, string $type, string $fieldKey): void
    {
        $uuid = (string) DB::table('content_nodes')->where('id', $nodeId)->value('uuid');

        $this->insertRow($type, $fieldKey, 'node', $nodeId, $uuid);
    }

    private function insertRow(
        string $type,
        string $fieldKey,
        string $entity,
        ?int $nodeId,
        ?string $nodeUuid,
        string $lang = 'default',
        int $weight = 0,
    ): void {
        DB::table('content_values_string')->insert([
            'entity' => $entity,
            'content_node_id' => $nodeId,
            'content_node_uuid' => $nodeUuid,
            'content_type' => $type,
            'field_key' => $fieldKey,
            'lang' => $lang,
            'weight' => $weight,
            'value' => 'x',
        ]);
    }
}
