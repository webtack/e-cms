<?php

namespace Webtack\Ecms\Tests\Feature\Admin;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Webtack\Ecms\Models\ContentNode;
use Webtack\Ecms\Models\ContentType;
use Webtack\Ecms\Tests\Fixtures\User;
use Webtack\Ecms\Tests\TestCase;

class ContentTypeCreateTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): static
    {
        return $this->actingAs(User::factory()->create());
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get('/admin/content-types/create')->assertRedirect('/login');
        $this->post('/admin/content-types', ['name' => 'News', 'key' => 'news'])->assertRedirect('/login');

        $this->assertSame(0, ContentType::count());
    }

    public function test_a_valid_parent_type_from_the_query_is_preselected(): void
    {
        ContentType::create(['key' => 'common', 'name' => 'Common']);

        $this->admin()->get('/admin/content-types/create?parent_type=common')
            ->assertInertia(fn (Assert $page) => $page->where('defaultParent', 'common'));

        $this->admin()->get('/admin/content-types/create')
            ->assertInertia(fn (Assert $page) => $page->where('defaultParent', null));
    }

    public function test_an_unknown_parent_type_or_one_of_another_kind_is_ignored(): void
    {
        ContentType::create(['key' => 'home', 'name' => 'Home', 'kind' => 'page']);

        $this->admin()->get('/admin/content-types/create?parent_type=missing')
            ->assertInertia(fn (Assert $page) => $page->where('defaultParent', null));
        $this->admin()->get('/admin/content-types/create?parent_type=home')
            ->assertInertia(fn (Assert $page) => $page->where('defaultParent', null));
    }

    public function test_the_routes_have_their_names(): void
    {
        $this->assertSame('/admin/content-types/create', route('admin.content-types.create', absolute: false));
        $this->assertSame('/admin/content-types', route('admin.content-types.store', absolute: false));
    }

    public function test_the_form_gets_only_content_types_as_parent_options_ordered_by_name(): void
    {
        ContentType::create(['key' => 'b_type', 'name' => 'banana']);
        ContentType::create(['key' => 'a_type', 'name' => 'Cherry', 'slug' => 'cherry']);
        ContentType::create(['key' => 'c_type', 'name' => 'Apple']);
        ContentType::create(['key' => 'home', 'name' => 'Home', 'kind' => 'page']);
        ContentType::create(['key' => 'hero', 'name' => 'Hero', 'kind' => 'block']);

        $this->admin()->get('/admin/content-types/create')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/ContentTypes/Create')
                ->where('parentOptions', [
                    ['key' => 'c_type', 'name' => 'Apple', 'slug' => null],
                    ['key' => 'b_type', 'name' => 'banana', 'slug' => null],
                    ['key' => 'a_type', 'name' => 'Cherry', 'slug' => 'cherry'],
                ]));
    }

    public function test_a_type_is_created_with_all_fields(): void
    {
        ContentType::create(['key' => 'article', 'name' => 'Article', 'slug' => 'article']);

        $this->admin()->post('/admin/content-types', [
            'name' => '  News  ',
            'key' => 'news',
            'slug' => 'article/latest-news',
            'parent_type' => 'article',
            'description' => 'The news feed',
        ])
            ->assertRedirect('/admin/content-types')
            ->assertSessionHas('success');

        $type = ContentType::findByKey('news');
        $this->assertSame('News', $type->name);
        $this->assertSame('article/latest-news', $type->slug);
        $this->assertSame('article', $type->parent_type);
        $this->assertSame('The news feed', $type->description);
        $this->assertSame('content', $type->kind);
        $this->assertSame(
            ['title', 'meta_title', 'meta_description', 'meta_image', 'section_title', 'section_description', 'section_cover'],
            $type->fields()->pluck('key')->all(),
        );
        $this->assertTrue($type->fields()->first()->is_system);
    }

    public function test_the_success_message_is_translated(): void
    {
        $user = User::factory()->create(['locale' => 'uk']);

        $this->actingAs($user)->post('/admin/content-types', ['name' => 'News', 'key' => 'news'])
            ->assertSessionHas('success', __('Content type created.'));
    }

    public function test_minimal_input_creates_a_type_without_optional_values(): void
    {
        $this->admin()->post('/admin/content-types', [
            'name' => 'News', 'key' => 'news', 'slug' => '', 'parent_type' => '', 'description' => '',
        ])->assertRedirect('/admin/content-types');

        $type = ContentType::findByKey('news');
        $this->assertNull($type->slug);
        $this->assertNull($type->parent_type);
        $this->assertNull($type->description);
    }

    public function test_name_and_key_are_required(): void
    {
        $this->admin()->from('/admin/content-types/create')
            ->post('/admin/content-types', ['name' => '', 'key' => '', 'description' => 'kept'])
            ->assertRedirect('/admin/content-types/create')
            ->assertSessionHasErrors(['name', 'key']);

        $this->assertSame(0, ContentType::count());
    }

    public function test_the_key_must_be_valid_and_free(): void
    {
        ContentType::create(['key' => 'news', 'name' => 'News']);

        foreach (['News 1', '1news', 'Upper', str_repeat('a', 65), 'news'] as $key) {
            $this->admin()->post('/admin/content-types', ['name' => 'X', 'key' => $key])
                ->assertSessionHasErrors('key');
        }

        $this->assertSame(1, ContentType::count());
    }

    public function test_the_slug_must_be_valid_and_free_in_any_case(): void
    {
        ContentType::create(['key' => 'news', 'name' => 'News', 'slug' => 'news']);

        foreach (['Про нас', 'news_1', '-news', 'a--b', 'news', 'NEWS', 'News'] as $slug) {
            $this->admin()->post('/admin/content-types', ['name' => 'X', 'key' => 'other', 'slug' => $slug])
                ->assertSessionHasErrors('slug');
        }

        $this->assertNull(ContentType::findByKey('other'));
    }

    public function test_the_parent_must_be_an_existing_content_type(): void
    {
        ContentType::create(['key' => 'home', 'name' => 'Home', 'kind' => 'page']);
        ContentType::create(['key' => 'hero', 'name' => 'Hero', 'kind' => 'block']);

        foreach (['home', 'hero', 'missing'] as $parent) {
            $this->admin()->post('/admin/content-types', ['name' => 'X', 'key' => 'other', 'parent_type' => $parent])
                ->assertSessionHasErrors('parent_type');
        }

        $this->assertNull(ContentType::findByKey('other'));
    }

    public function test_the_kind_in_the_request_is_ignored(): void
    {
        $this->admin()->post('/admin/content-types', ['name' => 'X', 'key' => 'other', 'kind' => 'page'])
            ->assertRedirect('/admin/content-types');

        $this->assertSame('content', ContentType::findByKey('other')->kind);
    }

    public function test_a_reserved_key_is_rejected_next_to_the_key_field(): void
    {
        $admin = $this->admin();

        foreach (ContentType::RESERVED_KEYS as $key) {
            $admin->post('/admin/content-types', ['name' => 'X', 'key' => $key])
                ->assertSessionHasErrors(['key' => 'This key is reserved.']);
        }

        $admin->post('/admin/content-types', ['name' => 'X', 'key' => 'pages_list'])->assertSessionHasNoErrors();
        $this->assertNull(ContentType::findByKey('pages'));
        $this->assertNotNull(ContentType::findByKey('pages_list'));
    }

    public function test_a_slug_of_a_page_or_a_reserved_path_is_rejected_next_to_the_slug_field(): void
    {
        ContentType::create(['key' => 'home', 'name' => 'Home', 'kind' => 'page']);
        ContentNode::factory()->create(['content_type' => 'home', 'slug' => 'about']);
        $admin = $this->admin();

        $admin->post('/admin/content-types', ['name' => 'X', 'key' => 'x', 'slug' => 'about'])
            ->assertSessionHasErrors(['slug' => 'This slug is already used by a page.']);
        $admin->post('/admin/content-types', ['name' => 'X', 'key' => 'x', 'slug' => 'admin'])
            ->assertSessionHasErrors(['slug' => 'This slug is reserved.']);

        $this->assertNull(ContentType::findByKey('x'));
    }

    public function test_a_slug_of_several_segments_is_created_without_a_parent(): void
    {
        $this->admin()->post('/admin/content-types', ['name' => 'Institutions', 'key' => 'inst', 'slug' => 'common/institutions'])
            ->assertSessionHasNoErrors();

        $this->assertSame('common/institutions', ContentType::findByKey('inst')->slug);
    }

    public function test_a_child_slug_needs_the_prefix_of_the_parent_next_to_the_slug_field(): void
    {
        ContentType::create(['key' => 'common', 'name' => 'Common', 'slug' => 'common']);
        $admin = $this->admin();

        foreach (['institutions', 'other/institutions', 'common', 'commonplace/x'] as $slug) {
            $admin->from('/admin/content-types/create')
                ->post('/admin/content-types', ['name' => 'Inst', 'key' => 'inst', 'slug' => $slug, 'parent_type' => 'common'])
                ->assertRedirect('/admin/content-types/create')
                ->assertSessionHasErrors('slug')
                ->assertSessionHasInput('name', 'Inst');
        }

        $this->assertNull(ContentType::findByKey('inst'));

        $admin->post('/admin/content-types', ['name' => 'Inst', 'key' => 'inst', 'slug' => 'common/institutions', 'parent_type' => 'common'])
            ->assertSessionHasNoErrors();
        $this->assertSame('common/institutions', ContentType::findByKey('inst')->slug);
    }

    public function test_a_child_slug_needs_a_parent_with_a_slug(): void
    {
        ContentType::create(['key' => 'bare', 'name' => 'Bare']);

        $this->admin()->post('/admin/content-types', ['name' => 'Inst', 'key' => 'inst', 'slug' => 'common/institutions', 'parent_type' => 'bare'])
            ->assertSessionHasErrors(['slug' => __('The parent type must have a slug first.')]);

        $this->admin()->post('/admin/content-types', ['name' => 'Inst', 'key' => 'inst', 'slug' => '', 'parent_type' => 'bare'])
            ->assertSessionHasNoErrors();
    }

    public function test_a_reserved_first_segment_of_a_nested_slug_is_rejected(): void
    {
        $this->admin()->post('/admin/content-types', ['name' => 'X', 'key' => 'x', 'slug' => 'admin/reports'])
            ->assertSessionHasErrors(['slug' => 'This slug is reserved.']);

        $this->admin()->post('/admin/content-types', ['name' => 'X', 'key' => 'x', 'slug' => 'news/admin'])
            ->assertSessionHasNoErrors();
    }

    public function test_bad_separators_in_a_slug_are_rejected(): void
    {
        foreach (['/news', 'news/', 'a//b', 'a/-b'] as $slug) {
            $this->admin()->post('/admin/content-types', ['name' => 'X', 'key' => 'x', 'slug' => $slug])
                ->assertSessionHasErrors('slug');
        }
    }
}
