<?php

namespace Webtack\Ecms\Tests\Feature\Content;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Mockery;
use RuntimeException;
use Webtack\Ecms\Content\ViewCounter;
use Webtack\Ecms\Models\ContentNode;
use Webtack\Ecms\Models\ContentType;
use Webtack\Ecms\Tests\Fixtures\User;
use Webtack\Ecms\Tests\TestCase;

class ViewCounterTest extends TestCase
{
    use RefreshDatabase;

    private const BROWSER = 'Mozilla/5.0 (X11; Linux x86_64) Firefox/120.0';

    protected function setUp(): void
    {
        parent::setUp();

        config(['content.views.enabled' => true]);
        ContentType::create(['key' => 'news', 'name' => 'News', 'slug' => 'news']);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function node(string $slug = 'hello'): ContentNode
    {
        return ContentNode::factory()->create(['content_type' => 'news', 'slug' => $slug]);
    }

    private function request(string $ip = '10.0.0.1', ?string $agent = self::BROWSER, string $method = 'GET'): Request
    {
        $request = Request::create('/news/hello', $method, server: ['REMOTE_ADDR' => $ip]);
        $agent === null ? $request->headers->remove('User-Agent') : $request->headers->set('User-Agent', $agent);

        return $request;
    }

    private function views(ContentNode $node): int
    {
        return (int) ContentNode::query()->whereKey($node->id)->value('views');
    }

    public function test_a_view_is_counted(): void
    {
        $node = $this->node();

        app(ViewCounter::class)->record($this->request(), $node);

        $this->assertSame(1, $this->views($node));
    }

    public function test_the_same_visitor_is_counted_every_time_by_default(): void
    {
        $node = $this->node();
        $counter = app(ViewCounter::class);
        Cache::spy();

        $counter->record($this->request(), $node);
        $counter->record($this->request(), $node);

        $this->assertSame(2, $this->views($node));
        Cache::shouldNotHaveReceived('add');
    }

    public function test_the_same_visitor_is_counted_once_per_configured_period(): void
    {
        config(['content.views.ttl' => 86400]);
        $node = $this->node();
        $counter = app(ViewCounter::class);

        $counter->record($this->request(), $node);
        $counter->record($this->request(), $node);

        $this->assertSame(1, $this->views($node));
    }

    public function test_another_visitor_or_another_node_is_counted(): void
    {
        config(['content.views.ttl' => 86400]);
        $node = $this->node();
        $other = $this->node('other');
        $counter = app(ViewCounter::class);

        $counter->record($this->request('10.0.0.1'), $node);
        $counter->record($this->request('10.0.0.2'), $node);
        $counter->record($this->request('10.0.0.3', 'Safari/17'), $node);
        $counter->record($this->request('10.0.0.1'), $other);

        $this->assertSame(3, $this->views($node));
        $this->assertSame(1, $this->views($other));
    }

    public function test_the_same_visitor_is_counted_again_after_the_period(): void
    {
        config(['content.views.ttl' => 86400]);
        $node = $this->node();
        $counter = app(ViewCounter::class);
        Carbon::setTestNow('2026-11-06 10:00:00');

        $counter->record($this->request(), $node);
        Carbon::setTestNow(Carbon::parse('2026-11-06 10:00:00')->addSeconds(config('content.views.ttl') - 1));
        $counter->record($this->request(), $node);
        $this->assertSame(1, $this->views($node));

        Carbon::setTestNow(Carbon::now()->addSeconds(2));
        $counter->record($this->request(), $node);

        $this->assertSame(2, $this->views($node));
    }

    public function test_bots_and_an_empty_user_agent_are_not_counted(): void
    {
        $node = $this->node();
        $counter = app(ViewCounter::class);

        foreach (['Googlebot/2.1', 'curl/8.0', 'PYTHON-REQUESTS/2.31', '', '   ', null] as $agent) {
            $counter->record($this->request(agent: $agent), $node);
        }

        $this->assertSame(0, $this->views($node));
    }

    public function test_the_bot_list_comes_from_the_config(): void
    {
        $node = $this->node();
        config(['content.views.bot_agents' => ['firefox']]);

        app(ViewCounter::class)->record($this->request(), $node);

        $this->assertSame(0, $this->views($node));
    }

    public function test_a_head_request_is_not_counted(): void
    {
        $node = $this->node();

        app(ViewCounter::class)->record($this->request(method: 'HEAD'), $node);

        $this->assertSame(0, $this->views($node));
    }

    public function test_an_authenticated_user_is_counted_by_default(): void
    {
        $node = $this->node();
        $request = $this->request();
        $request->setUserResolver(fn () => User::factory()->create());

        app(ViewCounter::class)->record($request, $node);

        $this->assertSame(1, $this->views($node));
    }

    public function test_an_authenticated_user_is_not_counted_when_excluded_by_configuration(): void
    {
        config(['content.views.count_authenticated' => false]);
        $node = $this->node();
        $request = $this->request();
        $request->setUserResolver(fn () => User::factory()->create());

        app(ViewCounter::class)->record($request, $node);

        $this->assertSame(0, $this->views($node));
    }

    public function test_a_disabled_counter_counts_nothing(): void
    {
        $node = $this->node();
        config(['content.views.enabled' => false]);

        app(ViewCounter::class)->record($this->request(), $node);

        $this->assertSame(0, $this->views($node));
    }

    public function test_counting_keeps_updated_at(): void
    {
        $node = $this->node();
        $before = DB::table('content_nodes')->where('id', $node->id)->value('updated_at');
        Carbon::setTestNow(Carbon::now()->addDay());

        app(ViewCounter::class)->record($this->request(), $node);

        $this->assertSame($before, DB::table('content_nodes')->where('id', $node->id)->value('updated_at'));
        $this->assertSame(1, $this->views($node));
    }

    public function test_a_cache_failure_is_logged_and_the_view_is_not_counted(): void
    {
        config(['content.views.ttl' => 86400]);
        $node = $this->node();
        Cache::shouldReceive('add')->once()->andThrow(new RuntimeException('cache down'));
        Log::shouldReceive('warning')->once();

        app(ViewCounter::class)->record($this->request(), $node);

        $this->assertSame(0, $this->views($node));
    }

    public function test_an_update_failure_is_logged_and_swallowed(): void
    {
        $node = Mockery::mock(ContentNode::class)->makePartial();
        $node->shouldReceive('incrementViews')->once()->andThrow(new RuntimeException('db down'));
        $node->setAttribute('id', 1);
        Log::shouldReceive('warning')->once();

        app(ViewCounter::class)->record($this->request(), $node);

        $this->addToAssertionCount(1);
    }
}
