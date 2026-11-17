<?php

namespace Webtack\Ecms\Tests\Feature\Public;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\View;
use Webtack\Ecms\Content\ContentNodeCreator;
use Webtack\Ecms\Models\ContentType;
use Webtack\Ecms\Tests\TestCase;

/** `GET /search`: the term rules, the response and the template contract (a test template stands in for a theme's). */
class SearchRouteTest extends TestCase
{
    use RefreshDatabase;

    private string $views;

    protected function setUp(): void
    {
        parent::setUp();

        // The `default` theme has no `search/results`, so a throw-away template is put in front of the theme's views.
        $this->views = sys_get_temp_dir().'/search-views-'.uniqid();
        File::ensureDirectoryExists($this->views.'/search');
        File::put($this->views.'/search/results.blade.php', <<<'BLADE'
<div id="search" data-query="{{ $query }}" data-too-short="{{ $tooShort ? 'yes' : 'no' }}" data-min="{{ $minLength }}" data-results="{{ $results === null ? 'none' : 'list' }}">
@if ($results !== null)
@foreach ($results as $result)
<a class="hit" href="{{ $result->url }}">{{ $result->title }}</a>
@endforeach
<span class="next">{{ $results->nextPageUrl() }}</span>
<span class="total">{{ $results->total() }}</span>
@endif
</div>
BLADE);
        View::prependNamespace('theme', $this->views);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->views);

        parent::tearDown();
    }

    private function record(string $slug, string $title): void
    {
        $type = ContentType::findByKey('news') ?? ContentType::create(['key' => 'news', 'name' => 'News', 'slug' => 'news']);

        app(ContentNodeCreator::class)->create($type, ['title' => $title], slug: $slug, authorId: $this->authorId());
    }

    public function test_the_page_without_a_term_shows_the_form_only(): void
    {
        $this->get('/search')
            ->assertOk()
            ->assertSee('data-query=""', false)
            ->assertSee('data-too-short="no"', false)
            ->assertSee('data-results="none"', false);
    }

    public function test_a_two_character_term_gives_the_hint_and_runs_no_node_query(): void
    {
        DB::flushQueryLog();
        DB::enableQueryLog();

        $this->get('/search?q=ab')
            ->assertOk()
            ->assertSee('data-query="ab"', false)
            ->assertSee('data-too-short="yes"', false)
            ->assertSee('data-min="3"', false)
            ->assertSee('data-results="none"', false);

        $nodeQueries = array_filter(DB::getQueryLog(), fn (array $query) => str_contains($query['query'], 'content_nodes'));
        DB::disableQueryLog();

        $this->assertSame([], array_values($nodeQueries));
    }

    public function test_a_three_character_term_is_searched(): void
    {
        $this->record('lamp-story', 'Lamp story');

        $this->get('/search?q=lam')
            ->assertOk()
            ->assertSee('data-too-short="no"', false)
            ->assertSee('data-results="list"', false)
            ->assertSee('href="/news/lamp-story"', false);
    }

    public function test_the_term_is_trimmed(): void
    {
        $this->get('/search?q=%20%20lamp%20%20')->assertOk()->assertSee('data-query="lamp"', false);
        $this->get('/search?q=%20%20ab%20%20')->assertOk()->assertSee('data-too-short="yes"', false);
    }

    public function test_a_long_term_is_cut_to_the_maximum_length(): void
    {
        $this->get('/search?q='.str_repeat('a', 150))
            ->assertOk()
            ->assertSee('data-query="'.str_repeat('a', 100).'"', false)
            ->assertDontSee(str_repeat('a', 101), false);
    }

    public function test_an_array_term_counts_as_empty(): void
    {
        $this->get('/search?q[]=lamp')
            ->assertOk()
            ->assertSee('data-query=""', false)
            ->assertSee('data-results="none"', false);
    }

    public function test_the_pagination_links_keep_the_term(): void
    {
        config(['content.per_page' => 2]);

        foreach (['a', 'b', 'c'] as $slug) {
            $this->record("lamp-{$slug}", "Lamp {$slug}");
        }

        $html = $this->get('/search?q=lamp')->assertOk()->getContent();

        $this->assertMatchesRegularExpression('#<span class="next">[^<]*q=lamp[^<]*page=2#', $html);
        $this->get('/search?q=lamp&page=2')->assertOk()->assertSee('<span class="total">3</span>', false);
    }

    public function test_no_matches_answers_200_with_an_empty_list(): void
    {
        $this->get('/search?q=nothing')
            ->assertOk()
            ->assertSee('data-results="list"', false)
            ->assertSee('<span class="total">0</span>', false)
            ->assertDontSee('class="hit"', false);
    }

    public function test_the_page_is_not_indexed(): void
    {
        $this->get('/search')->assertHeader('X-Robots-Tag', 'noindex');
        $this->get('/search?q=lamp')->assertHeader('X-Robots-Tag', 'noindex');
    }

    public function test_a_deeper_address_gets_the_site_404(): void
    {
        $this->get('/search/lamp')
            ->assertNotFound()
            ->assertSee('class="site-header', false)
            ->assertSee('>404</h1>', false);
    }

    public function test_search_over_the_limit_is_429_without_querying_nodes_and_other_pages_still_open(): void
    {
        config(['limits.search_per_minute' => 2]);

        $this->get('/search?q=lamp')->assertOk();
        $this->get('/search?q=lamp')->assertOk();

        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->get('/search?q=lamp')->assertStatus(429);
        $nodeQueries = array_filter(DB::getQueryLog(), fn (array $query) => str_contains($query['query'], 'content_nodes'));
        DB::disableQueryLog();

        $this->assertSame([], array_values($nodeQueries));
        $this->get('/')->assertStatus(200);
    }

    public function test_search_limit_of_one_ip_does_not_affect_another(): void
    {
        config(['limits.search_per_minute' => 1]);

        $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.1'])->get('/search?q=lamp')->assertOk();
        $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.1'])->get('/search?q=lamp')->assertStatus(429);
        $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.2'])->get('/search?q=lamp')->assertOk();
    }

    public function test_the_address_is_the_search_route_not_the_node_route(): void
    {
        $this->assertSame('public.search', Route::getRoutes()->match(Request::create('/search'))->getName());
    }
}
