<?php

namespace Webtack\Ecms\Tests\Feature\Public;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\View;
use Inertia\Testing\AssertableInertia as Assert;
use Webtack\Ecms\Content\ContentNodeCreator;
use Webtack\Ecms\Models\ContentField;
use Webtack\Ecms\Models\ContentNode;
use Webtack\Ecms\Models\ContentType;
use Webtack\Ecms\Models\Menu;
use Webtack\Ecms\Models\MenuItem;
use Webtack\Ecms\Models\MenuItemTranslation;
use Webtack\Ecms\Models\Vocabulary;
use Webtack\Ecms\Settings\SiteSettings;
use Webtack\Ecms\Taxonomy\TermWriter;
use Webtack\Ecms\Tests\Fixtures\User;
use Webtack\Ecms\Tests\TestCase;

class PublicRoutesTest extends TestCase
{
    use RefreshDatabase;

    private function page(string $slug, string $title = 'Page', array $attributes = []): ContentNode
    {
        $type = ContentType::findByKey('static') ?? ContentType::create(['key' => 'static', 'name' => 'Static', 'kind' => 'page']);

        return app(ContentNodeCreator::class)->create(
            $type,
            ['title' => $title],
            slug: $slug,
            authorId: $this->authorId(),
            publishedAt: $attributes['published_at'] ?? null,
            isActive: $attributes['is_active'] ?? true,
            unpublishedAt: $attributes['unpublished_at'] ?? null,
        );
    }

    private function newsType(string $key = 'news', string $slug = 'news'): ContentType
    {
        return ContentType::findByKey($key) ?? ContentType::create(['key' => $key, 'name' => ucfirst($key), 'slug' => $slug]);
    }

    private function record(ContentType $type, string $slug, string $title, bool $active = true): ContentNode
    {
        return app(ContentNodeCreator::class)->create($type, ['title' => $title], slug: $slug, authorId: $this->authorId(), isActive: $active);
    }

    public function test_the_home_page_renders_home_inside_the_layout(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('class="site-header', false)
            ->assertSee('class="site-footer', false)
            ->assertSee('<h1', false);
    }

    public function test_the_home_page_is_plain_html_without_inertia(): void
    {
        $html = $this->get('/')->assertOk()->getContent();

        $this->assertStringNotContainsString('data-page=', $html);
        $this->assertStringNotContainsString('id="app"', $html);
    }

    public function test_a_visible_page_is_shown(): void
    {
        $this->page('about', 'About us');

        $this->get('/about')->assertOk()->assertSee('About us');
    }

    public function test_the_title_has_the_record_title_and_the_site_name(): void
    {
        app(SiteSettings::class)->save(['name' => 'Acme']);
        $this->page('about', 'Про нас');

        $this->get('/about')->assertOk()->assertSee('<title>Про нас</title>', false);
        $this->get('/')->assertSee('<title>Acme</title>', false);
    }

    public function test_the_layout_shows_the_site_name(): void
    {
        app(SiteSettings::class)->save(['name' => 'Acme Corp']);

        $this->get('/')->assertSee('Acme Corp');
    }

    public function test_a_disabled_scheduled_or_expired_page_is_not_found(): void
    {
        $this->page('off', attributes: ['is_active' => false]);
        $this->page('soon', attributes: ['published_at' => now()->addDay()]);
        $this->page('gone', attributes: ['published_at' => now()->subDays(2), 'unpublished_at' => now()->subDay()]);

        foreach (['/off', '/soon', '/gone'] as $path) {
            $this->get($path)->assertNotFound();
        }
    }

    public function test_a_node_that_is_not_a_page_is_not_served_by_its_slug(): void
    {
        $this->record($this->newsType(), 'hello', 'Hello');
        $block = ContentType::create(['key' => 'blocks_a', 'name' => 'Blocks', 'kind' => 'block']);
        app(ContentNodeCreator::class)->create($block, ['title' => 'Footer'], slug: 'block-footer', authorId: $this->authorId());

        $this->get('/hello')->assertNotFound();
        $this->get('/block-footer')->assertNotFound();
    }

    public function test_a_type_list_shows_visible_records_only(): void
    {
        $type = $this->newsType();
        $this->record($type, 'one', 'First story');
        $this->record($type, 'two', 'Second story');
        $this->record($type, 'hidden', 'Hidden story', active: false);

        $this->get('/news')
            ->assertOk()
            ->assertSee('First story')
            ->assertSee('Second story')
            ->assertDontSee('Hidden story');
    }

    public function test_an_unknown_slug_is_not_found_inside_the_layout(): void
    {
        $this->get('/unknown')
            ->assertNotFound()
            ->assertSee('class="site-header', false)
            ->assertSee('>404</h1>', false);
    }

    public function test_a_record_is_shown(): void
    {
        $this->record($this->newsType(), 'hello', 'Hello world');

        $this->get('/news/hello')->assertOk()->assertSee('Hello world');
    }

    public function test_a_record_of_another_type_or_an_invisible_one_is_not_found(): void
    {
        $this->record($this->newsType(), 'hello', 'Hello');
        $this->record($this->newsType('events', 'events'), 'party', 'Party');
        $this->record($this->newsType(), 'off', 'Off', active: false);

        $this->get('/news/party')->assertNotFound();
        $this->get('/events/hello')->assertNotFound();
        $this->get('/news/off')->assertNotFound();
        $this->get('/news/missing')->assertNotFound();
    }

    public function test_a_type_with_a_nested_slug_has_a_list_and_records(): void
    {
        $type = $this->newsType('institutions', 'common/institutions');
        $this->record($type, 'hello', 'Hello institution');
        $this->record($type, 'hidden', 'Hidden institution', active: false);

        $this->get('/common/institutions')->assertOk()->assertSee('Hello institution')->assertDontSee('Hidden institution');
        $this->get('/common/institutions/hello')->assertOk()->assertSee('Hello institution');
        $this->get('/common/institutions/hidden')->assertNotFound();
    }

    public function test_a_part_of_a_nested_slug_and_a_record_of_another_type_are_not_found(): void
    {
        $this->record($this->newsType('institutions', 'common/institutions'), 'hello', 'Hello');
        $this->record($this->newsType('sources', 'common/institutions-sources'), 'src', 'Source');

        $this->get('/common')->assertNotFound();
        $this->get('/common/institutions-sources/hello')->assertNotFound();
        $this->get('/common/institutions/src')->assertNotFound();
        $this->get('/common/institutions/hello/extra')->assertNotFound();
    }

    public function test_the_list_wins_over_a_record_with_the_same_address(): void
    {
        $common = $this->newsType('common', 'common');
        $inst = $this->newsType('institutions', 'common/institutions');
        $this->record($inst, 'inner', 'Inner story');
        $node = $this->record($common, 'institutions-note', 'Shadowed story');
        // A legacy collision created before the rules: the record now has the address of the list.
        DB::table('content_nodes')->where('id', $node->id)->update(['slug' => 'institutions']);

        $this->get('/common/institutions')->assertOk()->assertSee('Inner story')->assertDontSee('Shadowed story');
    }

    public function test_a_record_of_a_root_type_is_found_next_to_a_nested_type(): void
    {
        $common = $this->newsType('common', 'common');
        $this->newsType('institutions', 'common/institutions');
        $this->record($common, 'plain', 'Plain story');

        $this->get('/common/plain')->assertOk()->assertSee('Plain story');
    }

    public function test_empty_segments_and_bad_characters_get_the_standard_404(): void
    {
        $this->record($this->newsType(), 'hello', 'Hello');

        foreach (['/news//hello', '/News_1/hello', '/news/hello world'] as $uri) {
            $this->get($uri)->assertNotFound()->assertDontSee('Hello');
        }
    }

    public function test_a_record_with_a_legacy_slug_is_shown(): void
    {
        $type = $this->newsType();

        foreach (['volnovaha---x' => 'One', 'etno_wave-x' => 'Two', 'jam-session-' => 'Three'] as $slug => $title) {
            app(ContentNodeCreator::class)->create($type, ['title' => $title], slug: $slug, authorId: $this->authorId(), legacySlug: true);

            $this->get("/news/{$slug}")->assertOk()->assertSee($title);
        }
    }

    public function test_uppercase_and_spaces_in_a_segment_are_still_not_found(): void
    {
        app(ContentNodeCreator::class)->create($this->newsType(), ['title' => 'Hello'], slug: 'hello', authorId: $this->authorId());

        foreach (['/news/Hello', '/news/he llo'] as $uri) {
            $this->get($uri)->assertNotFound();
        }
    }

    public function test_reserved_first_segments_are_not_served_by_the_site(): void
    {
        // No public route matches a reserved first segment: the admin gets the standard 404, and `/storage/...`
        // stays with its own route (the status is the storage route's); neither builds a theme page.
        $this->get('/admin/something')->assertNotFound()->assertDontSee('class="site-header"', false);
        $this->get('/storage/something-unknown')->assertDontSee('class="site-header"', false);
    }

    public function test_a_page_has_no_details_route(): void
    {
        $this->page('about');

        $this->get('/about/anything')->assertNotFound();
    }

    public function test_a_deep_unknown_path_gets_the_theme_404(): void
    {
        $this->get('/no/such/page/at/all')
            ->assertNotFound()
            ->assertSee('class="site-header', false)
            ->assertSee('>404</h1>', false);
    }

    public function test_a_file_name_gets_the_theme_404(): void
    {
        $this->get('/favicon.ico')->assertNotFound()->assertSee('>404</h1>', false);
    }

    public function test_service_paths_are_not_taken_by_the_site(): void
    {
        // An Inertia response, not a page of the site (the HTML shell has no `data-page` here: the admin build is not
        // published into the Testbench public directory).
        $this->get('/login')->assertOk()->assertInertia(fn (Assert $page) => $page->component('Auth/Login'));

        $this->actingAs(User::factory()->create())
            ->get('/admin/dashboard')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Admin/Dashboard'));
    }

    public function test_a_page_row_with_a_reserved_slug_does_not_replace_the_login(): void
    {
        $page = $this->page('placeholder', 'Fake login');
        // A row that bypassed the model validation.
        DB::table('content_nodes')->where('id', $page->id)->update(['slug' => 'login']);

        $this->get('/login')->assertOk()->assertDontSee('Fake login');
    }

    public function test_a_reserved_slug_in_the_database_is_not_served_as_a_page(): void
    {
        $page = $this->page('placeholder', 'Fake locale');
        DB::table('content_nodes')->where('id', $page->id)->update(['slug' => 'locale']);

        // `/locale` is POST-only: GET keeps its 405 and the page must not appear.
        $this->get('/locale')->assertStatus(405);
    }

    public function test_a_wrong_verb_on_an_unknown_url_stays_a_standard_404(): void
    {
        $this->actingAs(User::factory()->create())
            ->put('/admin/page-types/unknown/values')
            ->assertNotFound()
            ->assertDontSee('class="site-header"', false);
        // An address with an empty segment matches no public route, so a wrong verb gets the standard 404, not a 405.
        $this->post('/no//such')->assertNotFound()->assertDontSee('class="site-header"', false);
    }

    public function test_a_json_request_keeps_the_standard_404(): void
    {
        // Matches no public route (empty segment): a JSON client gets the standard 404, not the themed page.
        $this->getJson('/no//such')->assertNotFound()->assertDontSee('site-header', false);
    }

    public function test_the_admin_keeps_its_standard_404(): void
    {
        $this->actingAs(User::factory()->create())
            ->get('/admin/unknown')
            ->assertNotFound()
            ->assertDontSee('class="site-header"', false);
    }

    /** @param array<string, mixed> $public */
    private function typeWith(string $key, string $kind, array $public = [], ?string $slug = null): ContentType
    {
        $type = ContentType::create([
            'key' => $key,
            'name' => ucfirst($key),
            'kind' => $kind,
            'slug' => $kind === 'content' ? ($slug ?? $key) : null,
        ]);

        foreach (['body' => 'text', 'note' => 'string', 'price' => 'integer'] as $fieldKey => $fieldType) {
            ContentField::create(['content_type' => $key, 'key' => $fieldKey, 'name' => ucfirst($fieldKey), 'type' => $fieldType]);
        }

        // The settings name fields, so they are saved after the fields exist.
        if ($public !== []) {
            $type->update(['settings' => ['public' => $public]]);
        }

        return $type;
    }

    /** @param array<string, mixed> $values */
    private function node(ContentType $type, string $slug, array $values): ContentNode
    {
        return app(ContentNodeCreator::class)->create($type, $values, slug: $slug, authorId: $this->authorId());
    }

    public function test_a_page_shows_the_fields_of_page_fields_only(): void
    {
        $type = $this->typeWith('doc', 'page', ['page_fields' => ['body']]);
        $this->node($type, 'doc-one', ['title' => 'Doc', 'body' => 'The body text', 'note' => 'Hidden note']);

        $this->get('/doc-one')->assertOk()->assertSee('The body text')->assertDontSee('Hidden note');
    }

    public function test_a_page_without_settings_shows_every_added_field_that_has_a_value(): void
    {
        $type = $this->typeWith('doc', 'page');
        $this->node($type, 'doc-one', ['title' => 'Doc', 'body' => 'The body text', 'note' => 'The note']);

        $html = $this->get('/doc-one')->assertOk()->assertSee('The body text')->assertSee('The note')->getContent();

        // `price` has no value: its wrapper is not printed.
        $this->assertStringNotContainsString('field--price', $html);
    }

    public function test_the_fields_of_a_record_follow_page_fields(): void
    {
        $type = $this->typeWith('news', 'content', ['page_fields' => ['note', 'body']]);
        $this->node($type, 'story', ['title' => 'Story', 'body' => 'BODY', 'note' => 'NOTE', 'price' => 5]);

        $html = $this->get('/news/story')->assertOk()->getContent();

        $this->assertLessThan(strpos($html, 'BODY'), strpos($html, 'NOTE'), 'The order of page_fields is kept.');
        $this->assertStringNotContainsString('field--price', $html);
    }

    public function test_a_card_shows_the_title_as_a_link_and_the_list_fields(): void
    {
        $type = $this->typeWith('news', 'content', ['list_fields' => ['price']]);
        $this->node($type, 'story', ['title' => 'Story', 'price' => 15, 'note' => 'Not on card']);

        $this->get('/news')
            ->assertOk()
            ->assertSee('href="/news/story">Story</a>', false)
            ->assertSee('field--price', false)
            ->assertDontSee('Not on card');
    }

    public function test_the_list_follows_the_sort_setting(): void
    {
        $type = $this->typeWith('news', 'content', ['sort' => ['field' => 'price', 'direction' => 'asc']]);
        $this->node($type, 'b', ['title' => 'Bravo', 'price' => 2]);
        $this->node($type, 'a', ['title' => 'Alpha', 'price' => 1]);
        $this->node($type, 'c', ['title' => 'Charlie', 'price' => 3]);

        $html = $this->get('/news')->assertOk()->getContent();

        $this->assertLessThan(strpos($html, 'Bravo'), strpos($html, 'Alpha'));
        $this->assertLessThan(strpos($html, 'Charlie'), strpos($html, 'Bravo'));
    }

    public function test_the_list_without_settings_shows_the_newest_first(): void
    {
        $type = $this->typeWith('news', 'content');
        $this->node($type, 'old', ['title' => 'Old story']);
        $new = $this->node($type, 'new', ['title' => 'New story']);
        DB::table('content_nodes')->where('id', $new->id)->update(['published_at' => now()->subMinute()]);
        DB::table('content_nodes')->where('slug', 'old')->update(['published_at' => now()->subDay()]);

        $html = $this->get('/news')->assertOk()->getContent();

        $this->assertLessThan(strpos($html, 'Old story'), strpos($html, 'New story'));
    }

    public function test_per_page_cuts_the_list_and_the_paginator_links_to_the_next_page(): void
    {
        $type = $this->typeWith('news', 'content', ['per_page' => 2, 'sort' => ['field' => 'price', 'direction' => 'asc']]);

        foreach (['A' => 1, 'B' => 2, 'C' => 3] as $letter => $price) {
            $this->node($type, strtolower($letter), ['title' => "Story {$letter}", 'price' => $price]);
        }

        $this->get('/news')->assertOk()->assertSee('Story A')->assertSee('Story B')->assertDontSee('Story C')->assertSee('page=2', false);
        $this->get('/news?page=2')->assertOk()->assertSee('Story C')->assertDontSee('Story A');
    }

    public function test_the_paginator_can_be_switched_off(): void
    {
        $type = $this->typeWith('news', 'content', ['per_page' => 1, 'pagination' => false]);
        $this->node($type, 'a', ['title' => 'Story A']);
        $this->node($type, 'b', ['title' => 'Story B']);

        $this->get('/news')->assertOk()->assertDontSee('page=2', false);
    }

    public function test_the_references_of_all_the_cards_are_read_in_one_batch(): void
    {
        $type = $this->typeWith('news', 'content');
        Vocabulary::factory()->create(['key' => 'topics', 'name' => 'Topics']);
        ContentField::create(['content_type' => 'news', 'key' => 'topic', 'name' => 'Topic', 'type' => 'term', 'settings' => ['vocabulary' => 'topics']]);
        $type->update(['settings' => ['public' => ['list_fields' => ['topic']]]]);

        $count = function (int $records) use ($type): int {
            ContentNode::query()->where('content_type', 'news')->get()->each->delete();

            // A different term on every card, so only a batch (not the cache of one term) keeps the count flat.
            for ($i = 0; $i < $records; $i++) {
                $term = app(TermWriter::class)->create(Vocabulary::findByKey('topics'), ['translations' => ['default' => ['name' => "Science {$i}"]]]);
                $this->node($type, "story-{$i}", ['title' => "Story {$i}", 'topic' => $term->uuid]);
            }

            // A real request starts with empty scoped services; the test client reuses one application.
            $this->app->forgetScopedInstances();
            DB::enableQueryLog();
            DB::flushQueryLog();
            $this->get('/news')->assertOk()->assertSee('Science 0');
            $queries = count(DB::getQueryLog());
            DB::disableQueryLog();

            return $queries;
        };

        $count(1); // warms what is cached across requests (settings, languages)

        $this->assertSame($count(2), $count(8), 'The query count must not depend on the number of cards.');
    }

    public function test_the_meta_values_of_a_record_go_to_the_head(): void
    {
        app(SiteSettings::class)->save(['name' => 'Acme']);
        $type = $this->typeWith('news', 'content');
        $this->node($type, 'story', ['title' => 'Story', 'meta_title' => 'SEO title', 'meta_description' => 'SEO description']);

        $this->get('/news/story')
            ->assertOk()
            ->assertSee('<title>SEO title</title>', false)
            ->assertSee('<meta name="description" content="SEO description">', false);
    }

    public function test_without_meta_values_the_head_keeps_the_record_title(): void
    {
        app(SiteSettings::class)->save(['name' => 'Acme']);
        $type = $this->typeWith('news', 'content');
        $this->node($type, 'story', ['title' => 'Story']);

        $this->get('/news/story')
            ->assertOk()
            ->assertSee('<title>Story</title>', false)
            ->assertDontSee('name="description"', false)
            ->assertDontSee('og:image', false);
    }

    private function mainMenuItem(string $title, string $url): void
    {
        $menu = Menu::findByKey('main') ?? Menu::factory()->create(['key' => 'main', 'name' => 'Main']);
        $item = MenuItem::factory()->in($menu)->create(['url' => $url]);
        MenuItemTranslation::factory()->create(['item_uuid' => $item->uuid, 'lang' => 'default', 'title' => $title]);
    }

    public function test_every_public_page_shows_the_main_menu_in_the_header(): void
    {
        $this->mainMenuItem('Our catalog', '/catalog');
        $this->page('about');

        foreach (['/', '/about', '/no/such/page'] as $path) {
            $html = $this->get($path)->getContent();

            $this->assertMatchesRegularExpression('#<header class="site-header[^"]*">.*<a [^>]*href="'.preg_quote(url('/catalog'), '#').'"[^>]*>Our catalog</a>.*</header>#s', $html, $path);
        }
    }

    public function test_the_pages_work_without_a_main_menu(): void
    {
        $this->page('about');

        $this->get('/')->assertOk()->assertDontSee('class="menu', false);
        $this->get('/about')->assertOk()->assertDontSee('class="menu', false);
        $this->get('/no/such/page')->assertNotFound()->assertSee('class="site-header', false);
    }

    /** An `events` type (slug `events`, `per_page` as given) split into upcoming and past records by its `date` field. */
    private function eventsType(int $perPage = 0, bool $archive = true): ContentType
    {
        $type = ContentType::create(['key' => 'events', 'name' => 'Events', 'slug' => 'events']);
        ContentField::create(['content_type' => 'events', 'key' => 'date', 'name' => 'Date', 'type' => 'datetime']);
        $type->update(['settings' => ['public' => [
            'sort' => ['field' => 'date', 'direction' => 'asc'],
            'per_page' => $perPage,
            ...($archive ? ['archive' => ['field' => 'date', 'sort' => ['field' => 'date', 'direction' => 'desc']]] : []),
        ]]]);

        return $type;
    }

    private function event(ContentType $type, string $title, string $date, bool $active = true): ContentNode
    {
        return app(ContentNodeCreator::class)->create($type, ['title' => $title, 'date' => $date], slug: str($title)->slug()->value(), authorId: $this->authorId(), isActive: $active);
    }

    public function test_the_main_list_hides_past_records_and_the_archive_shows_them(): void
    {
        $this->travelTo(now()->setTime(15, 0));
        $type = $this->eventsType();
        $this->event($type, 'Today show', now()->startOfDay()->toDateTimeString());
        $this->event($type, 'Last week show', now()->subWeek()->toDateTimeString());

        $this->get('/events')->assertOk()->assertSee('Today show')->assertDontSee('Last week show');
        $this->get('/events?period=archive')->assertOk()->assertSee('Last week show')->assertDontSee('Today show');
    }

    public function test_both_lists_follow_their_own_sort(): void
    {
        $this->travelTo(now()->setTime(15, 0));
        $type = $this->eventsType();
        foreach ([3 => 'Far', 1 => 'Near', 2 => 'Middle'] as $days => $title) {
            $this->event($type, "{$title} future", now()->addDays($days)->toDateTimeString());
            $this->event($type, "{$title} past", now()->subDays($days)->toDateTimeString());
        }

        $main = $this->get('/events')->assertOk()->getContent();
        $this->assertLessThan(strpos($main, 'Middle future'), strpos($main, 'Near future'));
        $this->assertLessThan(strpos($main, 'Far future'), strpos($main, 'Middle future'));

        $archive = $this->get('/events?period=archive')->assertOk()->getContent();
        $this->assertLessThan(strpos($archive, 'Middle past'), strpos($archive, 'Near past'));
        $this->assertLessThan(strpos($archive, 'Far past'), strpos($archive, 'Middle past'));
    }

    public function test_invisible_records_are_hidden_in_both_lists(): void
    {
        $this->travelTo(now()->setTime(15, 0));
        $type = $this->eventsType();
        $this->event($type, 'Off future', now()->addDay()->toDateTimeString(), active: false);
        $this->event($type, 'Off past', now()->subDay()->toDateTimeString(), active: false);

        $this->get('/events')->assertOk()->assertDontSee('Off future')->assertDontSee('Off past');
        $this->get('/events?period=archive')->assertOk()->assertDontSee('Off future')->assertDontSee('Off past');
    }

    public function test_the_paginator_of_the_archive_keeps_the_period(): void
    {
        $this->travelTo(now()->setTime(15, 0));
        $type = $this->eventsType(perPage: 1);
        $this->event($type, 'Past one', now()->subDays(2)->toDateTimeString());
        $this->event($type, 'Past two', now()->subDays(3)->toDateTimeString());

        $this->get('/events?period=archive')->assertOk()->assertSee('period=archive&amp;page=2', false);
        $this->get('/events?period=archive&page=2')->assertOk()->assertSee('Past two');
    }

    public function test_an_unknown_period_is_the_main_list(): void
    {
        $this->travelTo(now()->setTime(15, 0));
        $type = $this->eventsType();
        $this->event($type, 'Upcoming show', now()->addDay()->toDateTimeString());
        $this->event($type, 'Old show', now()->subDay()->toDateTimeString());

        $this->get('/events?period=foo')->assertOk()->assertSee('Upcoming show')->assertDontSee('Old show');
    }

    public function test_a_type_without_an_archive_ignores_the_period(): void
    {
        $this->travelTo(now()->setTime(15, 0));
        $type = $this->eventsType(archive: false);
        $this->event($type, 'Upcoming show', now()->addDay()->toDateTimeString());
        $this->event($type, 'Old show', now()->subDay()->toDateTimeString());

        $plain = $this->get('/events')->assertOk()->assertSee('Upcoming show')->assertSee('Old show')->getContent();
        $this->assertSame($plain, $this->get('/events?period=archive')->assertOk()->getContent());
    }

    public function test_the_template_knows_the_period_and_whether_there_is_an_archive(): void
    {
        $this->travelTo(now()->setTime(15, 0));
        $seen = [];
        View::composer('theme::content.list', function ($view) use (&$seen) {
            $seen[] = $view->getData()['period'].':'.var_export($view->getData()['hasArchive'], true);
        });

        $type = $this->eventsType();
        $this->get('/events')->assertOk();
        $this->get('/events?period=archive')->assertOk();
        $type->update(['settings' => null]);
        $this->get('/events?period=archive')->assertOk();

        $this->assertSame(['current:true', 'archive:true', 'current:false'], $seen);
    }
}
