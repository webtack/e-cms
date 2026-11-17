<?php

namespace Webtack\Ecms\Tests\Feature\Admin;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Webtack\Ecms\Content\ContentNodeCreator;
use Webtack\Ecms\Content\ContentValueWriter;
use Webtack\Ecms\Content\ValueOwner;
use Webtack\Ecms\Models\ContentField;
use Webtack\Ecms\Models\ContentType;
use Webtack\Ecms\Tests\Fixtures\User;
use Webtack\Ecms\Tests\TestCase;

class ContentFieldIndexTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): static
    {
        return $this->actingAs(User::factory()->create());
    }

    public function test_guest_is_redirected_to_login(): void
    {
        ContentType::create(['key' => 'news', 'name' => 'News']);

        $this->get('/admin/content-types/news/fields')->assertRedirect('/login');
    }

    public function test_unverified_user_is_redirected_to_the_notice(): void
    {
        ContentType::create(['key' => 'news', 'name' => 'News']);

        $this->actingAs(User::factory()->unverified()->create())
            ->get('/admin/content-types/news/fields')
            ->assertRedirect('/email/verify');
    }

    public function test_the_routes_have_their_names(): void
    {
        $this->assertSame('/admin/content-types/news/fields', route('admin.content-types.fields.index', 'news', false));
        $this->assertSame('/admin/content-types/news/fields/create', route('admin.content-types.fields.create', 'news', false));
        $this->assertSame('/admin/content-types/news/fields', route('admin.content-types.fields.store', 'news', false));
        $this->assertSame('/admin/content-types/news/fields/order', route('admin.content-types.fields.reorder', 'news', false));
        $this->assertSame('/admin/content-types/news/fields/visits/edit', route('admin.content-types.fields.edit', ['news', 'visits'], false));
        $this->assertSame('/admin/content-types/news/fields/visits', route('admin.content-types.fields.update', ['news', 'visits'], false));
        $this->assertSame('/admin/content-types/news/fields/visits', route('admin.content-types.fields.destroy', ['news', 'visits'], false));
    }

    public function test_the_fields_come_ordered_by_weight_with_the_type(): void
    {
        ContentType::create(['key' => 'news', 'name' => 'News']);
        ContentField::create(['content_type' => 'news', 'key' => 'body', 'name' => 'Body', 'type' => 'text', 'weight' => 20]);
        ContentField::create(['content_type' => 'news', 'key' => 'summary', 'name' => 'Summary', 'type' => 'text', 'weight' => 10]);
        ContentType::create(['key' => 'other', 'name' => 'Other']);

        $this->admin()->get('/admin/content-types/news/fields')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/ContentFields/Index')
                ->where('type', ['key' => 'news', 'name' => 'News', 'slug' => null, 'children_count' => 0, 'ancestors' => []])
                ->has('fields', 3)
                ->where('fields.0.key', 'title')
                ->where('fields.1.key', 'summary')
                ->where('fields.2.key', 'body')
                ->where('fields.0', [
                    'key' => 'title', 'name' => 'Title', 'type' => 'string', 'is_multiple' => false, 'max_items' => 0, 'is_required' => true,
                    'is_translatable' => true, 'hide_label' => false, 'is_system' => true, 'weight' => 0, 'has_values' => false,
                ]));
    }

    public function test_the_type_prop_carries_the_slug_for_the_preview_link(): void
    {
        ContentType::create(['key' => 'news', 'name' => 'News', 'slug' => 'news-list']);

        $this->admin()->get('/admin/content-types/news/fields')
            ->assertInertia(fn (Assert $page) => $page
                ->where('type', ['key' => 'news', 'name' => 'News', 'slug' => 'news-list', 'children_count' => 0, 'ancestors' => []]));
    }

    public function test_has_values_marks_only_fields_with_stored_values(): void
    {
        $type = ContentType::create(['key' => 'news', 'name' => 'News']);
        ContentField::create(['content_type' => 'news', 'key' => 'visits', 'name' => 'Visits', 'type' => 'integer', 'is_required' => false]);
        ContentField::create(['content_type' => 'news', 'key' => 'note', 'name' => 'Note', 'type' => 'string']);
        app(ContentNodeCreator::class)->create($type, ['title' => 'Hello', 'visits' => 5], authorId: $this->authorId());

        $this->admin()->get('/admin/content-types/news/fields')
            ->assertInertia(fn (Assert $page) => $page
                ->where('fields.0.has_values', true)
                ->where('fields.1.key', 'visits')
                ->where('fields.1.has_values', true)
                ->where('fields.2.key', 'note')
                ->where('fields.2.has_values', false));
    }

    public function test_values_of_the_type_itself_do_not_count_as_filled_in_records(): void
    {
        $type = ContentType::create(['key' => 'news', 'name' => 'News']);
        app(ContentValueWriter::class)->write(ValueOwner::forType($type), ['section_title' => 'News']);
        app(ContentNodeCreator::class)->create($type, ['title' => 'Hello'], authorId: $this->authorId());

        $this->admin()->get('/admin/content-types/news/fields')
            ->assertInertia(fn (Assert $page) => $page
                ->where('fields', fn ($fields) => collect($fields)->pluck('has_values', 'key')->all() === ['title' => true]));
    }

    public function test_panel_fields_are_hidden_for_every_kind_and_title_stays(): void
    {
        ContentType::create(['key' => 'news', 'name' => 'News']);
        ContentType::create(['key' => 'home', 'name' => 'Home', 'kind' => 'page']);
        ContentType::create(['key' => 'hero', 'name' => 'Hero', 'kind' => 'block']);

        foreach (['content-types/news', 'page-types/home', 'block-types/hero'] as $path) {
            $this->admin()->get("/admin/{$path}/fields")
                ->assertInertia(fn (Assert $page) => $page->where('fields', fn ($fields) => collect($fields)->pluck('key')->all() === ['title']));
        }
    }

    public function test_a_missing_or_non_content_type_is_not_found(): void
    {
        ContentType::create(['key' => 'home', 'name' => 'Home', 'kind' => 'page']);
        ContentType::create(['key' => 'hero', 'name' => 'Hero', 'kind' => 'block']);

        foreach (['missing', 'home', 'hero'] as $key) {
            $this->admin()->get("/admin/content-types/{$key}/fields")->assertNotFound();
        }
    }
}
