<?php

namespace Webtack\Ecms\Tests\Feature\Content;

use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Webtack\Ecms\Models\ContentField;
use Webtack\Ecms\Models\ContentNode;
use Webtack\Ecms\Models\ContentType;
use Webtack\Ecms\Tests\TestCase;

class ContentFieldTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_field_is_added_to_a_type(): void
    {
        ContentType::create(['key' => 'article', 'name' => 'Article']);

        $field = ContentField::create(['content_type' => 'article', 'key' => 'visits', 'name' => 'Visits', 'type' => 'integer']);

        $this->assertSame('article', $field->fresh()->content_type);
        $this->assertFalse($field->fresh()->is_system);
    }

    public function test_a_duplicate_key_in_a_type_is_rejected_but_allowed_in_another(): void
    {
        ContentType::create(['key' => 'article', 'name' => 'Article']);
        ContentType::create(['key' => 'product', 'name' => 'Product']);
        ContentField::create(['content_type' => 'article', 'key' => 'visits', 'name' => 'Visits', 'type' => 'integer']);
        ContentField::create(['content_type' => 'product', 'key' => 'visits', 'name' => 'Visits', 'type' => 'integer']);

        $this->expectException(QueryException::class);
        ContentField::create(['content_type' => 'article', 'key' => 'visits', 'name' => 'Again', 'type' => 'integer']);
    }

    public function test_invalid_key_formats_are_rejected(): void
    {
        ContentType::create(['key' => 'article', 'name' => 'Article']);

        foreach (['My Field', '1price'] as $key) {
            try {
                ContentField::create(['content_type' => 'article', 'key' => $key, 'name' => 'X', 'type' => 'integer']);
                $this->fail("Key [{$key}] must be rejected.");
            } catch (ValidationException $e) {
                $this->assertArrayHasKey('key', $e->errors());
            }
        }
    }

    public function test_the_key_and_type_of_a_field_cannot_change(): void
    {
        ContentType::create(['key' => 'article', 'name' => 'Article']);
        ContentType::create(['key' => 'product', 'name' => 'Product']);
        $field = ContentField::create(['content_type' => 'article', 'key' => 'visits', 'name' => 'Visits', 'type' => 'integer']);

        foreach (['key' => 'hits', 'content_type' => 'product'] as $attribute => $value) {
            try {
                $field->fresh()->forceFill([$attribute => $value])->save();
                $this->fail("Changing {$attribute} must be rejected.");
            } catch (ValidationException) {
            }
        }
        $this->assertSame('visits', $field->fresh()->key);
        $this->assertSame('article', $field->fresh()->content_type);

        $field->fresh()->update(['name' => 'Hits']);
        $this->assertSame('Hits', $field->fresh()->name);
    }

    public function test_an_unknown_field_type_is_rejected(): void
    {
        ContentType::create(['key' => 'article', 'name' => 'Article']);

        try {
            ContentField::create(['content_type' => 'article', 'key' => 'x', 'name' => 'X', 'type' => 'nope']);
            $this->fail('Unknown type must be rejected.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('type', $e->errors());
        }
    }

    public function test_the_type_of_a_field_with_values_cannot_change(): void
    {
        ContentType::create(['key' => 'article', 'name' => 'Article']);
        $field = ContentField::create(['content_type' => 'article', 'key' => 'visits', 'name' => 'Visits', 'type' => 'integer']);
        $node = ContentNode::factory()->create(['content_type' => 'article']);
        DB::table('content_values_integer')->insert([
            'content_node_id' => $node->id,
            'content_node_uuid' => $node->uuid,
            'content_type' => 'article',
            'field_key' => 'visits',
            'value' => 5,
        ]);

        try {
            $field->fresh()->update(['type' => 'string']);
            $this->fail('Type change must be rejected.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('type', $e->errors());
        }
        $this->assertSame('integer', $field->fresh()->type);
        $this->assertSame(1, DB::table('content_values_integer')->count());

        $field->fresh()->update(['name' => 'Hits', 'weight' => 3, 'settings' => ['min' => 0]]);
        $this->assertSame('Hits', $field->fresh()->name);
    }

    public function test_the_type_of_a_field_without_values_cannot_change_either(): void
    {
        ContentType::create(['key' => 'article', 'name' => 'Article']);
        $field = ContentField::create(['content_type' => 'article', 'key' => 'visits', 'name' => 'Visits', 'type' => 'integer']);

        try {
            $field->update(['type' => 'string']);
            $this->fail('Type change must be rejected.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('type', $e->errors());
        }
        $this->assertSame('integer', $field->fresh()->type);

        $field->update(['type' => 'integer', 'name' => 'Hits', 'weight' => 3]);
        $this->assertSame('Hits', $field->fresh()->name);
    }

    public function test_a_field_without_values_is_deleted(): void
    {
        ContentType::create(['key' => 'article', 'name' => 'Article']);
        $field = ContentField::create(['content_type' => 'article', 'key' => 'visits', 'name' => 'Visits', 'type' => 'integer']);

        $field->delete();

        $this->assertNull(ContentField::query()->where('key', 'visits')->first());
    }

    public function test_a_field_with_values_is_not_deleted(): void
    {
        ContentType::create(['key' => 'article', 'name' => 'Article']);
        $field = ContentField::create(['content_type' => 'article', 'key' => 'visits', 'name' => 'Visits', 'type' => 'integer']);
        $node = ContentNode::factory()->create(['content_type' => 'article']);
        DB::table('content_values_integer')->insert([
            'content_node_id' => $node->id,
            'content_node_uuid' => $node->uuid,
            'content_type' => 'article',
            'field_key' => 'visits',
            'lang' => 'en',
            'value' => 5,
        ]);

        try {
            $field->fresh()->delete();
            $this->fail('Deleting a field that has values must be rejected.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('key', $e->errors());
        }

        $this->assertNotNull(ContentField::query()->where('key', 'visits')->first());
        $this->assertSame(1, DB::table('content_values_integer')->count());
    }

    public function test_list_settings_must_reference_fields_of_the_type(): void
    {
        $article = ContentType::create(['key' => 'article', 'name' => 'Article']);
        ContentType::create(['key' => 'product', 'name' => 'Product']);
        ContentField::create(['content_type' => 'article', 'key' => 'visits', 'name' => 'Visits', 'type' => 'integer']);
        ContentField::create(['content_type' => 'product', 'key' => 'price', 'name' => 'Price', 'type' => 'integer']);

        $article->update(['settings' => ['list' => [
            'visible' => ['title', 'visits'],
            'search' => ['title'],
            'sort' => ['field' => 'visits', 'direction' => 'desc'],
        ]]]);

        foreach ([
            ['visible' => ['title', 'price']],
            ['search' => ['price']],
            ['sort' => ['field' => 'price', 'direction' => 'asc']],
        ] as $list) {
            try {
                $article->fresh()->update(['settings' => ['list' => $list]]);
                $this->fail('Foreign field must be rejected.');
            } catch (ValidationException $e) {
                $this->assertArrayHasKey('settings', $e->errors());
            }
        }
    }

    public function test_a_type_without_settings_has_empty_settings(): void
    {
        $this->assertNull(ContentType::create(['key' => 'article', 'name' => 'Article'])->fresh()->settings);
    }

    public function test_a_user_field_cannot_take_a_system_field_key(): void
    {
        ContentType::create(['key' => 'article', 'name' => 'Article']);
        ContentType::create(['key' => 'shot', 'name' => 'Shot', 'kind' => 'block']);

        foreach (['article', 'shot'] as $type) {
            foreach (['meta_title', 'meta_description', 'meta_image', 'section_title', 'section_description', 'section_cover'] as $key) {
                try {
                    ContentField::create(['content_type' => $type, 'key' => $key, 'name' => 'X', 'type' => 'string']);
                    $this->fail("The key {$key} must be reserved in {$type}.");
                } catch (ValidationException $e) {
                    $this->assertArrayHasKey('key', $e->errors());
                }
            }
        }

        $this->assertSame(0, ContentField::where('content_type', 'shot')->where('key', 'meta_title')->count());
    }

    public function test_the_views_key_is_reserved_for_new_fields_but_an_existing_one_stays_valid(): void
    {
        ContentType::create(['key' => 'article', 'name' => 'Article']);

        try {
            ContentField::create(['content_type' => 'article', 'key' => 'views', 'name' => 'Views', 'type' => 'integer']);
            $this->fail('The key views must be reserved.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('key', $e->errors());
        }
        $this->assertFalse(ContentField::where('key', 'views')->exists());

        // A field created before the reservation keeps working: it can still be edited.
        $field = ContentField::create(['content_type' => 'article', 'key' => 'visits', 'name' => 'Visits', 'type' => 'integer']);
        DB::table('content_fields')->where('id', $field->id)->update(['key' => 'views']);
        $legacy = ContentField::query()->findOrFail($field->id);
        $legacy->update(['name' => 'Renamed']);

        $this->assertSame('Renamed', $legacy->fresh()->name);
    }

    public function test_a_field_shows_its_label_by_default_and_keeps_hide_label_after_a_save(): void
    {
        ContentType::create(['key' => 'article', 'name' => 'Article']);

        $field = ContentField::create(['content_type' => 'article', 'key' => 'visits', 'name' => 'Visits', 'type' => 'integer']);
        $this->assertFalse($field->fresh()->hide_label);

        $field->update(['hide_label' => true]);
        $this->assertTrue($field->fresh()->hide_label);

        $field->update(['name' => 'Page visits']);
        $this->assertTrue($field->fresh()->hide_label);
    }
}
