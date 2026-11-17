<?php

namespace Webtack\Ecms\Tests\Feature\Content;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Webtack\Ecms\Content\ContentValueReader;
use Webtack\Ecms\Content\ContentValueWriter;
use Webtack\Ecms\Content\FieldTypeRegistry;
use Webtack\Ecms\Content\FieldTypes\AbstractFieldType;
use Webtack\Ecms\Content\FieldTypes\ComposedValue;
use Webtack\Ecms\Models\ContentField;
use Webtack\Ecms\Models\ContentNode;
use Webtack\Ecms\Models\ContentType;
use Webtack\Ecms\Tests\TestCase;

/**
 * A composed value keeps its scalar in `value` and the rest in `options`; plain types keep `options` empty.
 */
class ComposedValueTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Stored in the short string table; the scalar is `name`, the extra data is `note`.
        app(FieldTypeRegistry::class)->register('pair', new class extends AbstractFieldType implements ComposedValue
        {
            public function key(): string
            {
                return 'pair';
            }

            public function table(): string
            {
                return 'content_values_string';
            }

            public function split(mixed $input): array
            {
                return [$input['name'], isset($input['note']) ? ['note' => $input['note']] : null];
            }

            public function compose(mixed $scalar, ?array $options): mixed
            {
                return ['name' => $scalar, 'note' => $options['note'] ?? null];
            }

            public function isBlank(mixed $value): bool
            {
                return empty($value['name'] ?? null);
            }

            protected function baseRules(): array
            {
                return ['array'];
            }
        }::class);

        ContentType::create(['key' => 'card', 'name' => 'Card']);
    }

    private function field(string $key, string $type, bool $multiple = false): ContentNode
    {
        ContentField::create(['content_type' => 'card', 'key' => $key, 'name' => $key, 'type' => $type, 'is_multiple' => $multiple]);

        return ContentNode::factory()->create(['content_type' => 'card']);
    }

    private function read(ContentNode $node, string $key): mixed
    {
        return app(ContentValueReader::class)->load([$node], [$key])[$node->id][$key];
    }

    private function write(ContentNode $node, mixed $value, string $key = 'p'): void
    {
        app(ContentValueWriter::class)->write($node, [$key => $value]);
    }

    public function test_a_composed_value_is_stored_and_read_back(): void
    {
        $node = $this->field('p', 'pair');

        $this->write($node, ['name' => 'A', 'note' => 'n']);

        $this->assertSame(['name' => 'A', 'note' => 'n'], $this->read($node, 'p'));
        $row = DB::table('content_values_string')->where('content_node_id', $node->id)->first();
        $this->assertSame('A', $row->value);
        $this->assertSame(['note' => 'n'], json_decode($row->options, true));
    }

    public function test_a_plain_type_leaves_options_empty(): void
    {
        $node = $this->field('s', 'string');

        $this->write($node, 'hello', 's');

        $this->assertSame('hello', $this->read($node, 's'));
        $this->assertNull(DB::table('content_values_string')->where('content_node_id', $node->id)->value('options'));
    }

    public function test_null_clears_the_row_with_its_options(): void
    {
        $node = $this->field('p', 'pair');
        $this->write($node, ['name' => 'A', 'note' => 'n']);

        $this->write($node, null);

        $this->assertNull($this->read($node, 'p'));
        $this->assertSame(0, DB::table('content_values_string')->where('content_node_id', $node->id)->count());
    }

    public function test_a_multiple_field_keeps_order_and_options_per_row(): void
    {
        $node = $this->field('p', 'pair', multiple: true);

        $this->write($node, [['name' => 'A', 'note' => '1'], ['name' => 'B'], ['name' => 'C', 'note' => '3']]);

        $this->assertSame([
            ['name' => 'A', 'note' => '1'],
            ['name' => 'B', 'note' => null],
            ['name' => 'C', 'note' => '3'],
        ], $this->read($node, 'p'));
        $this->assertSame(
            ['1', null, '3'],
            DB::table('content_values_string')->where('content_node_id', $node->id)->orderBy('weight')->get()
                ->map(fn ($row) => json_decode($row->options, true)['note'] ?? null)->all(),
        );
    }

    public function test_a_single_field_accepts_an_associative_array_but_rejects_a_list(): void
    {
        $node = $this->field('p', 'pair');

        $this->write($node, ['name' => 'A']);
        $this->assertSame('A', $this->read($node, 'p')['name']);

        $this->expectException(ValidationException::class);
        $this->write($node, [['name' => 'A'], ['name' => 'B']]);
    }

    public function test_a_multiple_field_rejects_a_single_composed_value(): void
    {
        $node = $this->field('p', 'pair', multiple: true);

        $this->expectException(ValidationException::class);
        $this->write($node, ['name' => 'A']);
    }
}
