<?php

namespace Webtack\Ecms\Tests\Feature\Admin;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Webtack\Ecms\Content\ContentNodeCreator;
use Webtack\Ecms\Models\ContentField;
use Webtack\Ecms\Models\ContentType;
use Webtack\Ecms\Models\Vocabulary;
use Webtack\Ecms\Tests\Fixtures\User;
use Webtack\Ecms\Tests\TestCase;

class ContentFieldEditTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): static
    {
        return $this->actingAs(User::factory()->create());
    }

    private function newsWithViews(): ContentType
    {
        $type = ContentType::create(['key' => 'news', 'name' => 'News']);
        ContentField::create(['content_type' => 'news', 'key' => 'visits', 'name' => 'Visits', 'type' => 'integer', 'is_required' => false, 'weight' => 10]);

        return $type;
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return ['name' => 'Visits', 'type' => 'integer', 'is_multiple' => false, 'is_required' => false, 'is_translatable' => false, 'weight' => 10, ...$overrides];
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->newsWithViews();

        $this->get('/admin/content-types/news/fields/visits/edit')->assertRedirect('/login');
        $this->put('/admin/content-types/news/fields/visits', $this->payload(['name' => 'Changed']))->assertRedirect('/login');

        $this->assertSame('Visits', ContentField::where('key', 'visits')->value('name'));
    }

    public function test_the_form_gets_the_field_with_has_values(): void
    {
        $type = $this->newsWithViews();
        app(ContentNodeCreator::class)->create($type, ['title' => 'Hello'], authorId: $this->authorId());

        $this->admin()->get('/admin/content-types/news/fields/visits/edit')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/ContentFields/Edit')
                ->where('type', ['key' => 'news', 'name' => 'News', 'slug' => null, 'children_count' => 0, 'ancestors' => []])
                ->where('field', [
                    'key' => 'visits', 'name' => 'Visits', 'type' => 'integer', 'is_multiple' => false, 'max_items' => 0, 'is_required' => false,
                    'description' => null, 'is_translatable' => false, 'hide_label' => false, 'is_system' => false, 'weight' => 10, 'settings' => null, 'has_values' => false,
                ])
                ->where('typeOptions', array_keys(config('content.field_types')))
                ->where('typeOptions', fn ($options) => in_array('term', $options->toArray(), true) && in_array('youtube', $options->toArray(), true))
                ->where('vocabularies', []));

        $this->admin()->get('/admin/content-types/news/fields/title/edit')
            ->assertInertia(fn (Assert $page) => $page->where('field.is_system', true)->where('field.has_values', true));
    }

    public function test_a_panel_field_cannot_be_opened_or_saved(): void
    {
        ContentType::create(['key' => 'news', 'name' => 'News']);

        foreach (['meta_title', 'meta_description', 'meta_image', 'section_title', 'section_description', 'section_cover'] as $key) {
            $this->admin()->get("/admin/content-types/news/fields/{$key}/edit")->assertNotFound();
            $this->admin()->put("/admin/content-types/news/fields/{$key}", $this->payload(['name' => 'Changed', 'type' => 'string']))->assertNotFound();
            $this->assertNotSame('Changed', ContentField::where('content_type', 'news')->where('key', $key)->value('name'));
        }
    }

    public function test_an_unknown_foreign_or_non_content_field_is_not_found(): void
    {
        $this->newsWithViews();
        ContentType::create(['key' => 'other', 'name' => 'Other']);
        ContentField::create(['content_type' => 'other', 'key' => 'price', 'name' => 'Price', 'type' => 'decimal']);
        ContentType::create(['key' => 'home', 'name' => 'Home', 'kind' => 'page']);

        foreach (['/admin/content-types/news/fields/missing', '/admin/content-types/news/fields/price', '/admin/content-types/home/fields/title'] as $url) {
            $this->admin()->get("{$url}/edit")->assertNotFound();
            $this->admin()->put($url, $this->payload(['name' => 'Changed']))->assertNotFound();
            $this->admin()->delete($url)->assertNotFound();
        }

        $this->assertSame('Price', ContentField::where('key', 'price')->value('name'));
    }

    public function test_name_flags_and_weight_are_updated(): void
    {
        $this->newsWithViews();

        $this->admin()->from('/admin/content-types/news/fields/visits')->put('/admin/content-types/news/fields/visits', $this->payload([
            'name' => 'Page visits', 'is_required' => true, 'is_translatable' => true, 'weight' => 42,
        ]))
            ->assertRedirect('/admin/content-types/news/fields/visits')
            ->assertSessionHas('success');

        $field = ContentField::where('key', 'visits')->firstOrFail();
        $this->assertSame('Page visits', $field->name);
        $this->assertTrue($field->is_required);
        $this->assertTrue($field->is_translatable);
        $this->assertSame(42, $field->weight);
    }

    public function test_an_existing_field_with_a_reserved_key_is_still_updated(): void
    {
        $this->newsWithViews();
        // Created before the key was reserved: bypass the model event to simulate it.
        DB::table('content_fields')->insert(['content_type' => 'news', 'key' => 'created_at', 'name' => 'Created', 'type' => 'string', 'weight' => 20]);

        $this->admin()->put('/admin/content-types/news/fields/created_at', $this->payload(['name' => 'Renamed', 'type' => 'string', 'weight' => 21]))
            ->assertSessionHasNoErrors();

        $this->assertSame('Renamed', ContentField::where('content_type', 'news')->where('key', 'created_at')->value('name'));
    }

    public function test_key_and_system_flag_in_the_request_are_ignored(): void
    {
        $this->newsWithViews();

        $this->admin()->put('/admin/content-types/news/fields/visits', $this->payload(['key' => 'renamed', 'is_system' => true, 'content_type' => 'other']))
            ->assertSessionHasNoErrors();

        $field = ContentField::where('key', 'visits')->firstOrFail();
        $this->assertFalse($field->is_system);
        $this->assertSame('news', $field->content_type);
        $this->assertNull(ContentField::where('key', 'renamed')->first());
    }

    public function test_validation_matches_the_create_form(): void
    {
        $this->newsWithViews();

        $this->admin()->put('/admin/content-types/news/fields/visits', ['name' => '', 'type' => 'nonsense', 'weight' => 70000])
            ->assertSessionHasErrors(['name', 'type', 'weight']);

        $this->assertSame('Visits', ContentField::where('key', 'visits')->value('name'));
    }

    public function test_the_type_of_a_field_without_values_is_not_changed(): void
    {
        $this->newsWithViews();

        $this->admin()->from('/admin/content-types/news/fields/visits/edit')
            ->put('/admin/content-types/news/fields/visits', $this->payload(['type' => 'decimal']))
            ->assertRedirect('/admin/content-types/news/fields/visits/edit')
            ->assertSessionHasErrors('type');

        $this->assertSame('integer', ContentField::where('key', 'visits')->value('type'));
    }

    public function test_term_is_offered_and_accepted_only_for_content_types(): void
    {
        ContentType::create(['key' => 'home', 'name' => 'Home', 'kind' => 'page']);
        ContentField::create(['content_type' => 'home', 'key' => 'summary', 'name' => 'Summary', 'type' => 'text', 'weight' => 10]);
        Vocabulary::create(['key' => 'tags', 'name' => 'Tags']);

        $this->admin()->get('/admin/page-types/home/fields/summary/edit')
            ->assertInertia(fn (Assert $page) => $page->where('typeOptions', fn ($options) => ! in_array('term', $options->toArray(), true)));

        $this->admin()->put('/admin/page-types/home/fields/summary', $this->payload(['key' => 'summary', 'type' => 'term', 'settings' => ['vocabulary' => 'tags']]))
            ->assertSessionHasErrors('type');

        $this->assertSame('text', ContentField::where('key', 'summary')->value('type'));
    }

    public function test_the_current_type_with_a_new_name_is_saved(): void
    {
        $this->newsWithViews();

        $this->admin()->put('/admin/content-types/news/fields/visits', $this->payload(['name' => 'Hits']))
            ->assertSessionHasNoErrors();

        $this->assertSame('Hits', ContentField::where('key', 'visits')->value('name'));
    }

    public function test_the_type_of_a_field_with_values_is_not_changed(): void
    {
        $type = $this->newsWithViews();
        app(ContentNodeCreator::class)->create($type, ['title' => 'Hello', 'visits' => 5], authorId: $this->authorId());

        $this->admin()->from('/admin/content-types/news/fields/visits/edit')
            ->put('/admin/content-types/news/fields/visits', $this->payload(['type' => 'decimal', 'name' => 'Changed']))
            ->assertRedirect('/admin/content-types/news/fields/visits/edit')
            ->assertSessionHasErrors('type');

        $field = ContentField::where('key', 'visits')->firstOrFail();
        $this->assertSame('integer', $field->type);
        $this->assertSame('Visits', $field->name);
        $this->assertSame(1, DB::table('content_values_integer')->where('field_key', 'visits')->count());
    }

    public function test_forbidden_changes_of_the_title_field_are_rejected(): void
    {
        ContentType::create(['key' => 'news', 'name' => 'News']);

        foreach (['type' => 'text', 'is_multiple' => true, 'is_required' => false] as $attribute => $value) {
            $this->admin()->put('/admin/content-types/news/fields/title', $this->payload([
                'name' => 'Title', 'type' => 'string', 'is_multiple' => false, 'is_required' => true, 'weight' => 0, $attribute => $value,
            ]))->assertSessionHasErrors($attribute);
        }

        $title = ContentField::where('key', 'title')->firstOrFail();
        $this->assertSame('string', $title->type);
        $this->assertFalse($title->is_multiple);
        $this->assertTrue($title->is_required);
    }

    public function test_allowed_changes_of_the_title_field_are_saved(): void
    {
        ContentType::create(['key' => 'news', 'name' => 'News']);

        $this->admin()->put('/admin/content-types/news/fields/title', $this->payload([
            'name' => 'Headline', 'type' => 'string', 'is_required' => true, 'is_translatable' => false, 'weight' => 5,
        ]))->assertSessionHasNoErrors();

        $title = ContentField::where('key', 'title')->firstOrFail();
        $this->assertSame('Headline', $title->name);
        $this->assertFalse($title->is_translatable);
        $this->assertSame(5, $title->weight);
    }

    public function test_the_description_is_saved_changed_and_cleared(): void
    {
        $this->newsWithViews();

        $this->admin()->put('/admin/content-types/news/fields/visits', $this->payload(['description' => 'How many times it was read']))->assertSessionHasNoErrors();
        $this->assertSame('How many times it was read', ContentField::where('key', 'visits')->value('description'));

        $this->admin()->get('/admin/content-types/news/fields/visits/edit')
            ->assertInertia(fn (Assert $page) => $page->where('field.description', 'How many times it was read'));

        $this->admin()->put('/admin/content-types/news/fields/visits', $this->payload(['description' => '  ']))->assertSessionHasNoErrors();
        $this->assertNull(ContentField::where('key', 'visits')->value('description'));
    }

    public function test_a_description_over_1000_characters_is_rejected(): void
    {
        $this->newsWithViews();

        $this->admin()->put('/admin/content-types/news/fields/visits', $this->payload(['description' => str_repeat('a', 1001)]))
            ->assertSessionHasErrors('description');
        $this->admin()->put('/admin/content-types/news/fields/visits', $this->payload(['description' => str_repeat('a', 1000)]))
            ->assertSessionHasNoErrors();
    }

    public function test_the_title_field_description_is_changed_while_other_locks_stay(): void
    {
        ContentType::create(['key' => 'news', 'name' => 'News']);

        $this->admin()->put('/admin/content-types/news/fields/title', $this->payload(['type' => 'string', 'is_required' => true, 'description' => 'Short title']))
            ->assertSessionHasNoErrors();
        $this->assertSame('Short title', ContentField::where('key', 'title')->value('description'));

        $this->admin()->put('/admin/content-types/news/fields/title', $this->payload(['type' => 'string', 'is_required' => false, 'description' => 'x']))
            ->assertSessionHasErrors('is_required');
    }

    public function test_max_items_is_saved_and_shown_in_the_form(): void
    {
        $this->newsWithViews();

        $this->admin()->put('/admin/content-types/news/fields/visits', $this->payload(['is_multiple' => true, 'max_items' => 3]))
            ->assertSessionHasNoErrors();
        $this->admin()->get('/admin/content-types/news/fields/visits/edit')
            ->assertInertia(fn (Assert $page) => $page->where('field.max_items', 3));
    }

    public function test_max_items_is_reset_when_the_field_stops_being_multiple(): void
    {
        $this->newsWithViews();
        $this->admin()->put('/admin/content-types/news/fields/visits', $this->payload(['is_multiple' => true, 'max_items' => 3]));

        $this->admin()->put('/admin/content-types/news/fields/visits', $this->payload(['is_multiple' => false, 'max_items' => 3]))
            ->assertSessionHasNoErrors();

        $this->assertSame(0, ContentField::where('key', 'visits')->value('max_items'));
    }

    public function test_max_items_rejects_invalid_values(): void
    {
        $this->newsWithViews();

        foreach ([-1, 65536, 'abc'] as $bad) {
            $this->admin()->put('/admin/content-types/news/fields/visits', $this->payload(['is_multiple' => true, 'max_items' => $bad]))
                ->assertSessionHasErrors('max_items');
        }
        $this->assertSame(0, ContentField::where('key', 'visits')->value('max_items'));
    }

    public function test_hide_label_is_turned_on_and_off_again(): void
    {
        $this->newsWithViews();

        $this->admin()->put('/admin/content-types/news/fields/visits', $this->payload(['hide_label' => true]))->assertSessionHasNoErrors();
        $this->assertTrue(ContentField::where('key', 'visits')->firstOrFail()->hide_label);

        $this->admin()->get('/admin/content-types/news/fields/visits/edit')
            ->assertInertia(fn (Assert $page) => $page->where('field.hide_label', true));

        $this->admin()->put('/admin/content-types/news/fields/visits', $this->payload(['hide_label' => false]))->assertSessionHasNoErrors();
        $this->assertFalse(ContentField::where('key', 'visits')->firstOrFail()->hide_label);
    }

    public function test_hide_label_is_allowed_for_the_system_title_field(): void
    {
        ContentType::create(['key' => 'news', 'name' => 'News']);

        $this->admin()->put('/admin/content-types/news/fields/title', $this->payload([
            'name' => 'Title', 'type' => 'string', 'is_required' => true, 'hide_label' => true,
        ]))->assertSessionHasNoErrors();

        $this->assertTrue(ContentField::where('key', 'title')->firstOrFail()->hide_label);
    }

    public function test_a_non_boolean_hide_label_is_rejected_on_update(): void
    {
        $this->newsWithViews();

        $this->admin()->put('/admin/content-types/news/fields/visits', $this->payload(['hide_label' => 'maybe']))->assertSessionHasErrors('hide_label');
        $this->assertFalse(ContentField::where('key', 'visits')->firstOrFail()->hide_label);
    }
}
