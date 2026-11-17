<?php

namespace Webtack\Ecms\Tests\Feature\Admin;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;
use Webtack\Ecms\Models\ContentField;
use Webtack\Ecms\Models\ContentNode;
use Webtack\Ecms\Models\ContentType;
use Webtack\Ecms\Tests\Fixtures\User;
use Webtack\Ecms\Tests\TestCase;

class ContentTypeEditTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): static
    {
        return $this->actingAs(User::factory()->create());
    }

    public function test_guest_is_redirected_to_login(): void
    {
        ContentType::create(['key' => 'news', 'name' => 'News']);

        $this->get('/admin/content-types/news/edit')->assertRedirect('/login');
        $this->put('/admin/content-types/news', ['name' => 'Changed'])->assertRedirect('/login');

        $this->assertSame('News', ContentType::findByKey('news')->name);
    }

    public function test_the_routes_have_their_names(): void
    {
        $this->assertSame('/admin/content-types/news/edit', route('admin.content-types.edit', 'news', false));
        $this->assertSame('/admin/content-types/news', route('admin.content-types.update', 'news', false));
    }

    public function test_the_form_gets_the_type_and_parent_options_without_itself_and_descendants(): void
    {
        ContentType::create(['key' => 'a', 'name' => 'A', 'slug' => 'a-slug', 'description' => 'Root']);
        ContentType::create(['key' => 'b', 'name' => 'B', 'parent_type' => 'a']);
        ContentType::create(['key' => 'c', 'name' => 'C', 'parent_type' => 'b']);
        ContentType::create(['key' => 'other', 'name' => 'Other']);
        ContentType::create(['key' => 'home', 'name' => 'Home', 'kind' => 'page']);

        $this->admin()->get('/admin/content-types/a/edit')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/ContentTypes/Edit')
                ->where('type', [
                    'key' => 'a', 'name' => 'A', 'slug' => 'a-slug', 'parent_type' => null, 'description' => 'Root',
                    'children_count' => 1, 'ancestors' => [],
                ])
                ->where('parentOptions', [['key' => 'other', 'name' => 'Other', 'slug' => null]]));

        $this->admin()->get('/admin/content-types/c/edit')
            ->assertInertia(fn (Assert $page) => $page
                ->where('type.parent_type', 'b')
                ->where('parentOptions', [
                    ['key' => 'a', 'name' => 'A', 'slug' => 'a-slug'],
                    ['key' => 'b', 'name' => 'B', 'slug' => null],
                    ['key' => 'other', 'name' => 'Other', 'slug' => null],
                ]));
    }

    public function test_a_missing_or_non_content_type_is_not_found(): void
    {
        ContentType::create(['key' => 'home', 'name' => 'Home', 'kind' => 'page']);
        ContentType::create(['key' => 'hero', 'name' => 'Hero', 'kind' => 'block']);

        foreach (['missing', 'home', 'hero'] as $key) {
            $this->admin()->get("/admin/content-types/{$key}/edit")->assertNotFound();
            $this->admin()->put("/admin/content-types/{$key}", ['name' => 'Changed'])->assertNotFound();
        }

        $this->assertSame('Home', ContentType::findByKey('home')->name);
    }

    public function test_a_type_is_updated(): void
    {
        ContentType::create(['key' => 'article', 'name' => 'Article', 'slug' => 'article']);
        ContentType::create(['key' => 'news', 'name' => 'News']);

        $this->admin()->from('/admin/content-types/news')->put('/admin/content-types/news', [
            'name' => 'Latest', 'slug' => 'article/latest', 'parent_type' => 'article', 'description' => 'Fresh',
        ])
            ->assertRedirect('/admin/content-types/news')
            ->assertSessionHas('success');

        $type = ContentType::findByKey('news');
        $this->assertSame('Latest', $type->name);
        $this->assertSame('article/latest', $type->slug);
        $this->assertSame('article', $type->parent_type);
        $this->assertSame('Fresh', $type->description);
        $this->assertSame('news', $type->key);
        $this->assertSame('content', $type->kind);
    }

    public function test_saving_the_own_slug_is_not_a_conflict(): void
    {
        ContentType::create(['key' => 'news', 'name' => 'News', 'slug' => 'news']);

        $this->admin()->from('/admin/content-types/news')->put('/admin/content-types/news', ['name' => 'News 2', 'slug' => 'news'])
            ->assertSessionDoesntHaveErrors()
            ->assertRedirect('/admin/content-types/news');

        $this->assertSame('News 2', ContentType::findByKey('news')->name);
    }

    public function test_a_slug_of_another_type_is_rejected(): void
    {
        ContentType::create(['key' => 'news', 'name' => 'News']);
        ContentType::create(['key' => 'blog', 'name' => 'Blog', 'slug' => 'blog']);

        foreach (['blog', 'BLOG'] as $slug) {
            $this->admin()->put('/admin/content-types/news', ['name' => 'News', 'slug' => $slug])
                ->assertSessionHasErrors('slug');
        }

        $this->assertNull(ContentType::findByKey('news')->slug);
    }

    public function test_the_slug_can_be_cleared(): void
    {
        ContentType::create(['key' => 'news', 'name' => 'News', 'slug' => 'news']);

        $this->admin()->from('/admin/content-types/news')->put('/admin/content-types/news', ['name' => 'News', 'slug' => ''])
            ->assertRedirect('/admin/content-types/news');

        $this->assertNull(ContentType::findByKey('news')->slug);
    }

    public function test_the_key_and_kind_in_the_request_are_ignored(): void
    {
        ContentType::create(['key' => 'news', 'name' => 'News']);

        $this->admin()->from('/admin/content-types/news')->put('/admin/content-types/news', ['name' => 'Latest', 'key' => 'other', 'kind' => 'page'])
            ->assertRedirect('/admin/content-types/news');

        $this->assertNull(ContentType::findByKey('other'));
        $type = ContentType::findByKey('news');
        $this->assertSame('Latest', $type->name);
        $this->assertSame('content', $type->kind);
    }

    public function test_the_parent_cannot_be_the_type_itself_or_a_descendant(): void
    {
        ContentType::create(['key' => 'a', 'name' => 'A']);
        ContentType::create(['key' => 'b', 'name' => 'B', 'parent_type' => 'a']);
        ContentType::create(['key' => 'c', 'name' => 'C', 'parent_type' => 'b']);

        foreach (['a', 'b', 'c'] as $parent) {
            $this->admin()->put('/admin/content-types/a', ['name' => 'A', 'parent_type' => $parent])
                ->assertSessionHasErrors('parent_type');
        }

        $this->assertNull(ContentType::findByKey('a')->parent_type);
    }

    public function test_the_parent_must_be_a_content_type(): void
    {
        ContentType::create(['key' => 'news', 'name' => 'News']);
        ContentType::create(['key' => 'home', 'name' => 'Home', 'kind' => 'page']);

        $this->admin()->put('/admin/content-types/news', ['name' => 'News', 'parent_type' => 'home'])
            ->assertSessionHasErrors('parent_type');
    }

    public function test_the_name_is_required(): void
    {
        ContentType::create(['key' => 'news', 'name' => 'News']);

        $this->admin()->put('/admin/content-types/news', ['name' => ''])
            ->assertSessionHasErrors('name');

        $this->assertSame('News', ContentType::findByKey('news')->name);
    }

    private function newsWithFields(): ContentType
    {
        $type = ContentType::create(['key' => 'news', 'name' => 'News']);
        ContentField::create(['content_type' => 'news', 'key' => 'price', 'name' => 'Price', 'type' => 'integer', 'weight' => 10]);

        return $type;
    }

    /** @param  array<string, mixed>  $list */
    private function saveList(array $list): TestResponse
    {
        return $this->admin()->from('/admin/content-types/news')->put('/admin/content-types/news', ['name' => 'News', 'settings' => ['list' => $list]]);
    }

    public function test_the_list_settings_are_saved_with_service_keys_in_columns_and_sort(): void
    {
        $this->newsWithFields();

        $this->saveList([
            'visible' => ['price', 'status', 'is_active', 'created_at', 'unpublished_at'],
            'search' => ['title'],
            'sort' => ['field' => 'updated_at', 'direction' => 'desc'],
        ])->assertRedirect('/admin/content-types/news')->assertSessionHasNoErrors();

        $this->assertSame([
            'visible' => ['price', 'status', 'is_active', 'created_at', 'unpublished_at'],
            'search' => ['title'],
            'sort' => ['field' => 'updated_at', 'direction' => 'desc'],
        ], ContentType::findByKey('news')->settings['list']);
    }

    public function test_views_is_accepted_as_a_column_and_as_the_default_sort(): void
    {
        $this->newsWithFields();

        $this->saveList(['visible' => ['title', 'views'], 'sort' => ['field' => 'views', 'direction' => 'desc']])
            ->assertSessionHasNoErrors();

        $this->assertSame(
            ['visible' => ['title', 'views'], 'sort' => ['field' => 'views', 'direction' => 'desc']],
            ContentType::findByKey('news')->settings['list'],
        );
    }

    public function test_the_default_sort_and_empty_selections_are_not_stored(): void
    {
        $this->newsWithFields()->update(['settings' => ['list' => ['visible' => ['price']], 'other' => 1]]);

        $this->saveList(['visible' => [], 'search' => [], 'sort' => ['field' => 'created_at', 'direction' => 'asc']])
            ->assertSessionHasNoErrors();

        // The `list` key is gone, the other key stays.
        $this->assertSame(['other' => 1], ContentType::findByKey('news')->settings);
    }

    public function test_title_is_stored_at_its_position_and_alone_it_means_the_default(): void
    {
        $this->newsWithFields()->update(['settings' => ['list' => ['visible' => ['price']]]]);

        $this->saveList(['visible' => ['price', 'title', 'status']])->assertSessionHasNoErrors();
        $this->assertSame(['price', 'title', 'status'], ContentType::findByKey('news')->settings['list']['visible']);

        $this->saveList(['visible' => ['title']])->assertSessionHasNoErrors();
        $this->assertNull(ContentType::findByKey('news')->settings);

        $this->saveList(['visible' => ['title', 'price']])->assertSessionHasNoErrors();
        $this->assertSame(['title', 'price'], ContentType::findByKey('news')->settings['list']['visible']);

        $this->saveList(['visible' => ['title', 'title']])->assertSessionHasErrors('settings.list.visible.0');
    }

    public function test_empty_settings_become_null(): void
    {
        $this->newsWithFields()->update(['settings' => ['list' => ['visible' => ['price']]]]);

        $this->saveList(['visible' => []])->assertSessionHasNoErrors();
        $this->assertNull(ContentType::findByKey('news')->settings);
    }

    public function test_other_settings_keys_survive_and_a_request_without_settings_keeps_the_list(): void
    {
        $this->newsWithFields()->update(['settings' => ['list' => ['visible' => ['price']], 'other' => ['a' => 1]]]);

        $this->saveList(['search' => ['title']])->assertSessionHasNoErrors();
        $this->assertSame(['a' => 1], ContentType::findByKey('news')->settings['other']);
        $this->assertSame(['search' => ['title']], ContentType::findByKey('news')->settings['list']);

        $this->admin()->put('/admin/content-types/news', ['name' => 'Renamed'])->assertSessionHasNoErrors();
        $this->assertSame(['search' => ['title']], ContentType::findByKey('news')->settings['list']);
    }

    public function test_unknown_keys_and_a_bad_direction_are_rejected_next_to_the_settings(): void
    {
        $this->newsWithFields();

        $this->saveList(['visible' => ['missing']])->assertSessionHasErrors('settings');
        $this->saveList(['search' => ['status']])->assertSessionHasErrors('settings');
        $this->saveList(['sort' => ['field' => 'slug', 'direction' => 'asc']])->assertSessionHasErrors('settings');
        $this->saveList(['sort' => ['field' => 'price', 'direction' => 'up']])->assertSessionHasErrors('settings.list.sort.direction');
        $this->saveList(['unknown' => ['x']])->assertSessionHasErrors('settings.list');

        $this->assertNull(ContentType::findByKey('news')->settings);
    }

    public function test_the_form_gets_the_list_options_and_the_default_state_of_a_type_without_settings(): void
    {
        $this->newsWithFields();
        ContentField::create(['content_type' => 'news', 'key' => 'cover', 'name' => 'Cover', 'type' => 'image', 'weight' => 20]);

        $this->admin()->get('/admin/content-types/news/edit')
            ->assertInertia(fn (Assert $page) => $page
                ->where('listOptions.columns', fn ($columns) => collect($columns)->pluck('key')->all() === [
                    'title', 'meta_title', 'meta_description', 'meta_image', 'price', 'cover', 'slug', 'status', 'is_active', 'created_at', 'updated_at', 'published_at', 'unpublished_at', 'views', 'author',
                ])
                ->where('listOptions.searchFields', fn ($fields) => collect($fields)->pluck('key')->all() === ['title', 'meta_title', 'meta_description'])
                ->where('listOptions.current', [
                    'visible' => ['title', 'slug', 'status', 'published_at', 'author'],
                    'search' => ['title', 'meta_title', 'meta_description'],
                    'sort' => ['field' => 'created_at', 'direction' => 'asc'],
                ]));
    }

    public function test_the_sort_options_are_service_keys_and_sortable_fields_only(): void
    {
        $this->newsWithFields();
        ContentField::create(['content_type' => 'news', 'key' => 'cover', 'name' => 'Cover', 'type' => 'image', 'weight' => 20]);
        ContentField::create(['content_type' => 'news', 'key' => 'clip', 'name' => 'Clip', 'type' => 'youtube', 'weight' => 21]);

        $this->admin()->get('/admin/content-types/news/edit')
            ->assertInertia(fn (Assert $page) => $page
                ->where('listOptions.sortOptions', function ($options) {
                    $keys = collect($options)->pluck('key')->all();

                    return array_slice($keys, 0, 7) === ['status', 'is_active', 'created_at', 'updated_at', 'published_at', 'unpublished_at', 'views']
                        && in_array('price', $keys, true) && in_array('title', $keys, true)
                        && array_intersect(['cover', 'clip', 'slug'], $keys) === [];
                }));
    }

    public function test_the_form_gets_the_saved_list_settings_cleaned_of_stale_keys(): void
    {
        $type = $this->newsWithFields();
        $type->update(['settings' => ['list' => [
            'visible' => ['price', 'title', 'unpublished_at'], 'search' => ['title'], 'sort' => ['field' => 'updated_at', 'direction' => 'desc'],
        ]]]);
        // A field deleted later leaves its key behind in the settings.
        ContentField::create(['content_type' => 'news', 'key' => 'gone', 'name' => 'Gone', 'type' => 'string', 'weight' => 30]);
        $type->fresh()->update(['settings' => ['list' => ['visible' => ['price', 'gone', 'is_active'], 'sort' => ['field' => 'gone', 'direction' => 'desc']]]]);
        ContentField::where('key', 'gone')->delete();

        $this->admin()->get('/admin/content-types/news/edit')
            ->assertInertia(fn (Assert $page) => $page
                ->where('listOptions.current.visible', ['title', 'price', 'is_active'])
                ->where('listOptions.current.sort', ['field' => 'created_at', 'direction' => 'asc']));
    }

    public function test_the_edit_form_has_no_public_options_and_refuses_public_settings(): void
    {
        $this->newsWithFields();

        $this->admin()->get('/admin/content-types/news/edit')
            ->assertInertia(fn (Assert $page) => $page->has('listOptions')->missing('publicOptions'));

        $this->admin()->put('/admin/content-types/news', ['name' => 'News', 'settings' => ['public' => ['per_page' => 5]]])
            ->assertSessionHasErrors('settings');
        $this->assertNull(ContentType::findByKey('news')->settings);
    }

    public function test_saving_the_list_settings_keeps_the_public_ones(): void
    {
        $this->newsWithFields()->update(['settings' => ['public' => ['per_page' => 5]]]);

        $this->saveList(['search' => ['title']])->assertSessionHasNoErrors();

        $this->assertSame(['per_page' => 5], ContentType::findByKey('news')->settings['public']);
    }

    public function test_changing_the_slug_to_a_page_slug_or_a_reserved_path_is_rejected_but_an_unchanged_slug_passes(): void
    {
        ContentType::create(['key' => 'home', 'name' => 'Home', 'kind' => 'page']);
        ContentNode::factory()->create(['content_type' => 'home', 'slug' => 'about']);
        ContentType::create(['key' => 'news', 'name' => 'News', 'slug' => 'latest']);
        $admin = $this->admin();

        $admin->put('/admin/content-types/news', ['name' => 'News', 'slug' => 'about'])
            ->assertSessionHasErrors(['slug' => 'This slug is already used by a page.']);
        $admin->put('/admin/content-types/news', ['name' => 'News', 'slug' => 'admin'])
            ->assertSessionHasErrors(['slug' => 'This slug is reserved.']);
        $this->assertSame('latest', ContentType::findByKey('news')->slug);

        $admin->put('/admin/content-types/news', ['name' => 'Renamed', 'slug' => 'latest'])->assertSessionHasNoErrors();
        $this->assertSame('Renamed', ContentType::findByKey('news')->name);
    }

    public function test_the_slug_of_a_parent_with_slugged_children_cannot_change_or_be_cleared(): void
    {
        ContentType::create(['key' => 'common', 'name' => 'Common', 'slug' => 'common']);
        ContentType::create(['key' => 'inst', 'name' => 'Inst', 'slug' => 'common/institutions', 'parent_type' => 'common']);
        $admin = $this->admin();

        foreach (['info', ''] as $slug) {
            $admin->from('/admin/content-types/common/edit')->put('/admin/content-types/common', ['name' => 'Common', 'slug' => $slug])
                ->assertRedirect('/admin/content-types/common/edit')
                ->assertSessionHasErrors(['slug' => __('Change the slugs of the child types first.')]);
        }

        $this->assertSame('common', ContentType::findByKey('common')->slug);

        // The same slug with a new name is not a change.
        $admin->put('/admin/content-types/common', ['name' => 'Common 2', 'slug' => 'common'])->assertSessionHasNoErrors();
        $this->assertSame('Common 2', ContentType::findByKey('common')->name);
    }

    public function test_the_slug_of_a_parent_without_slugged_children_can_change(): void
    {
        ContentType::create(['key' => 'common', 'name' => 'Common', 'slug' => 'common']);
        ContentType::create(['key' => 'inst', 'name' => 'Inst', 'parent_type' => 'common']);

        $this->admin()->put('/admin/content-types/common', ['name' => 'Common', 'slug' => 'info'])->assertSessionHasNoErrors();

        $this->assertSame('info', ContentType::findByKey('common')->slug);
    }

    public function test_changing_the_parent_rechecks_the_slug(): void
    {
        ContentType::create(['key' => 'common', 'name' => 'Common', 'slug' => 'common']);
        ContentType::create(['key' => 'collectives', 'name' => 'Collectives', 'slug' => 'collectives']);
        ContentType::create(['key' => 'inst', 'name' => 'Inst', 'slug' => 'common/institutions', 'parent_type' => 'common']);
        $admin = $this->admin();

        $admin->put('/admin/content-types/inst', ['name' => 'Inst', 'slug' => 'common/institutions', 'parent_type' => 'collectives'])
            ->assertSessionHasErrors('slug');
        $this->assertSame('common', ContentType::findByKey('inst')->parent_type);

        // Saving with an unchanged slug and parent passes even when the slug later stopped matching.
        $admin->put('/admin/content-types/inst', ['name' => 'Renamed', 'slug' => 'common/institutions', 'parent_type' => 'common'])
            ->assertSessionHasNoErrors();
    }

    public function test_a_nested_slug_and_the_prefix_are_checked_on_edit(): void
    {
        ContentType::create(['key' => 'common', 'name' => 'Common', 'slug' => 'common']);
        ContentType::create(['key' => 'inst', 'name' => 'Inst', 'parent_type' => 'common']);
        $admin = $this->admin();

        $admin->put('/admin/content-types/inst', ['name' => 'Inst', 'slug' => 'institutions', 'parent_type' => 'common'])
            ->assertSessionHasErrors('slug');
        $admin->put('/admin/content-types/inst', ['name' => 'Inst', 'slug' => 'admin/reports'])
            ->assertSessionHasErrors(['slug' => 'This slug is reserved.']);

        $admin->put('/admin/content-types/inst', ['name' => 'Inst', 'slug' => 'common/institutions', 'parent_type' => 'common'])
            ->assertSessionHasNoErrors();
        $this->assertSame('common/institutions', ContentType::findByKey('inst')->slug);
    }
}
