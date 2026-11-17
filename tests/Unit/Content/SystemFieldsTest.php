<?php

namespace Webtack\Ecms\Tests\Unit\Content;

use Webtack\Ecms\Content\FieldTypeRegistry;
use Webtack\Ecms\Content\SystemFields;
use Webtack\Ecms\Tests\TestCase;

class SystemFieldsTest extends TestCase
{
    public function test_fields_by_kind(): void
    {
        $fields = new SystemFields;

        $this->assertSame(
            ['title', 'meta_title', 'meta_description', 'meta_image', 'section_title', 'section_description', 'section_cover'],
            array_column($fields->forKind('content'), 'key'),
        );
        $this->assertSame(
            ['title', 'meta_title', 'meta_description', 'meta_image'],
            array_column($fields->forKind('page'), 'key'),
        );
        $this->assertSame(['title'], array_column($fields->forKind('block'), 'key'));
        $this->assertSame([], $fields->forKind('unknown'));
    }

    public function test_fields_are_ordered_by_weight_with_title_first(): void
    {
        $weights = array_column((new SystemFields)->forKind('content'), 'weight');

        $this->assertSame(0, $weights[0]);
        $sorted = $weights;
        sort($sorted);
        $this->assertSame($sorted, $weights);
        $this->assertSame(array_unique($weights), $weights);
    }

    public function test_only_title_is_required_and_every_field_uses_a_registered_type(): void
    {
        $registry = app(FieldTypeRegistry::class);

        foreach ((new SystemFields)->all() as $definition) {
            $this->assertSame($definition['key'] === 'title', $definition['required'], $definition['key']);
            $this->assertTrue($registry->has($definition['type']), $definition['key']);
        }
    }

    public function test_keys_and_reserved_keys(): void
    {
        $fields = new SystemFields;

        $this->assertContains('title', $fields->keys());
        $this->assertNotContains('title', $fields->reservedKeys());
        $this->assertEqualsCanonicalizing(
            ['meta_title', 'meta_description', 'meta_image', 'section_title', 'section_description', 'section_cover'],
            $fields->reservedKeys(),
        );
    }

    public function test_find(): void
    {
        $fields = new SystemFields;

        $this->assertSame('image', $fields->find('meta_image')['type']);
        $this->assertNull($fields->find('price'));
    }

    public function test_owners(): void
    {
        $fields = new SystemFields;

        // Both owners.
        $this->assertTrue($fields->owns('content', 'meta_title', SystemFields::OWNER_NODE));
        $this->assertTrue($fields->owns('content', 'meta_title', SystemFields::OWNER_TYPE));
        // Page types hold meta values for nodes only.
        $this->assertTrue($fields->owns('page', 'meta_title', SystemFields::OWNER_NODE));
        $this->assertTrue($fields->owns('page', 'meta_title', SystemFields::OWNER_TYPE));
        // Section fields belong to the type of a content kind.
        $this->assertTrue($fields->owns('content', 'section_cover', SystemFields::OWNER_TYPE));
        $this->assertFalse($fields->owns('content', 'section_cover', SystemFields::OWNER_NODE));
        $this->assertFalse($fields->owns('page', 'section_cover', SystemFields::OWNER_TYPE));
        // The title and ordinary fields belong to nodes only.
        $this->assertTrue($fields->owns('block', 'title', SystemFields::OWNER_NODE));
        $this->assertFalse($fields->owns('content', 'title', SystemFields::OWNER_TYPE));
        $this->assertTrue($fields->owns('content', 'price', SystemFields::OWNER_NODE));
        $this->assertFalse($fields->owns('content', 'price', SystemFields::OWNER_TYPE));
        // A kind that does not get the field does not own it.
        $this->assertFalse($fields->owns('block', 'meta_title', SystemFields::OWNER_NODE));
    }

    public function test_owners_without_a_known_kind(): void
    {
        $fields = new SystemFields;

        $this->assertTrue($fields->owns(null, 'meta_title', SystemFields::OWNER_TYPE));
        $this->assertTrue($fields->owns(null, 'meta_title', SystemFields::OWNER_NODE));
        $this->assertFalse($fields->owns(null, 'section_title', SystemFields::OWNER_NODE));
        $this->assertFalse($fields->owns(null, 'title', SystemFields::OWNER_TYPE));
        $this->assertTrue($fields->owns(null, 'price', SystemFields::OWNER_NODE));
    }
}
