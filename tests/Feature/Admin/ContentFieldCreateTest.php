<?php

namespace Webtack\Ecms\Tests\Feature\Admin;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;
use Webtack\Ecms\Models\ContentField;
use Webtack\Ecms\Models\ContentType;
use Webtack\Ecms\Models\Vocabulary;
use Webtack\Ecms\Tests\Fixtures\User;
use Webtack\Ecms\Tests\TestCase;

class ContentFieldCreateTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): static
    {
        return $this->actingAs(User::factory()->create());
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return ['key' => 'visits', 'name' => 'Visits', 'type' => 'integer', 'is_multiple' => false, 'is_required' => false, 'is_translatable' => false, 'weight' => 10, ...$overrides];
    }

    public function test_guest_is_redirected_to_login(): void
    {
        ContentType::create(['key' => 'news', 'name' => 'News']);

        $this->get('/admin/content-types/news/fields/create')->assertRedirect('/login');
        $this->post('/admin/content-types/news/fields', $this->payload())->assertRedirect('/login');

        $this->assertSame(7, ContentField::where('content_type', 'news')->count());
    }

    public function test_the_form_gets_type_options_and_the_next_weight(): void
    {
        ContentType::create(['key' => 'news', 'name' => 'News']);
        ContentField::create(['content_type' => 'news', 'key' => 'body', 'name' => 'Body', 'type' => 'text', 'weight' => 20]);

        $this->admin()->get('/admin/content-types/news/fields/create')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/ContentFields/Create')
                ->where('type', ['key' => 'news', 'name' => 'News', 'slug' => null, 'children_count' => 0, 'ancestors' => []])
                ->where('typeOptions', array_keys(config('content.field_types')))
                ->where('typeOptions', fn ($options) => in_array('term', $options->toArray(), true) && in_array('youtube', $options->toArray(), true))
                ->where('vocabularies', [])
                ->where('defaultWeight', 21));
    }

    public function test_term_is_offered_and_accepted_only_for_content_types(): void
    {
        ContentType::create(['key' => 'home', 'name' => 'Home', 'kind' => 'page']);
        ContentType::create(['key' => 'hero', 'name' => 'Hero', 'kind' => 'block']);
        Vocabulary::create(['key' => 'tags', 'name' => 'Tags']);

        foreach (['page-types/home', 'block-types/hero'] as $base) {
            $this->admin()->get("/admin/{$base}/fields/create")
                ->assertInertia(fn (Assert $page) => $page->where('typeOptions', fn ($options) => ! in_array('term', $options->toArray(), true)));

            $this->admin()->post("/admin/{$base}/fields", $this->payload(['type' => 'term', 'settings' => ['vocabulary' => 'tags']]))
                ->assertSessionHasErrors('type');
        }

        $this->assertNull(ContentField::where('key', 'visits')->first());
    }

    public function test_the_default_weight_of_a_type_without_extra_fields_follows_the_system_fields(): void
    {
        ContentType::create(['key' => 'news', 'name' => 'News']);

        $this->admin()->get('/admin/content-types/news/fields/create')
            ->assertInertia(fn (Assert $page) => $page->where('defaultWeight', 7));
    }

    public function test_a_field_is_created(): void
    {
        ContentType::create(['key' => 'news', 'name' => 'News']);

        $this->admin()->post('/admin/content-types/news/fields', $this->payload(['name' => ' Visits ', 'is_required' => true]))
            ->assertRedirect('/admin/content-types/news/fields')
            ->assertSessionHas('success');

        $field = ContentField::where('content_type', 'news')->where('key', 'visits')->firstOrFail();
        $this->assertSame('Visits', $field->name);
        $this->assertSame('integer', $field->type);
        $this->assertTrue($field->is_required);
        $this->assertFalse($field->is_multiple);
        $this->assertSame(10, $field->weight);
    }

    public function test_a_field_is_created_with_a_description(): void
    {
        ContentType::create(['key' => 'news', 'name' => 'News']);

        $this->admin()->post('/admin/content-types/news/fields', $this->payload(['description' => 'Read count']))->assertSessionHasNoErrors();
        $this->assertSame('Read count', ContentField::where('key', 'visits')->value('description'));

        $this->admin()->post('/admin/content-types/news/fields', $this->payload(['key' => 'long', 'description' => str_repeat('a', 1001)]))
            ->assertSessionHasErrors('description');
    }

    public function test_required_values_are_validated(): void
    {
        ContentType::create(['key' => 'news', 'name' => 'News']);

        $this->admin()->post('/admin/content-types/news/fields', ['weight' => ''])
            ->assertSessionHasErrors(['key', 'name', 'type', 'weight']);

        $this->assertSame(7, ContentField::where('content_type', 'news')->count());
    }

    public function test_invalid_key_formats_are_rejected(): void
    {
        ContentType::create(['key' => 'news', 'name' => 'News']);

        foreach (['My Field', '1price', 'UPPER', str_repeat('a', 65)] as $key) {
            $this->admin()->post('/admin/content-types/news/fields', $this->payload(['key' => $key]))
                ->assertSessionHasErrors('key');
        }

        $this->assertSame(7, ContentField::where('content_type', 'news')->count());
    }

    public function test_a_duplicate_key_is_rejected_within_the_type_only(): void
    {
        ContentType::create(['key' => 'news', 'name' => 'News']);
        ContentType::create(['key' => 'other', 'name' => 'Other']);
        ContentField::create(['content_type' => 'other', 'key' => 'visits', 'name' => 'Visits', 'type' => 'integer']);

        $this->admin()->post('/admin/content-types/news/fields', $this->payload(['key' => 'title']))
            ->assertSessionHasErrors('key');

        $this->admin()->post('/admin/content-types/news/fields', $this->payload())
            ->assertSessionHasNoErrors();

        $this->assertSame(1, ContentField::where('content_type', 'news')->where('key', 'visits')->count());
    }

    public function test_an_unregistered_type_is_rejected(): void
    {
        ContentType::create(['key' => 'news', 'name' => 'News']);

        $this->admin()->post('/admin/content-types/news/fields', $this->payload(['type' => 'nonsense']))
            ->assertSessionHasErrors('type');

        $this->assertNull(ContentField::where('key', 'visits')->first());
    }

    public function test_a_weight_out_of_range_is_rejected(): void
    {
        ContentType::create(['key' => 'news', 'name' => 'News']);

        foreach ([-1, 70000, 'abc', 1.5] as $weight) {
            $this->admin()->post('/admin/content-types/news/fields', $this->payload(['weight' => $weight]))
                ->assertSessionHasErrors('weight');
        }

        $this->assertNull(ContentField::where('key', 'visits')->first());
    }

    public function test_is_system_in_the_request_is_ignored(): void
    {
        ContentType::create(['key' => 'news', 'name' => 'News']);

        $this->admin()->post('/admin/content-types/news/fields', $this->payload(['is_system' => true, 'content_type' => 'other']))
            ->assertSessionHasNoErrors();

        $field = ContentField::where('key', 'visits')->firstOrFail();
        $this->assertFalse($field->is_system);
        $this->assertSame('news', $field->content_type);
    }

    public function test_a_missing_or_non_content_type_is_not_found(): void
    {
        ContentType::create(['key' => 'home', 'name' => 'Home', 'kind' => 'page']);
        ContentType::create(['key' => 'hero', 'name' => 'Hero', 'kind' => 'block']);

        foreach (['missing', 'home', 'hero'] as $key) {
            $this->admin()->get("/admin/content-types/{$key}/fields/create")->assertNotFound();
            $this->admin()->post("/admin/content-types/{$key}/fields", $this->payload())->assertNotFound();
        }

        $this->assertNull(ContentField::where('key', 'visits')->first());
    }

    public function test_max_items_defaults_to_zero_and_is_saved_for_a_multiple_field(): void
    {
        ContentType::create(['key' => 'news', 'name' => 'News']);

        $this->admin()->post('/admin/content-types/news/fields', $this->payload(['key' => 'plain', 'type' => 'string', 'is_multiple' => true]))
            ->assertSessionHasNoErrors();
        $this->admin()->post('/admin/content-types/news/fields', $this->payload(['key' => 'gallery', 'type' => 'string', 'is_multiple' => true, 'max_items' => 5]))
            ->assertSessionHasNoErrors();

        $this->assertSame(0, ContentField::where('key', 'plain')->value('max_items'));
        $this->assertSame(5, ContentField::where('key', 'gallery')->value('max_items'));
    }

    public function test_max_items_is_reset_for_a_single_field(): void
    {
        ContentType::create(['key' => 'news', 'name' => 'News']);

        $this->admin()->post('/admin/content-types/news/fields', $this->payload(['max_items' => 5]))->assertSessionHasNoErrors();

        $this->assertSame(0, ContentField::where('key', 'visits')->value('max_items'));
    }

    public function test_max_items_must_be_an_integer_in_range(): void
    {
        ContentType::create(['key' => 'news', 'name' => 'News']);

        foreach ([-1, 65536, 'abc'] as $bad) {
            $this->admin()->post('/admin/content-types/news/fields', $this->payload(['is_multiple' => true, 'max_items' => $bad]))
                ->assertSessionHasErrors('max_items');
        }
        $this->assertSame(7, ContentField::where('content_type', 'news')->count());
    }

    public function test_reserved_keys_are_rejected(): void
    {
        ContentType::create(['key' => 'news', 'name' => 'News']);

        foreach (ContentField::RESERVED_KEYS as $key) {
            $this->admin()->post('/admin/content-types/news/fields', $this->payload(['key' => $key]))
                ->assertSessionHasErrors(['key' => 'This key is reserved.']);
        }

        $this->assertSame(7, ContentField::where('content_type', 'news')->count());
    }

    public function test_node_service_attribute_keys_are_reserved(): void
    {
        ContentType::create(['key' => 'news', 'name' => 'News']);

        foreach (['is_active', 'created_at', 'updated_at', 'unpublished_at', 'views', 'author'] as $key) {
            $this->assertContains($key, ContentField::RESERVED_KEYS);
            $this->admin()->post('/admin/content-types/news/fields', $this->payload(['key' => $key]))
                ->assertSessionHasErrors(['key' => 'This key is reserved.']);
            $this->assertFalse(ContentField::where('content_type', 'news')->where('key', $key)->exists());
        }
    }

    public function test_system_field_keys_are_reserved(): void
    {
        ContentType::create(['key' => 'news', 'name' => 'News']);

        foreach (['meta_title', 'meta_description', 'meta_image', 'section_title', 'section_description', 'section_cover'] as $key) {
            $this->admin()->post('/admin/content-types/news/fields', $this->payload(['key' => $key]))
                ->assertSessionHasErrors(['key' => 'This key is reserved.']);
        }

        $this->assertSame(7, ContentField::where('content_type', 'news')->count());
    }

    public function test_a_non_reserved_key_passes(): void
    {
        ContentType::create(['key' => 'news', 'name' => 'News']);

        $this->admin()->post('/admin/content-types/news/fields', $this->payload(['key' => 'visits']))
            ->assertSessionHasNoErrors();

        $this->assertTrue(ContentField::where('content_type', 'news')->where('key', 'visits')->exists());
    }

    public function test_the_model_rejects_a_reserved_key_but_keeps_existing_fields_updatable(): void
    {
        ContentType::create(['key' => 'news', 'name' => 'News']);

        try {
            ContentField::create(['content_type' => 'news', 'key' => 'status', 'name' => 'Status', 'type' => 'string']);
            $this->fail('A reserved key must be rejected for a new field.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('key', $e->errors());
        }

        // A field created before the key was reserved: bypass the model event to simulate it.
        DB::table('content_fields')->insert([
            'content_type' => 'news', 'key' => 'status', 'name' => 'Status', 'type' => 'string',
        ]);
        $field = ContentField::where('key', 'status')->first();

        $field->update(['name' => 'Renamed']);

        $this->assertSame('Renamed', $field->fresh()->name);
    }

    public function test_hide_label_is_off_by_default_and_saved_when_on(): void
    {
        ContentType::create(['key' => 'news', 'name' => 'News']);

        $this->admin()->post('/admin/content-types/news/fields', $this->payload())->assertSessionHasNoErrors();
        $this->assertFalse(ContentField::where('key', 'visits')->firstOrFail()->hide_label);

        $this->admin()->post('/admin/content-types/news/fields', $this->payload(['key' => 'quiet', 'hide_label' => true]))->assertSessionHasNoErrors();
        $this->assertTrue(ContentField::where('key', 'quiet')->firstOrFail()->hide_label);
    }

    public function test_a_non_boolean_hide_label_is_rejected(): void
    {
        ContentType::create(['key' => 'news', 'name' => 'News']);

        $this->admin()->post('/admin/content-types/news/fields', $this->payload(['hide_label' => 'maybe']))->assertSessionHasErrors('hide_label');
        $this->assertFalse(ContentField::where('key', 'visits')->exists());
    }
}
