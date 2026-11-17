<?php

namespace Webtack\Ecms\Tests\Feature\Content;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Webtack\Ecms\Models\ContentField;
use Webtack\Ecms\Models\ContentNode;
use Webtack\Ecms\Models\ContentType;
use Webtack\Ecms\Tests\TestCase;

class CascadeTest extends TestCase
{
    use RefreshDatabase;

    private ContentNode $node;

    protected function setUp(): void
    {
        parent::setUp();

        ContentType::create(['key' => 'article', 'name' => 'Article']);
        ContentField::create(['content_type' => 'article', 'key' => 'visits', 'name' => 'Visits', 'type' => 'integer']);
        ContentField::create(['content_type' => 'article', 'key' => 'rating', 'name' => 'Rating', 'type' => 'integer']);
        $this->node = ContentNode::factory()->create(['content_type' => 'article']);

        foreach (['default', 'en'] as $lang) {
            foreach (['title' => 'string', 'visits' => 'integer', 'rating' => 'integer'] as $key => $table) {
                DB::table("content_values_{$table}")->insert([
                    'content_node_id' => $this->node->id,
                    'content_node_uuid' => $this->node->uuid,
                    'content_type' => 'article',
                    'field_key' => $key,
                    'lang' => $lang,
                    'value' => $table === 'string' ? 'x' : 1,
                ]);
            }
        }
    }

    public function test_deleting_a_node_removes_its_values_in_all_languages(): void
    {
        $this->node->delete();

        $this->assertSame(0, DB::table('content_values_string')->count());
        $this->assertSame(0, DB::table('content_values_integer')->count());
    }

    public function test_a_field_with_values_is_not_deleted(): void
    {
        try {
            ContentField::where('key', 'visits')->sole()->delete();
            $this->fail('Deleting a field that has values must be rejected.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('key', $e->errors());
        }

        $this->assertNotNull(ContentField::where('key', 'visits')->first());
        $this->assertSame(2, DB::table('content_values_integer')->where('field_key', 'visits')->count());
        $this->assertSame(2, DB::table('content_values_integer')->where('field_key', 'rating')->count());
    }

    public function test_a_field_is_deleted_once_its_values_are_cleared(): void
    {
        DB::table('content_values_integer')->where('field_key', 'visits')->delete();

        ContentField::where('key', 'visits')->sole()->delete();

        $this->assertNull(ContentField::where('key', 'visits')->first());
        $this->assertSame(2, DB::table('content_values_integer')->where('field_key', 'rating')->count());
        $this->assertSame(2, DB::table('content_values_string')->count());
    }

    public function test_deleting_a_type_removes_fields_nodes_and_values(): void
    {
        ContentType::findByKey('article')->delete();

        $this->assertSame(0, ContentField::count());
        $this->assertSame(0, ContentNode::count());
        $this->assertSame(0, DB::table('content_values_string')->count());
        $this->assertSame(0, DB::table('content_values_integer')->count());
    }
}
