<?php

namespace Webtack\Ecms\Tests\Feature\Content;

use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Webtack\Ecms\Tests\TestCase;

class SchemaTest extends TestCase
{
    use RefreshDatabase;

    private const VALUE_TYPES = ['string', 'text', 'integer', 'decimal', 'boolean', 'date', 'datetime'];

    public function test_content_nodes_have_a_unique_uuid_index(): void
    {
        $unique = collect(Schema::getIndexes('content_nodes'))
            ->first(fn (array $index) => $index['unique'] && $index['columns'] === ['uuid']);

        $this->assertNotNull($unique);
    }

    public function test_types_and_fields_have_no_uuid(): void
    {
        $this->assertFalse(Schema::hasColumn('content_types', 'uuid'));
        $this->assertFalse(Schema::hasColumn('content_fields', 'uuid'));
    }

    public function test_type_relations_reference_the_type_key(): void
    {
        foreach ([
            'content_types' => ['parent_type'],
            'content_fields' => ['content_type'],
            'content_nodes' => ['content_type'],
        ] as $table => $columns) {
            $fk = collect(Schema::getForeignKeys($table))->first(fn (array $fk) => $fk['columns'] === $columns);

            $this->assertNotNull($fk, "{$table} must have a FK on ".implode(',', $columns));
            $this->assertSame('content_types', $fk['foreign_table']);
            $this->assertSame(['key'], $fk['foreign_columns']);
        }
    }

    public function test_value_tables_follow_the_shared_contract(): void
    {
        foreach (self::VALUE_TYPES as $type) {
            $table = "content_values_{$type}";

            foreach (['content_node_id', 'content_node_uuid', 'content_type', 'field_key', 'lang', 'weight', 'value', 'options'] as $column) {
                $this->assertTrue(Schema::hasColumn($table, $column), "{$table}.{$column}");
            }
            $this->assertFalse(Schema::hasColumn($table, 'field_id'), "{$table} must not have field_id");

            $lang = collect(Schema::getColumns($table))->firstWhere('name', 'lang');
            $this->assertStringContainsString('default', (string) $lang['default']);

            $columnSets = collect(Schema::getIndexes($table))->map(fn (array $index) => $index['columns']);
            $this->assertTrue($columnSets->contains(['content_node_id', 'content_type', 'field_key', 'lang', 'weight']));
            $this->assertTrue($columnSets->contains(
                $type === 'text'
                    ? ['content_type', 'field_key', 'lang']
                    : ['content_type', 'field_key', 'lang', 'value'],
            ));

            $foreignKeys = collect(Schema::getForeignKeys($table));
            $field = $foreignKeys->first(fn (array $fk) => $fk['foreign_table'] === 'content_fields');
            $node = $foreignKeys->first(fn (array $fk) => $fk['foreign_table'] === 'content_nodes');
            $this->assertSame(['content_type', 'field_key'], $field['columns']);
            $this->assertSame(['content_type', 'key'], $field['foreign_columns']);
            $this->assertSame(['content_node_id', 'content_type'], $node['columns']);
            $this->assertSame(['id', 'content_type'], $node['foreign_columns']);
            $this->assertSame('cascade', $field['on_delete']);
            $this->assertSame('cascade', $node['on_delete']);
        }
    }

    public function test_database_rejects_a_value_of_another_types_field(): void
    {
        $this->seedTypesAndFields();
        $nodeId = $this->insertNode('article');

        $this->expectException(QueryException::class);
        $this->insertValue($nodeId, 'product', 'price');
    }

    public function test_database_rejects_a_value_of_a_missing_field(): void
    {
        $this->seedTypesAndFields();
        $nodeId = $this->insertNode('article');

        $this->expectException(QueryException::class);
        $this->insertValue($nodeId, 'article', 'missing');
    }

    public function test_database_rejects_a_duplicate_value_weight(): void
    {
        $this->seedTypesAndFields();
        $nodeId = $this->insertNode('article');
        $this->insertValue($nodeId, 'article', 'visits');

        $this->expectException(QueryException::class);
        $this->insertValue($nodeId, 'article', 'visits');
    }

    public function test_a_valid_value_is_accepted(): void
    {
        $this->seedTypesAndFields();
        $nodeId = $this->insertNode('article');

        $this->insertValue($nodeId, 'article', 'visits');

        $this->assertSame(1, DB::table('content_values_integer')->count());
    }

    private function seedTypesAndFields(): void
    {
        foreach (['article', 'product'] as $key) {
            DB::table('content_types')->insert(['key' => $key, 'name' => $key]);
        }
        DB::table('content_fields')->insert([
            ['content_type' => 'article', 'key' => 'visits', 'name' => 'Visits', 'type' => 'integer'],
            ['content_type' => 'product', 'key' => 'price', 'name' => 'Price', 'type' => 'integer'],
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

    private function insertValue(int $nodeId, string $type, string $fieldKey): void
    {
        DB::table('content_values_integer')->insert([
            'content_node_id' => $nodeId,
            'content_node_uuid' => DB::table('content_nodes')->where('id', $nodeId)->value('uuid'),
            'content_type' => $type,
            'field_key' => $fieldKey,
            'value' => 1,
        ]);
    }
}
