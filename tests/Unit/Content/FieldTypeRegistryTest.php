<?php

namespace Webtack\Ecms\Tests\Unit\Content;

use InvalidArgumentException;
use Webtack\Ecms\Content\FieldTypeRegistry;
use Webtack\Ecms\Content\FieldTypes\IntegerType;
use Webtack\Ecms\Tests\TestCase;

class FieldTypeRegistryTest extends TestCase
{
    public function test_a_registered_key_resolves(): void
    {
        $this->assertInstanceOf(IntegerType::class, app(FieldTypeRegistry::class)->get('integer'));
    }

    public function test_an_unknown_key_is_an_error(): void
    {
        $this->expectException(InvalidArgumentException::class);
        app(FieldTypeRegistry::class)->get('nope');
    }

    public function test_all_builtin_types_are_registered_and_image_and_file_share_the_string_table(): void
    {
        $registry = app(FieldTypeRegistry::class);

        $this->assertEqualsCanonicalizing(
            ['string', 'text', 'richtext', 'integer', 'decimal', 'boolean', 'date', 'datetime', 'image', 'file', 'youtube', 'term', 'url', 'type_ref', 'node_ref'],
            $registry->keys(),
        );
        $this->assertContains('content_values_datetime', $registry->tables());
        $this->assertCount(7, $registry->tables());
        $this->assertSame('content_values_string', $registry->get('image')->table());
        $this->assertSame('content_values_string', $registry->get('file')->table());
    }

    public function test_a_type_can_be_registered_at_runtime(): void
    {
        $registry = new FieldTypeRegistry([]);

        $this->assertFalse($registry->has('integer'));
        $registry->register('integer', IntegerType::class);
        $this->assertTrue($registry->has('integer'));
    }
}
