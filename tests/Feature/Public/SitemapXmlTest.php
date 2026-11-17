<?php

namespace Webtack\Ecms\Tests\Feature\Public;

use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Testing\TestResponse;
use Webtack\Ecms\Content\ContentNodeCreator;
use Webtack\Ecms\Content\SitemapXml;
use Webtack\Ecms\Models\ContentNode;
use Webtack\Ecms\Models\ContentType;
use Webtack\Ecms\Tests\Fixtures\User;
use Webtack\Ecms\Tests\TestCase;

/** `GET /sitemap.xml`: the document, the settings it follows, visibility, dates, ordering and the 50 000 limit. */
class SitemapXmlTest extends TestCase
{
    use RefreshDatabase;

    /** The scheme and host the test requests go to (`APP_URL`). */
    private function root(): string
    {
        return rtrim((string) config('app.url'), '/');
    }

    private function type(string $key, string $kind, ?string $slug, ?array $sitemap): ContentType
    {
        return ContentType::create([
            'key' => $key, 'name' => ucfirst($key), 'kind' => $kind, 'slug' => $slug,
            'settings' => $sitemap === null ? null : ['sitemap' => $sitemap],
        ]);
    }

    private function node(ContentType $type, string $slug, array $attributes = []): ContentNode
    {
        $node = app(ContentNodeCreator::class)->create(
            $type,
            ['title' => ucfirst($slug)],
            slug: $slug,
            authorId: User::factory()->create()->id,
            publishedAt: $attributes['published_at'] ?? now()->subDay(),
            isActive: $attributes['is_active'] ?? true,
            unpublishedAt: $attributes['unpublished_at'] ?? null,
        );

        if (isset($attributes['updated_at'])) {
            ContentNode::query()->whereKey($node->id)->update(['updated_at' => $attributes['updated_at']]);
        }

        return $node;
    }

    /** @return list<string> The `loc` values in the document order. */
    private function locs(TestResponse $response): array
    {
        return array_map('strval', $this->document($response)->xpath('//sm:url/sm:loc'));
    }

    private function document(TestResponse $response): \SimpleXMLElement
    {
        $xml = simplexml_load_string($response->getContent());
        $this->assertNotFalse($xml, 'The body is not well-formed XML.');
        $xml->registerXPathNamespace('sm', 'http://www.sitemaps.org/schemas/sitemap/0.9');

        return $xml;
    }

    /** @return array<string, string> The value of a child element of the `url` with this `loc`. */
    private function urlData(TestResponse $response, string $path): array
    {
        $url = $this->document($response)->xpath('//sm:url[sm:loc="'.$this->root().$path.'"]')[0] ?? null;
        $this->assertNotNull($url, "No url for {$path}");

        return collect((array) $url)->map(fn ($v) => (string) $v)->all();
    }

    public function test_the_document_is_xml_and_the_route_is_public(): void
    {
        $response = $this->get('/sitemap.xml');

        $response->assertOk()->assertHeader('Content-Type', 'application/xml; charset=utf-8');
        $this->assertSame([$this->root().'/'], $this->locs($response));
        $this->assertSame('sitemap.xml', explode('/', trim(route('public.sitemap.xml', [], false), '/'))[0]);
    }

    public function test_an_empty_configuration_lists_only_the_home_page(): void
    {
        $this->type('news', 'content', 'news', null);
        $this->node(ContentType::findByKey('news'), 'a');

        $response = $this->get('/sitemap.xml');

        $this->assertSame([$this->root().'/'], $this->locs($response));
        $this->assertSame(['loc' => $this->root().'/', 'changefreq' => 'monthly', 'priority' => '1.0'], $this->urlData($response, '/'));
    }

    public function test_the_list_and_the_records_follow_the_type_settings(): void
    {
        $news = $this->type('news', 'content', 'news', ['xml' => ['type' => true, 'nodes' => true, 'changefreq' => 'weekly', 'priority' => '0.5']]);
        $this->node($news, 'a', ['updated_at' => '2026-01-01 10:00:00']);
        $this->node($news, 'b', ['updated_at' => '2026-02-01 10:00:00']);

        $response = $this->get('/sitemap.xml');

        $this->assertSame([$this->root().'/', $this->root().'/news', $this->root().'/news/b', $this->root().'/news/a'], $this->locs($response));
        $this->assertSame('weekly', $this->urlData($response, '/news/a')['changefreq']);
        $this->assertSame('0.5', $this->urlData($response, '/news')['priority']);
    }

    public function test_only_the_list_when_only_the_type_checkbox_is_on(): void
    {
        $events = $this->type('events', 'content', 'events', ['xml' => ['type' => true, 'changefreq' => 'daily', 'priority' => '0.7']]);
        $this->node($events, 'party');

        $this->assertSame([$this->root().'/', $this->root().'/events'], $this->locs($this->get('/sitemap.xml')));
    }

    public function test_hidden_scheduled_and_expired_nodes_are_not_listed(): void
    {
        $news = $this->type('news', 'content', 'news', ['xml' => ['nodes' => true, 'changefreq' => 'weekly', 'priority' => '0.5']]);
        $this->node($news, 'visible');
        $this->node($news, 'off', ['is_active' => false]);
        $this->node($news, 'later', ['published_at' => now()->addDay()]);
        $this->node($news, 'gone', ['published_at' => now()->subWeek(), 'unpublished_at' => now()->subDay()]);

        $this->assertSame([$this->root().'/', $this->root().'/news/visible'], $this->locs($this->get('/sitemap.xml')));
    }

    public function test_pages_stand_on_their_own_slug(): void
    {
        $about = $this->type('about', 'page', null, ['xml' => ['nodes' => true, 'changefreq' => 'monthly', 'priority' => '0.9']]);
        $this->node($about, 'contacts');

        $response = $this->get('/sitemap.xml');

        $this->assertSame([$this->root().'/', $this->root().'/contacts'], $this->locs($response));
        $this->assertSame('0.9', $this->urlData($response, '/contacts')['priority']);
    }

    public function test_a_type_without_a_slug_is_not_listed(): void
    {
        $draft = $this->type('draft', 'content', null, ['xml' => ['type' => true, 'nodes' => true, 'changefreq' => 'weekly', 'priority' => '0.5']]);
        $this->node($draft, 'a');

        $this->assertSame([$this->root().'/'], $this->locs($this->get('/sitemap.xml')));
    }

    public function test_web_only_types_are_not_in_the_xml(): void
    {
        $news = $this->type('news', 'content', 'news', ['web' => ['type' => true, 'nodes' => true]]);
        $this->node($news, 'a');

        $this->assertSame([$this->root().'/'], $this->locs($this->get('/sitemap.xml')));
    }

    public function test_the_last_change_dates_have_the_offset_and_the_list_takes_the_latest(): void
    {
        $news = $this->type('news', 'content', 'news', ['xml' => ['type' => true, 'nodes' => true, 'changefreq' => 'weekly', 'priority' => '0.5']]);
        $this->node($news, 'old', ['updated_at' => '2026-11-10 08:00:00']);
        $this->node($news, 'new', ['updated_at' => '2026-11-13 23:37:48']);
        $this->node($news, 'hidden-latest', ['updated_at' => '2026-12-01 00:00:00', 'is_active' => false]);

        $response = $this->get('/sitemap.xml');
        $expected = Carbon::parse('2026-11-13 23:37:48', config('app.timezone'))->toAtomString();

        $this->assertMatchesRegularExpression('/^\d{4}-\d\d-\d\dT\d\d:\d\d:\d\d[+-]\d\d:\d\d$/', $expected);
        $this->assertSame($expected, $this->urlData($response, '/news/new')['lastmod']);
        $this->assertSame($expected, $this->urlData($response, '/news')['lastmod']);
        $this->assertArrayNotHasKey('lastmod', $this->urlData($response, '/'));
    }

    public function test_a_list_without_visible_nodes_has_no_lastmod(): void
    {
        $this->type('events', 'content', 'events', ['xml' => ['type' => true, 'changefreq' => 'weekly', 'priority' => '0.5']]);

        $this->assertArrayNotHasKey('lastmod', $this->urlData($this->get('/sitemap.xml'), '/events'));
    }

    public function test_types_are_ordered_by_key_and_each_list_comes_before_its_records(): void
    {
        $zeta = $this->type('zeta', 'content', 'zeta', ['xml' => ['type' => true, 'nodes' => true, 'changefreq' => 'weekly', 'priority' => '0.5']]);
        $alpha = $this->type('alpha', 'content', 'alpha', ['xml' => ['type' => true, 'nodes' => true, 'changefreq' => 'weekly', 'priority' => '0.5']]);
        $this->type('mid', 'content', 'mid', ['xml' => ['type' => true, 'changefreq' => 'weekly', 'priority' => '0.5']]);
        $this->node($zeta, 'z1');
        $this->node($alpha, 'a1');

        $this->assertSame([
            $this->root().'/', $this->root().'/alpha', $this->root().'/alpha/a1', $this->root().'/mid', $this->root().'/zeta', $this->root().'/zeta/z1',
        ], $this->locs($this->get('/sitemap.xml')));
    }

    public function test_all_records_are_listed_beyond_the_legacy_limit(): void
    {
        $news = $this->type('news', 'content', 'news', ['xml' => ['nodes' => true, 'changefreq' => 'weekly', 'priority' => '0.5']]);
        ContentNode::factory()->count(120)->sequence(fn ($sequence) => ['slug' => 'item-'.$sequence->index])->create(['content_type' => $news->key]);

        $this->assertCount(121, $this->locs($this->get('/sitemap.xml')));
    }

    public function test_the_document_is_cut_at_the_limit_and_the_cut_is_logged(): void
    {
        Log::spy();
        $news = $this->type('news', 'content', 'news', ['xml' => ['type' => true, 'nodes' => true, 'changefreq' => 'weekly', 'priority' => '0.5']]);
        ContentNode::factory()->count(5)->sequence(fn ($sequence) => ['slug' => 'item-'.$sequence->index])->create(['content_type' => $news->key]);

        $xml = app(SitemapXml::class)->build($this->root(), limit: 3);

        $document = simplexml_load_string($xml);
        $this->assertCount(3, $document->url);
        $this->assertSame($this->root().'/', (string) $document->url[0]->loc);
        Log::shouldHaveReceived('warning')->once();
    }

    public function test_the_limit_without_a_cut_logs_nothing(): void
    {
        Log::spy();
        $news = $this->type('news', 'content', 'news', ['xml' => ['nodes' => true, 'changefreq' => 'weekly', 'priority' => '0.5']]);
        ContentNode::factory()->count(2)->create(['content_type' => $news->key]);

        $document = simplexml_load_string(app(SitemapXml::class)->build($this->root(), limit: 3));

        $this->assertCount(3, $document->url);
        Log::shouldNotHaveReceived('warning');
    }

    public function test_the_query_count_does_not_depend_on_the_number_of_types(): void
    {
        $count = function (): int {
            DB::flushQueryLog();
            DB::enableQueryLog();
            $this->get('/sitemap.xml')->assertOk();
            $queries = count(DB::getQueryLog());
            DB::disableQueryLog();

            return $queries;
        };

        foreach (['a', 'b'] as $key) {
            $type = $this->type($key, 'content', $key, ['xml' => ['type' => true, 'nodes' => true, 'changefreq' => 'weekly', 'priority' => '0.5']]);
            $this->node($type, $key.'-x');
        }

        $few = $count();

        foreach (['c', 'd', 'e', 'f'] as $key) {
            $type = $this->type($key, 'content', $key, ['xml' => ['type' => true, 'nodes' => true, 'changefreq' => 'weekly', 'priority' => '0.5']]);
            $this->node($type, $key.'-x');
            $this->node($type, $key.'-y');
        }

        $this->assertSame($few, $count());
    }
}
