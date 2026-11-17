<?php

namespace Webtack\Ecms\Tests\Feature\Content;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Webtack\Ecms\Content\ContentValueReader;
use Webtack\Ecms\Content\ContentValueWriter;
use Webtack\Ecms\Content\FieldTypeRegistry;
use Webtack\Ecms\Content\FieldTypes\AbstractFieldType;
use Webtack\Ecms\Models\ContentField;
use Webtack\Ecms\Models\ContentNode;
use Webtack\Ecms\Models\ContentType;
use Webtack\Ecms\Tests\TestCase;

/**
 * A new field type is a class, a table and a registry entry; the core stays untouched.
 */
class FieldTypeExtensibilityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('content_values_color', function (Blueprint $table) {
            $table->id();
            $table->string('entity', 8)->default('node');
            $table->unsignedBigInteger('content_node_id')->nullable();
            $table->uuid('content_node_uuid')->nullable();
            $table->string('content_type', 64);
            $table->string('field_key', 64);
            $table->string('lang', 16)->default('default');
            $table->unsignedSmallInteger('weight')->default(0);
            $table->string('value', 7);
            $table->json('options')->nullable();
            $table->unique(['content_node_id', 'content_type', 'field_key', 'lang', 'weight'], 'cv_color_node_unique');
            $table->foreign(['content_type', 'field_key'], 'cv_color_field_foreign')
                ->references(['content_type', 'key'])->on('content_fields')->cascadeOnDelete();
            $table->foreign(['content_node_id', 'content_type'], 'cv_color_node_foreign')
                ->references(['id', 'content_type'])->on('content_nodes')->cascadeOnDelete();
        });

        app(FieldTypeRegistry::class)->register('color', new class extends AbstractFieldType
        {
            public function key(): string
            {
                return 'color';
            }

            public function prepare(mixed $value): string
            {
                return strtoupper($value);
            }

            protected function baseRules(): array
            {
                return ['string', 'regex:/^#[0-9a-fA-F]{6}$/'];
            }
        }::class);
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('content_values_color');

        parent::tearDown();
    }

    public function test_a_new_type_is_written_and_read_without_core_changes(): void
    {
        $type = ContentType::create(['key' => 'theme', 'name' => 'Theme']);
        ContentField::create(['content_type' => 'theme', 'key' => 'accent', 'name' => 'Accent', 'type' => 'color']);
        $node = ContentNode::factory()->create(['content_type' => $type->key]);

        app(ContentValueWriter::class)->write($node, ['accent' => '#ff00aa']);

        $this->assertSame('#FF00AA', app(ContentValueReader::class)->load([$node], ['accent'])[$node->id]['accent']);

        $this->expectException(ValidationException::class);
        app(ContentValueWriter::class)->write($node, ['accent' => 'red']);
    }
}
