<?php

namespace Webtack\Ecms\Tests\Feature\Admin;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;
use Webtack\Ecms\Models\ContentType;
use Webtack\Ecms\Models\Role;
use Webtack\Ecms\Tests\Fixtures\User;
use Webtack\Ecms\Tests\TestCase;

/**
 * The "Sitemap" tab of a `content` / `page` type: its props, the single save of `settings.sitemap`, the rules and the access.
 */
class ContentTypeSitemapTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): static
    {
        return $this->actingAs(User::factory()->create());
    }

    /** @param  array<string, mixed>  $sitemap */
    private function save(array $sitemap, string $key = 'news', string $base = 'content-types'): TestResponse
    {
        return $this->admin()->put("/admin/{$base}/{$key}/sitemap", ['settings' => ['sitemap' => $sitemap]]);
    }

    public function test_the_routes_have_their_names_and_guests_are_redirected(): void
    {
        ContentType::create(['key' => 'news', 'name' => 'News', 'slug' => 'news']);

        $this->assertSame('/admin/content-types/news/sitemap', route('admin.content-types.sitemap', 'news', false));
        $this->assertSame('/admin/page-types/news/sitemap', route('admin.page-types.sitemap.update', 'news', false));
        $this->get('/admin/content-types/news/sitemap')->assertRedirect('/login');
        $this->put('/admin/content-types/news/sitemap', [])->assertRedirect('/login');
    }

    public function test_the_page_carries_the_defaults_of_an_unconfigured_type(): void
    {
        ContentType::create(['key' => 'news', 'name' => 'News', 'slug' => 'news']);

        $this->admin()->get('/admin/content-types/news/sitemap')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/ContentTypes/Sitemap')
                ->where('type.key', 'news')
                ->where('section.kind', 'content')
                ->where('hasSlug', true)
                ->where('sitemap', [
                    'xml' => ['type' => false, 'nodes' => false, 'changefreq' => 'weekly', 'priority' => '0.5'],
                    'web' => ['type' => false, 'nodes' => false],
                ])
                ->where('changefreqOptions', ['always', 'hourly', 'daily', 'weekly', 'monthly', 'yearly', 'never'])
                ->where('priorityOptions', fn ($options) => count($options) === 11 && $options[0] === '0.0' && $options[10] === '1.0'));
    }

    public function test_saving_and_reloading_shows_the_same_values(): void
    {
        ContentType::create(['key' => 'news', 'name' => 'News', 'slug' => 'news']);

        $this->save([
            'xml' => ['type' => true, 'nodes' => true, 'changefreq' => 'monthly', 'priority' => '0.8'],
            'web' => ['type' => false, 'nodes' => true],
        ])->assertRedirect()->assertSessionHas('success');

        $this->assertSame([
            'xml' => ['type' => true, 'nodes' => true, 'changefreq' => 'monthly', 'priority' => '0.8'],
            'web' => ['nodes' => true],
        ], ContentType::findByKey('news')->settings['sitemap']);

        $this->admin()->get('/admin/content-types/news/sitemap')->assertInertia(fn (Assert $page) => $page
            ->where('sitemap.xml', ['type' => true, 'nodes' => true, 'changefreq' => 'monthly', 'priority' => '0.8'])
            ->where('sitemap.web', ['type' => false, 'nodes' => true]));
    }

    public function test_the_frequency_and_the_priority_default_when_an_xml_checkbox_is_on(): void
    {
        ContentType::create(['key' => 'news', 'name' => 'News', 'slug' => 'news']);

        $this->save(['xml' => ['nodes' => true]])->assertSessionHasNoErrors();

        $this->assertSame(['xml' => ['nodes' => true, 'changefreq' => 'weekly', 'priority' => '0.5']], ContentType::findByKey('news')->settings['sitemap']);
    }

    public function test_the_frequency_and_the_priority_are_dropped_with_the_xml_checkboxes(): void
    {
        ContentType::create(['key' => 'news', 'name' => 'News', 'slug' => 'news', 'settings' => ['sitemap' => [
            'xml' => ['nodes' => true, 'changefreq' => 'daily', 'priority' => '0.9'], 'web' => ['type' => true],
        ]]]);

        $this->save(['xml' => ['type' => false, 'nodes' => false, 'changefreq' => 'daily', 'priority' => '0.9'], 'web' => ['type' => true]]);

        $this->assertSame(['web' => ['type' => true]], ContentType::findByKey('news')->settings['sitemap']);
    }

    public function test_everything_off_removes_the_key_and_keeps_the_other_settings(): void
    {
        ContentType::create(['key' => 'news', 'name' => 'News', 'slug' => 'news', 'settings' => [
            'list' => ['search' => ['title']],
            'sitemap' => ['web' => ['nodes' => true]],
        ]]);

        $this->save(['xml' => ['type' => false, 'nodes' => false], 'web' => ['type' => false, 'nodes' => false]])->assertSessionHasNoErrors();

        $this->assertSame(['list' => ['search' => ['title']]], ContentType::findByKey('news')->settings);
    }

    public function test_other_settings_survive_a_save(): void
    {
        ContentType::create(['key' => 'news', 'name' => 'News', 'slug' => 'news', 'settings' => [
            'list' => ['search' => ['title']],
            'public' => ['per_page' => 5],
        ]]);

        $this->save(['web' => ['nodes' => true]]);

        $settings = ContentType::findByKey('news')->settings;
        $this->assertSame(['search' => ['title']], $settings['list']);
        $this->assertSame(['per_page' => 5], $settings['public']);
        $this->assertSame(['web' => ['nodes' => true]], $settings['sitemap']);
    }

    public function test_bad_values_fail_at_the_field_and_save_nothing(): void
    {
        ContentType::create(['key' => 'news', 'name' => 'News', 'slug' => 'news']);

        $this->save(['xml' => ['nodes' => true, 'changefreq' => 'sometimes']])->assertSessionHasErrors('settings.sitemap.xml.changefreq');
        $this->save(['xml' => ['nodes' => true, 'priority' => '1.5']])->assertSessionHasErrors('settings.sitemap.xml.priority');
        $this->save(['xml' => ['nodes' => 'maybe']])->assertSessionHasErrors('settings.sitemap.xml.nodes');

        $this->assertNull(ContentType::findByKey('news')->settings);
    }

    public function test_a_page_type_has_no_content_type_checkbox(): void
    {
        ContentType::create(['key' => 'about', 'name' => 'About', 'kind' => 'page']);

        $this->admin()->get('/admin/page-types/about/sitemap')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('section.kind', 'page')->where('sitemap.web.nodes', false));

        $this->save(['xml' => ['type' => true]], 'about', 'page-types')->assertSessionHasErrors('settings.sitemap.xml.type');
        $this->save(['web' => ['type' => true]], 'about', 'page-types')->assertSessionHasErrors('settings.sitemap.web.type');
        $this->assertNull(ContentType::findByKey('about')->settings);

        $this->save(['xml' => ['nodes' => true], 'web' => ['nodes' => true]], 'about', 'page-types')->assertSessionHasNoErrors();
        $this->assertSame(['xml', 'web'], array_keys(ContentType::findByKey('about')->settings['sitemap']));
    }

    public function test_a_type_without_a_slug_still_opens_and_saves(): void
    {
        ContentType::create(['key' => 'draft', 'name' => 'Draft']);

        $this->admin()->get('/admin/content-types/draft/sitemap')->assertInertia(fn (Assert $page) => $page->where('hasSlug', false));
        $this->save(['web' => ['nodes' => true]], 'draft')->assertSessionHasNoErrors();
    }

    public function test_a_user_without_the_types_right_gets_403(): void
    {
        ContentType::create(['key' => 'news', 'name' => 'News', 'slug' => 'news']);
        $user = User::factory()->withRole(Role::factory()->withPermissions(['menus' => 'allow'])->create())->create();

        $this->actingAs($user)->get('/admin/content-types/news/sitemap')->assertForbidden();
        $this->actingAs($user)->put('/admin/content-types/news/sitemap', ['settings' => ['sitemap' => ['web' => ['nodes' => true]]]])->assertForbidden();
        $this->assertNull(ContentType::findByKey('news')->settings);
    }

    public function test_a_block_type_has_no_tab(): void
    {
        ContentType::create(['key' => 'hero', 'name' => 'Hero', 'kind' => 'block']);

        $this->admin()->get('/admin/block-types/hero/sitemap')->assertNotFound();
        $this->admin()->put('/admin/block-types/hero/sitemap', ['settings' => ['sitemap' => ['web' => ['nodes' => true]]]])->assertNotFound();
        $this->assertNull(ContentType::findByKey('hero')->settings);
    }

    public function test_a_type_of_another_kind_answers_404(): void
    {
        ContentType::create(['key' => 'news', 'name' => 'News', 'slug' => 'news']);

        $this->admin()->get('/admin/page-types/news/sitemap')->assertNotFound();
    }
}
