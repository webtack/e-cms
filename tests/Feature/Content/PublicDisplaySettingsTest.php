<?php

namespace Webtack\Ecms\Tests\Feature\Content;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Webtack\Ecms\Content\PublicDisplaySettings;
use Webtack\Ecms\Content\ValueOwner;
use Webtack\Ecms\Models\ContentField;
use Webtack\Ecms\Models\ContentType;
use Webtack\Ecms\Tests\TestCase;

class PublicDisplaySettingsTest extends TestCase
{
    use RefreshDatabase;

    /** @param  array<string, string>  $extra  Extra fields, key => field type. */
    private function type(string $kind = 'content', ?array $public = null, array $extra = []): ContentType
    {
        $type = ContentType::create(['key' => 'article', 'name' => 'Article', 'kind' => $kind]);
        $defined = [['price', 'decimal'], ['color', 'string'], ['photo', 'image']];

        foreach ($extra as $key => $fieldType) {
            $defined[] = [$key, $fieldType];
        }

        foreach ($defined as $weight => [$key, $fieldType]) {
            ContentField::create(['content_type' => 'article', 'key' => $key, 'name' => ucfirst($key), 'type' => $fieldType, 'weight' => $weight + 1]);
        }

        if ($public !== null) {
            // Straight to the table: stale or invalid settings cannot be saved through the model, but can be stored already.
            DB::table('content_types')->where('id', $type->id)->update(['settings' => json_encode(['public' => $public])]);
        }

        return $type->fresh();
    }

    /** @return Collection<string, ContentField> */
    private function fields(ContentType $type): Collection
    {
        return ValueOwner::forNodesOfType($type->key)->fields($type->fields->keyBy('key'));
    }

    private function resolve(ContentType $type): array
    {
        return app(PublicDisplaySettings::class)->resolve($type, $this->fields($type));
    }

    public function test_defaults_without_settings(): void
    {
        $resolved = $this->resolve($this->type());

        $this->assertSame(['title'], $resolved['list_fields']);
        $this->assertSame(['price', 'color', 'photo'], $resolved['page_fields']);
        $this->assertSame(['field' => 'published_at', 'direction' => 'desc'], $resolved['sort']);
        $this->assertSame(0, $resolved['per_page']);
        $this->assertFalse($resolved['pagination']);
    }

    public function test_a_missing_page_fields_grows_with_new_fields_but_a_stored_list_does_not(): void
    {
        $all = $this->type();
        $explicit = ContentType::create(['key' => 'other', 'name' => 'Other']);
        ContentField::create(['content_type' => 'other', 'key' => 'price', 'name' => 'Price', 'type' => 'decimal']);
        $explicit->update(['settings' => ['public' => ['page_fields' => ['price']]]]);

        ContentField::create(['content_type' => 'article', 'key' => 'extra', 'name' => 'Extra', 'type' => 'string', 'weight' => 9]);
        ContentField::create(['content_type' => 'other', 'key' => 'extra', 'name' => 'Extra', 'type' => 'string']);

        $this->assertContains('extra', $this->resolve($all->fresh())['page_fields']);
        $this->assertSame(['price'], $this->resolve($explicit->fresh())['page_fields']);
    }

    public function test_stale_keys_are_dropped_on_read(): void
    {
        $type = $this->type(public: ['list_fields' => ['price']]);
        // A field removed after the settings were saved.
        ContentField::where('key', 'price')->delete();

        $resolved = $this->resolve($type->fresh());

        $this->assertSame(['title'], $resolved['list_fields']);
        $this->assertNotContains('price', $resolved['page_fields']);
    }

    public function test_sort_falls_back_when_it_references_a_missing_or_unsortable_field(): void
    {
        foreach (['gone', 'photo'] as $field) {
            $type = $this->type(public: ['sort' => ['field' => $field, 'direction' => 'asc']]);
            $this->assertSame(PublicDisplaySettings::DEFAULT_SORT, $this->resolve($type)['sort']);
            $type->delete();
        }

        $type = $this->type(public: ['sort' => ['field' => 'price', 'direction' => 'asc']]);
        $this->assertSame(['field' => 'price', 'direction' => 'asc'], $this->resolve($type)['sort']);
    }

    public function test_views_is_a_valid_public_sort_field(): void
    {
        $type = $this->type(public: ['sort' => ['field' => 'views', 'direction' => 'desc']]);

        $this->assertSame(['field' => 'views', 'direction' => 'desc'], $this->resolve($type)['sort']);
        $this->assertContains('views', array_column(app(PublicDisplaySettings::class)->formOptions($type, $this->fields($type))['sortOptions'], 'key'));
    }

    public function test_pagination_is_effectively_off_for_all_nodes(): void
    {
        $this->assertFalse($this->resolve($this->type(public: ['per_page' => 0, 'pagination' => true]))['pagination']);
    }

    public function test_pagination_defaults_to_on_for_a_page_size(): void
    {
        $resolved = $this->resolve($this->type(public: ['per_page' => 10]));

        $this->assertSame(10, $resolved['per_page']);
        $this->assertTrue($resolved['pagination']);
    }

    public function test_pages_have_page_fields_only(): void
    {
        $resolved = $this->resolve($this->type('page'));

        $this->assertSame(['page_fields'], array_keys($resolved));
    }

    public function test_form_options_keep_the_stored_pagination_for_all_nodes(): void
    {
        $type = $this->type(public: ['per_page' => 0, 'pagination' => false]);
        $options = app(PublicDisplaySettings::class)->formOptions($type, $this->fields($type));

        $this->assertTrue($options['current']['all_page_fields']);
        $this->assertFalse($options['current']['pagination']);
        $this->assertSame(['price', 'color', 'photo'], array_column($options['fields'], 'key'));
        $this->assertNotContains('photo', array_column($options['sortOptions'], 'key'));
    }

    public function test_meta_keeps_only_existing_fields_of_an_allowed_type(): void
    {
        $type = $this->type(public: ['meta' => ['title' => 'color', 'description' => 'color', 'image' => 'gone']]);

        $this->assertSame(['title' => 'color'], $this->resolve($type)['meta']);
        $this->assertSame(['title' => 'color'], app(PublicDisplaySettings::class)->clean($type, $this->fields($type))['meta']);
    }

    public function test_meta_is_absent_without_a_choice(): void
    {
        $this->assertArrayNotHasKey('meta', $this->resolve($this->type()));
    }

    public function test_a_block_never_has_meta(): void
    {
        $block = $this->type('block', ['meta' => ['title' => 'color']]);

        $this->assertArrayNotHasKey('meta', $this->resolve($block));
    }

    public function test_meta_options_offer_the_fields_of_an_allowed_type_and_the_node_title_for_the_title(): void
    {
        $type = $this->type(public: ['meta' => ['image' => 'photo']]);
        $options = app(PublicDisplaySettings::class)->metaOptions($type, $this->fields($type));

        $this->assertSame(['color'], array_column($options['metaFields']['title'], 'key'));
        $this->assertSame([false], array_column($options['metaFields']['title'], 'service'));
        $this->assertSame([], $options['metaFields']['description']);
        $this->assertSame(['photo'], array_column($options['metaFields']['image'], 'key'));
        $this->assertEquals((object) ['image' => 'photo'], $options['current']);
    }

    public function test_the_node_title_is_not_a_valid_meta_choice(): void
    {
        $type = $this->type(public: ['meta' => ['title' => 'title', 'description' => 'title']]);

        $this->assertArrayNotHasKey('meta', $this->resolve($type));
    }

    public function test_form_options_of_the_display_tab_carry_no_meta_choices(): void
    {
        $type = $this->type(public: ['meta' => ['image' => 'photo']]);
        $options = app(PublicDisplaySettings::class)->formOptions($type, $this->fields($type));

        $this->assertArrayNotHasKey('metaFields', $options);
        $this->assertArrayNotHasKey('meta', $options['current']);
    }

    public function test_an_archive_is_resolved_next_to_the_unchanged_main_sort(): void
    {
        $type = $this->type(public: [
            'sort' => ['field' => 'date', 'direction' => 'asc'],
            'archive' => ['field' => 'date', 'sort' => ['field' => 'date', 'direction' => 'desc']],
        ], extra: ['date' => 'datetime']);

        $resolved = $this->resolve($type);

        $this->assertSame(['field' => 'date', 'sort' => ['field' => 'date', 'direction' => 'desc']], $resolved['archive']);
        $this->assertSame(['field' => 'date', 'direction' => 'asc'], $resolved['sort']);
    }

    public function test_the_archive_sort_falls_back_to_the_main_sort(): void
    {
        $type = $this->type(public: [
            'sort' => ['field' => 'date', 'direction' => 'asc'],
            'archive' => ['field' => 'date'],
        ], extra: ['date' => 'datetime']);

        $this->assertSame(['field' => 'date', 'direction' => 'asc'], $this->resolve($type)['archive']['sort']);
    }

    public function test_a_stale_archive_sort_is_dropped_alone(): void
    {
        $type = $this->type(public: ['archive' => ['field' => 'date', 'sort' => ['field' => 'gone', 'direction' => 'asc']]], extra: ['date' => 'datetime']);

        $this->assertSame(['field' => 'date', 'sort' => PublicDisplaySettings::DEFAULT_SORT], $this->resolve($type)['archive']);
    }

    public function test_service_dates_can_split_the_archive(): void
    {
        foreach (['created_at', 'unpublished_at'] as $key) {
            $type = $this->type(public: ['archive' => ['field' => $key]]);
            $this->assertSame($key, $this->resolve($type)['archive']['field']);
            $type->delete();
        }
    }

    public function test_no_archive_without_a_valid_field(): void
    {
        // `gone` was deleted, `color` is a string field, `published_at` is not an archive date, `photo` is an image.
        foreach (['gone', 'color', 'published_at', 'photo'] as $key) {
            $type = $this->type(public: ['archive' => ['field' => $key]], extra: ['starts' => 'date']);
            $this->assertArrayNotHasKey('archive', $this->resolve($type), $key);
            $type->delete();
        }
    }

    public function test_the_archive_is_dropped_when_the_field_type_changed(): void
    {
        $type = $this->type(public: ['archive' => ['field' => 'date']], extra: ['date' => 'datetime']);
        $this->assertArrayHasKey('archive', $this->resolve($type));

        ContentField::query()->where('content_type', 'article')->where('key', 'date')->update(['type' => 'string']);

        $this->assertArrayNotHasKey('archive', $this->resolve($type->fresh()));
    }

    public function test_pages_and_blocks_have_no_archive(): void
    {
        foreach (['page', 'block'] as $kind) {
            $type = $this->type($kind, ['archive' => ['field' => 'date']], ['date' => 'datetime']);

            $this->assertArrayNotHasKey('archive', $this->resolve($type), $kind);
            $options = app(PublicDisplaySettings::class)->formOptions($type, $this->fields($type));
            $this->assertSame([], $options['archiveOptions'], $kind);
            $this->assertArrayNotHasKey('archive', $options['current'], $kind);
            $type->delete();
        }
    }

    public function test_form_options_offer_the_archive_dates_and_the_current_choice(): void
    {
        $type = $this->type(public: ['archive' => ['field' => 'starts', 'sort' => ['field' => 'starts', 'direction' => 'asc']]], extra: ['date' => 'datetime', 'starts' => 'date']);

        $options = app(PublicDisplaySettings::class)->formOptions($type, $this->fields($type));

        $this->assertSame(['unpublished_at', 'created_at', 'date', 'starts'], array_column($options['archiveOptions'], 'key'));
        $this->assertSame(['field' => 'starts', 'sort' => ['field' => 'starts', 'direction' => 'asc']], $options['current']['archive']);
    }

    public function test_form_options_have_no_archive_by_default(): void
    {
        $type = $this->type();

        $this->assertNull(app(PublicDisplaySettings::class)->formOptions($type, $this->fields($type))['current']['archive']);
    }
}
