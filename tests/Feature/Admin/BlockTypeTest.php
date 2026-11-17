<?php

namespace Webtack\Ecms\Tests\Feature\Admin;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Webtack\Ecms\Models\ContentField;
use Webtack\Ecms\Models\ContentNode;
use Webtack\Ecms\Models\ContentType;
use Webtack\Ecms\Tests\Fixtures\User;
use Webtack\Ecms\Tests\TestCase;

class BlockTypeTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): static
    {
        return $this->actingAs(User::factory()->create());
    }

    private function block(string $key = 'hero', ?string $parent = null): ContentType
    {
        return ContentType::create(['key' => $key, 'name' => ucfirst($key), 'kind' => 'block', 'parent_type' => $parent]);
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get('/admin/block-types')->assertRedirect('/login');
        $this->get('/admin/block-types/create')->assertRedirect('/login');
        $this->post('/admin/block-types', ['name' => 'Hero', 'key' => 'hero'])->assertRedirect('/login');

        $this->assertSame(0, ContentType::count());
    }

    public function test_the_list_shows_only_block_types_without_a_slug_column(): void
    {
        $this->block('hero');
        ContentType::create(['key' => 'news', 'name' => 'News']);
        ContentType::create(['key' => 'landing', 'name' => 'Landing', 'kind' => 'page']);

        $this->admin()->get('/admin/block-types')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/ContentTypes/Index')
                ->where('section', ['kind' => 'block', 'basePath' => '/admin/block-types'])
                ->has('table.rows', 1)
                ->where('table.rows.0.key', 'hero'));
    }

    public function test_a_block_type_is_created_with_kind_block_and_without_slug(): void
    {
        $this->admin()->post('/admin/block-types', [
            'name' => 'Hero', 'key' => 'hero', 'slug' => 'hero', 'kind' => 'content',
        ])->assertRedirect('/admin/block-types');

        $type = ContentType::where('key', 'hero')->firstOrFail();
        $this->assertSame('block', $type->kind);
        $this->assertNull($type->slug);
        $this->assertTrue($type->fields()->where('key', 'title')->exists());
    }

    public function test_the_parent_must_be_a_block_type(): void
    {
        ContentType::create(['key' => 'news', 'name' => 'News']);
        ContentType::create(['key' => 'landing', 'name' => 'Landing', 'kind' => 'page']);

        $admin = $this->admin();
        $admin->post('/admin/block-types', ['name' => 'Hero', 'key' => 'hero', 'parent_type' => 'news'])
            ->assertSessionHasErrors('parent_type');
        $admin->post('/admin/block-types', ['name' => 'Hero', 'key' => 'hero', 'parent_type' => 'landing'])
            ->assertSessionHasErrors('parent_type');

        $this->assertSame(0, ContentType::where('kind', 'block')->count());
    }

    public function test_a_duplicate_key_is_rejected(): void
    {
        ContentType::create(['key' => 'news', 'name' => 'News']);

        $this->admin()->post('/admin/block-types', ['name' => 'Hero', 'key' => 'news'])
            ->assertSessionHasErrors('key');
    }

    public function test_a_block_type_is_updated_and_keeps_key_and_kind(): void
    {
        $this->block('hero');

        $this->admin()->from('/admin/block-types/hero')->put('/admin/block-types/hero', [
            'name' => 'Renamed', 'key' => 'other', 'kind' => 'content', 'slug' => 'renamed',
        ])->assertRedirect('/admin/block-types/hero');

        $type = ContentType::where('key', 'hero')->firstOrFail();
        $this->assertSame('Renamed', $type->name);
        $this->assertSame('block', $type->kind);
        $this->assertNull($type->slug);
    }

    public function test_a_descendant_cannot_be_the_parent(): void
    {
        $this->block('hero');
        $this->block('banner', 'hero');

        $this->admin()->put('/admin/block-types/hero', ['name' => 'Hero', 'parent_type' => 'banner'])
            ->assertSessionHasErrors('parent_type');
    }

    public function test_sections_answer_404_for_the_other_kind(): void
    {
        $this->block('hero');
        ContentType::create(['key' => 'news', 'name' => 'News']);
        ContentType::create(['key' => 'landing', 'name' => 'Landing', 'kind' => 'page']);

        $admin = $this->admin();
        $admin->get('/admin/block-types/news/edit')->assertNotFound();
        $admin->get('/admin/block-types/landing/edit')->assertNotFound();
        $admin->get('/admin/block-types/news/fields')->assertNotFound();
        $admin->get('/admin/content-types/hero/edit')->assertNotFound();
        $admin->get('/admin/content-types/hero/fields')->assertNotFound();
        $admin->get('/admin/page-types/hero/edit')->assertNotFound();
        $admin->get('/admin/page-types/hero/fields')->assertNotFound();
        $admin->delete('/admin/content-types/hero')->assertNotFound();
        $admin->delete('/admin/page-types/hero')->assertNotFound();
    }

    public function test_an_empty_block_type_is_deleted_with_its_fields(): void
    {
        $this->block('hero');

        $this->admin()->delete('/admin/block-types/hero')->assertRedirect('/admin/block-types');

        $this->assertSame(0, ContentType::where('key', 'hero')->count());
        $this->assertSame(0, ContentField::where('content_type', 'hero')->count());
    }

    public function test_a_block_type_with_children_is_kept(): void
    {
        $this->block('hero');
        $this->block('banner', 'hero');

        $this->admin()->delete('/admin/block-types/hero')->assertSessionHas('error');

        $this->assertTrue(ContentType::where('key', 'hero')->exists());
    }

    public function test_a_block_type_with_nodes_is_kept(): void
    {
        $this->block('hero');
        ContentNode::factory()->create(['content_type' => 'hero', 'slug' => 'block-hero']);

        $this->admin()->delete('/admin/block-types/hero')->assertSessionHas('error');

        $this->assertTrue(ContentType::where('key', 'hero')->exists());
    }

    public function test_fields_are_listed_created_deleted_and_reordered(): void
    {
        $this->block('hero');
        $admin = $this->admin();

        $admin->get('/admin/block-types/hero/fields')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/ContentFields/Index')
                ->where('section.basePath', '/admin/block-types')
                ->where('fields.0.key', 'title'));

        $admin->post('/admin/block-types/hero/fields', ['key' => 'body', 'name' => 'Body', 'type' => 'text', 'weight' => 1])
            ->assertRedirect('/admin/block-types/hero/fields');
        $this->assertTrue(ContentField::where('content_type', 'hero')->where('key', 'body')->exists());

        $admin->put('/admin/block-types/hero/fields/order', ['order' => ['body', 'title']])->assertRedirect();
        $this->assertSame(0, ContentField::where('content_type', 'hero')->where('key', 'body')->value('weight'));

        $admin->delete('/admin/block-types/hero/fields/title')->assertSessionHas('error');
        $this->assertTrue(ContentField::where('content_type', 'hero')->where('key', 'title')->exists());

        $admin->delete('/admin/block-types/hero/fields/body')->assertRedirect('/admin/block-types/hero/fields');
        $this->assertFalse(ContentField::where('content_type', 'hero')->where('key', 'body')->exists());
    }

    public function test_a_foreign_field_key_answers_404(): void
    {
        $this->block('hero');
        $this->block('banner');

        $this->admin()->get('/admin/block-types/hero/fields/nope/edit')->assertNotFound();
    }

    public function test_the_list_settings_of_a_block_type_are_saved(): void
    {
        $this->block('hero');

        $this->admin()->from('/admin/block-types/hero')->put('/admin/block-types/hero', [
            'name' => 'Renamed',
            'settings' => ['list' => ['visible' => ['slug', 'is_active', 'created_at'], 'sort' => ['field' => 'created_at', 'direction' => 'desc']]],
        ])->assertRedirect('/admin/block-types/hero')->assertSessionHasNoErrors();

        $this->assertSame(
            ['visible' => ['slug', 'is_active', 'created_at'], 'sort' => ['field' => 'created_at', 'direction' => 'desc']],
            ContentType::where('key', 'hero')->firstOrFail()->settings['list'],
        );

        $this->admin()->put('/admin/block-types/hero', ['name' => 'Renamed', 'settings' => ['list' => ['visible' => ['missing']]]])
            ->assertSessionHasErrors('settings');
    }

    public function test_a_reserved_key_is_rejected_next_to_the_key_field(): void
    {
        $admin = $this->admin();

        foreach (ContentType::RESERVED_KEYS as $key) {
            $admin->post('/admin/block-types', ['name' => 'X', 'key' => $key])
                ->assertSessionHasErrors(['key' => 'This key is reserved.']);
        }

        $admin->post('/admin/block-types', ['name' => 'X', 'key' => 'pages_list'])->assertSessionHasNoErrors();
        $this->assertNull(ContentType::findByKey('pages'));
        $this->assertNotNull(ContentType::findByKey('pages_list'));
    }
}
