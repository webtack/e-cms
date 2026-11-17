<?php

namespace Webtack\Ecms\Tests\Feature\Public;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Webtack\Ecms\Content\ContentNodeCreator;
use Webtack\Ecms\Content\ContentValueReader;
use Webtack\Ecms\Content\PresentContext;
use Webtack\Ecms\Content\SitemapTree;
use Webtack\Ecms\Models\ContentNode;
use Webtack\Ecms\Models\ContentType;
use Webtack\Ecms\Tests\Fixtures\User;
use Webtack\Ecms\Tests\TestCase;

/** `GET /sitemap` and the tree behind it: settings, nesting, ordering, titles, visibility and the site 404 below it. */
class SitemapWebTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app()->setLocale('en');
    }

    private function type(string $key, string $kind, ?string $slug, ?array $web, ?string $parent = null, ?string $sectionTitle = null): ContentType
    {
        $type = ContentType::create([
            'key' => $key, 'name' => ucfirst($key), 'kind' => $kind, 'slug' => $slug, 'parent_type' => $parent,
            'settings' => $web === null ? null : ['sitemap' => ['web' => $web]],
        ]);

        if ($sectionTitle !== null) {
            DB::table('content_values_string')->insert([
                'content_type' => $key, 'content_node_id' => null, 'entity' => 'type', 'field_key' => 'section_title',
                'lang' => 'default', 'value' => $sectionTitle, 'weight' => 0,
            ]);
        }

        return $type;
    }

    private function node(ContentType $type, string $slug, string $title, array $attributes = []): ContentNode
    {
        return app(ContentNodeCreator::class)->create(
            $type,
            ['title' => $title],
            slug: $slug,
            authorId: User::factory()->create()->id,
            publishedAt: $attributes['published_at'] ?? now()->subDay(),
            isActive: $attributes['is_active'] ?? true,
        );
    }

    /** @return list<array<string, mixed>> */
    private function items(): array
    {
        return app(SitemapTree::class)->items();
    }

    /** @param  list<array<string, mixed>>  $items */
    private function titles(array $items): array
    {
        return array_map(fn (array $item) => $item['title'], $items);
    }

    public function test_the_page_is_rendered_in_the_shell_with_the_title(): void
    {
        $this->get('/sitemap')
            ->assertOk()
            ->assertSee('<title>Sitemap</title>', false)
            ->assertSee('Home')
            ->assertHeaderMissing('X-Robots-Tag');
    }

    public function test_nothing_configured_shows_only_home(): void
    {
        $this->type('news', 'content', 'news', null);

        $this->assertSame([['title' => 'Home', 'url' => '/', 'children' => []]], $this->items());
    }

    public function test_nested_child_types_with_records(): void
    {
        $common = $this->type('common', 'content', 'common', ['type' => true], sectionTitle: 'Common');
        $inst = $this->type('institutions', 'content', 'common/institutions', ['type' => true, 'nodes' => true], 'common', 'Institutions');
        $this->node($inst, 'second', 'Second');
        $this->node($inst, 'first', 'First');

        $this->assertSame([
            ['title' => 'Home', 'url' => '/', 'children' => []],
            ['title' => 'Common', 'url' => '/common', 'children' => [
                ['title' => 'Institutions', 'url' => '/common/institutions', 'children' => [
                    ['title' => 'First', 'url' => '/common/institutions/first', 'children' => []],
                    ['title' => 'Second', 'url' => '/common/institutions/second', 'children' => []],
                ]],
            ]],
        ], $this->items());
        $this->assertNotNull($common);
    }

    public function test_only_records_are_listed_at_the_top_level_without_an_own_item(): void
    {
        $news = $this->type('news', 'content', 'news', ['nodes' => true]);
        $this->node($news, 'b', 'Bravo');
        $this->node($news, 'a', 'Alpha');

        $this->assertSame(['Home', 'Alpha', 'Bravo'], $this->titles($this->items()));
        $this->assertSame('/news/a', $this->items()[1]['url']);
    }

    public function test_a_child_is_shown_at_the_parents_level_when_the_parent_is_off(): void
    {
        $this->type('common', 'content', 'common', null);
        $this->type('institutions', 'content', 'common/institutions', ['type' => true], 'common', 'Institutions');

        $this->assertSame(['Home', 'Institutions'], $this->titles($this->items()));
    }

    public function test_a_type_without_a_slug_gives_nothing_but_its_children_are_visited(): void
    {
        $draft = $this->type('draft', 'content', 'draft', ['type' => true, 'nodes' => true]);
        $this->node($draft, 'a', 'Hidden by the missing slug');
        $this->type('sub', 'content', 'draft/sub', ['type' => true], 'draft', 'Sub');
        DB::table('content_types')->where('key', 'draft')->update(['slug' => null]);

        $this->assertSame(['Home', 'Sub'], $this->titles($this->items()));
    }

    public function test_pages_are_one_flat_list_ordered_by_title_after_home(): void
    {
        $about = $this->type('about', 'page', null, ['nodes' => true]);
        $this->node($about, 'contacts', 'Contacts');
        $this->node($about, 'about-us', 'About us');
        $this->type('news', 'content', 'news', ['type' => true], sectionTitle: 'News');

        $items = $this->items();

        $this->assertSame(['Home', 'About us', 'Contacts', 'News'], $this->titles($items));
        $this->assertSame('/about-us', $items[1]['url']);
    }

    public function test_type_titles_use_the_section_title_then_the_name_and_a_missing_node_title_uses_the_slug(): void
    {
        $this->type('plain', 'content', 'plain', ['type' => true, 'nodes' => true]);
        $withTitle = $this->type('titled', 'content', 'titled', ['type' => true], sectionTitle: 'Новини');
        $node = ContentNode::factory()->create(['content_type' => 'plain', 'slug' => 'no-title']);

        $items = $this->items();

        $this->assertSame(['Home', 'Plain', 'Новини'], $this->titles($items));
        $this->assertSame([['title' => 'no-title', 'url' => '/plain/no-title', 'children' => []]], $items[1]['children']);
        $this->assertNotNull($withTitle);
        $this->assertNotNull($node);
    }

    public function test_the_reading_language_title_wins_over_the_base_one(): void
    {
        $news = $this->type('news', 'content', 'news', ['type' => true, 'nodes' => true], sectionTitle: 'News');
        $node = $this->node($news, 'a', 'Base title');
        DB::table('content_values_string')->insert([
            ['content_type' => 'news', 'content_node_id' => $node->id, 'content_node_uuid' => $node->uuid, 'entity' => 'node', 'field_key' => 'title', 'lang' => 'uk', 'value' => 'Українська назва', 'weight' => 0],
            ['content_type' => 'news', 'content_node_id' => null, 'content_node_uuid' => null, 'entity' => 'type', 'field_key' => 'section_title', 'lang' => 'uk', 'value' => 'Новини', 'weight' => 0],
        ]);
        $this->app->instance(PresentContext::class, new PresentContext(app(ContentValueReader::class), 'uk'));

        $items = app(SitemapTree::class)->items();

        $this->assertSame('Новини', $items[1]['title']);
        $this->assertSame('Українська назва', $items[1]['children'][0]['title']);
    }

    public function test_hidden_nodes_are_not_in_the_tree(): void
    {
        $news = $this->type('news', 'content', 'news', ['type' => true, 'nodes' => true], sectionTitle: 'News');
        $this->node($news, 'shown', 'Shown');
        $this->node($news, 'off', 'Off', ['is_active' => false]);
        $this->node($news, 'later', 'Later', ['published_at' => now()->addDay()]);

        $this->assertSame(['Shown'], $this->titles($this->items()[1]['children']));
    }

    public function test_a_parent_type_cycle_does_not_hang_and_keeps_the_content(): void
    {
        $this->type('a', 'content', 'a', ['type' => true], sectionTitle: 'A');
        $this->type('b', 'content', 'b', ['type' => true], sectionTitle: 'B');
        DB::table('content_types')->where('key', 'a')->update(['parent_type' => 'b']);
        DB::table('content_types')->where('key', 'b')->update(['parent_type' => 'a']);

        $titles = $this->titles($this->items());

        $this->assertContains('A', array_merge($titles, array_merge(...array_map(fn ($i) => $this->titles($i['children']), $this->items()))));
    }

    public function test_the_page_prints_the_tree_with_escaped_titles(): void
    {
        $news = $this->type('news', 'content', 'news', ['type' => true, 'nodes' => true], sectionTitle: 'News');
        $this->node($news, 'a', 'Fish & <b>chips</b>');

        $this->get('/sitemap')
            ->assertOk()
            ->assertSee('href="/news/a"', false)
            ->assertSee('Fish &amp; &lt;b&gt;chips&lt;/b&gt;', false)
            ->assertDontSee('<b>chips</b>', false);
    }

    public function test_a_deeper_address_gets_the_site_404(): void
    {
        $this->get('/sitemap/anything')
            ->assertNotFound()
            ->assertSee('>404</h1>', false);
    }

    public function test_the_query_count_does_not_depend_on_the_number_of_types(): void
    {
        $count = function (): int {
            DB::flushQueryLog();
            DB::enableQueryLog();
            app(SitemapTree::class)->items();
            $queries = count(DB::getQueryLog());
            DB::disableQueryLog();

            return $queries;
        };

        foreach (['a', 'b'] as $key) {
            $this->node($this->type($key, 'content', $key, ['type' => true, 'nodes' => true], sectionTitle: strtoupper($key)), $key.'-1', 'One');
        }

        $few = $count();

        foreach (['c', 'd', 'e'] as $key) {
            $type = $this->type($key, 'content', 'a/'.$key, ['type' => true, 'nodes' => true], 'a', strtoupper($key));
            $this->node($type, $key.'-1', 'One');
            $this->node($type, $key.'-2', 'Two');
        }

        $this->assertSame($few, $count());
    }
}
