<?php

namespace Webtack\Ecms\Tests\Feature\Admin;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Webtack\Ecms\Models\ContentField;
use Webtack\Ecms\Models\ContentType;
use Webtack\Ecms\Tests\Fixtures\User;
use Webtack\Ecms\Tests\TestCase;

class PageTypeTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): static
    {
        return $this->actingAs(User::factory()->create());
    }

    private function page(string $key = 'landing', ?string $parent = null): ContentType
    {
        return ContentType::create(['key' => $key, 'name' => ucfirst($key), 'kind' => 'page', 'parent_type' => $parent]);
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get('/admin/page-types')->assertRedirect('/login');
        $this->get('/admin/page-types/create')->assertRedirect('/login');
        $this->post('/admin/page-types', ['name' => 'Landing', 'key' => 'landing'])->assertRedirect('/login');

        $this->assertSame(0, ContentType::count());
    }

    public function test_the_list_shows_only_page_types_without_a_slug_column(): void
    {
        $this->page('landing');
        ContentType::create(['key' => 'news', 'name' => 'News']);
        ContentType::create(['key' => 'hero', 'name' => 'Hero', 'kind' => 'block']);

        $this->admin()->get('/admin/page-types')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/ContentTypes/Index')
                ->where('section', ['kind' => 'page', 'basePath' => '/admin/page-types'])
                ->has('table.rows', 1)
                ->where('table.rows.0.key', 'landing'));
    }

    public function test_the_content_list_gets_the_content_section(): void
    {
        $this->admin()->get('/admin/content-types')
            ->assertInertia(fn (Assert $page) => $page->where('section', ['kind' => 'content', 'basePath' => '/admin/content-types']));
    }

    public function test_a_page_type_is_created_with_kind_page_and_without_slug(): void
    {
        $this->admin()->post('/admin/page-types', [
            'name' => 'Landing', 'key' => 'landing', 'slug' => 'landing', 'kind' => 'content',
        ])->assertRedirect('/admin/page-types');

        $type = ContentType::where('key', 'landing')->firstOrFail();
        $this->assertSame('page', $type->kind);
        $this->assertNull($type->slug);
        $this->assertTrue($type->fields()->where('key', 'title')->exists());
    }

    public function test_the_parent_must_be_a_page_type(): void
    {
        ContentType::create(['key' => 'news', 'name' => 'News']);

        $this->admin()->post('/admin/page-types', ['name' => 'Landing', 'key' => 'landing', 'parent_type' => 'news'])
            ->assertSessionHasErrors('parent_type');

        $this->assertSame(0, ContentType::where('kind', 'page')->count());
    }

    public function test_the_content_section_rejects_a_page_parent(): void
    {
        $this->page('landing');

        $this->admin()->post('/admin/content-types', ['name' => 'News', 'key' => 'news', 'parent_type' => 'landing'])
            ->assertSessionHasErrors('parent_type');
    }

    public function test_a_page_type_is_updated_and_keeps_key_and_kind(): void
    {
        $this->page('landing');

        $this->admin()->from('/admin/page-types/landing')->put('/admin/page-types/landing', [
            'name' => 'Renamed', 'key' => 'other', 'kind' => 'content', 'slug' => 'renamed',
        ])->assertRedirect('/admin/page-types/landing');

        $type = ContentType::where('key', 'landing')->firstOrFail();
        $this->assertSame('Renamed', $type->name);
        $this->assertSame('page', $type->kind);
        $this->assertNull($type->slug);
    }

    public function test_a_descendant_cannot_be_the_parent(): void
    {
        $this->page('landing');
        $this->page('promo', 'landing');

        $this->admin()->put('/admin/page-types/landing', ['name' => 'Landing', 'parent_type' => 'promo'])
            ->assertSessionHasErrors('parent_type');
    }

    public function test_sections_answer_404_for_the_other_kind(): void
    {
        $this->page('landing');
        ContentType::create(['key' => 'news', 'name' => 'News']);
        ContentType::create(['key' => 'hero', 'name' => 'Hero', 'kind' => 'block']);

        $admin = $this->admin();
        $admin->get('/admin/page-types/news/edit')->assertNotFound();
        $admin->get('/admin/page-types/hero/edit')->assertNotFound();
        $admin->get('/admin/page-types/news/fields')->assertNotFound();
        $admin->get('/admin/content-types/landing/edit')->assertNotFound();
        $admin->get('/admin/content-types/landing/fields')->assertNotFound();
        $admin->delete('/admin/content-types/landing')->assertNotFound();
    }

    public function test_an_empty_page_type_is_deleted_with_its_fields(): void
    {
        $this->page('landing');

        $this->admin()->delete('/admin/page-types/landing')->assertRedirect('/admin/page-types');

        $this->assertSame(0, ContentType::where('key', 'landing')->count());
        $this->assertSame(0, ContentField::where('content_type', 'landing')->count());
    }

    public function test_a_page_type_with_children_is_kept(): void
    {
        $this->page('landing');
        $this->page('promo', 'landing');

        $this->admin()->delete('/admin/page-types/landing')->assertSessionHas('error');

        $this->assertTrue(ContentType::where('key', 'landing')->exists());
    }

    public function test_fields_are_listed_created_deleted_and_reordered(): void
    {
        $this->page('landing');
        $admin = $this->admin();

        $admin->get('/admin/page-types/landing/fields')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/ContentFields/Index')
                ->where('section.basePath', '/admin/page-types')
                ->where('fields.0.key', 'title'));

        $admin->post('/admin/page-types/landing/fields', ['key' => 'body', 'name' => 'Body', 'type' => 'text', 'weight' => 1])
            ->assertRedirect('/admin/page-types/landing/fields');
        $this->assertTrue(ContentField::where('content_type', 'landing')->where('key', 'body')->exists());

        $admin->put('/admin/page-types/landing/fields/order', ['order' => ['body', 'title']])->assertRedirect();
        $this->assertSame(0, ContentField::where('content_type', 'landing')->where('key', 'body')->value('weight'));

        $admin->delete('/admin/page-types/landing/fields/title')->assertSessionHas('error');
        $this->assertTrue(ContentField::where('content_type', 'landing')->where('key', 'title')->exists());

        $admin->delete('/admin/page-types/landing/fields/body')->assertRedirect('/admin/page-types/landing/fields');
        $this->assertFalse(ContentField::where('content_type', 'landing')->where('key', 'body')->exists());
    }

    public function test_a_foreign_field_key_answers_404(): void
    {
        $this->page('landing');
        $this->page('promo');

        $this->admin()->get('/admin/page-types/landing/fields/nope/edit')->assertNotFound();
    }

    public function test_the_list_settings_of_a_page_type_are_saved(): void
    {
        $this->page('landing');

        $this->admin()->from('/admin/page-types/landing')->put('/admin/page-types/landing', [
            'name' => 'Renamed',
            'settings' => ['list' => ['visible' => ['slug', 'is_active', 'created_at'], 'sort' => ['field' => 'created_at', 'direction' => 'desc']]],
        ])->assertRedirect('/admin/page-types/landing')->assertSessionHasNoErrors();

        $this->assertSame(
            ['visible' => ['slug', 'is_active', 'created_at'], 'sort' => ['field' => 'created_at', 'direction' => 'desc']],
            ContentType::where('key', 'landing')->firstOrFail()->settings['list'],
        );

        $this->admin()->put('/admin/page-types/landing', ['name' => 'Renamed', 'settings' => ['list' => ['visible' => ['missing']]]])
            ->assertSessionHasErrors('settings');
    }

    public function test_a_reserved_key_is_rejected_next_to_the_key_field(): void
    {
        $admin = $this->admin();

        foreach (ContentType::RESERVED_KEYS as $key) {
            $admin->post('/admin/page-types', ['name' => 'X', 'key' => $key])
                ->assertSessionHasErrors(['key' => 'This key is reserved.']);
        }

        $admin->post('/admin/page-types', ['name' => 'X', 'key' => 'pages_list'])->assertSessionHasNoErrors();
        $this->assertNull(ContentType::findByKey('pages'));
        $this->assertNotNull(ContentType::findByKey('pages_list'));
    }
}
