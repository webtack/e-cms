<?php

namespace Webtack\Ecms\Tests\Feature\Public;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Webtack\Ecms\Content\ContentNodeCreator;
use Webtack\Ecms\Models\ContentNode;
use Webtack\Ecms\Models\ContentType;
use Webtack\Ecms\Tests\Fixtures\User;
use Webtack\Ecms\Tests\TestCase;

/** Counting views of public node pages: which requests count (see `Webtack\Ecms\Content\ViewCounter` for the rules). */
class PublicViewsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['content.views.enabled' => true]);
    }

    private function record(string $typeKey, string $typeSlug, string $slug, bool $active = true): ContentNode
    {
        $type = ContentType::findByKey($typeKey) ?? ContentType::create(['key' => $typeKey, 'name' => ucfirst($typeKey), 'slug' => $typeSlug]);

        return app(ContentNodeCreator::class)->create($type, ['title' => ucfirst($slug)], slug: $slug, authorId: $this->authorId(), isActive: $active, views: 0);
    }

    private function page(string $slug): ContentNode
    {
        $type = ContentType::findByKey('static') ?? ContentType::create(['key' => 'static', 'name' => 'Static', 'kind' => 'page']);

        return app(ContentNodeCreator::class)->create($type, ['title' => ucfirst($slug)], slug: $slug, authorId: $this->authorId(), views: 0);
    }

    private function views(ContentNode $node): int
    {
        return (int) ContentNode::query()->whereKey($node->id)->value('views');
    }

    public function test_a_visit_to_a_content_node_counts(): void
    {
        $node = $this->record('news', 'news', 'hello');

        $this->get('/news/hello')->assertOk();

        $this->assertSame(1, $this->views($node));
    }

    public function test_a_visit_to_a_page_counts(): void
    {
        $page = $this->page('about');

        $this->get('/about')->assertOk();

        $this->assertSame(1, $this->views($page));
    }

    public function test_a_visit_to_a_node_of_a_type_with_a_nested_slug_counts(): void
    {
        $node = $this->record('institutions', 'common/institutions', 'library');

        $this->get('/common/institutions/library')->assertOk();

        $this->assertSame(1, $this->views($node));
    }

    public function test_a_repeated_visit_of_the_same_visitor_counts_every_time_by_default(): void
    {
        $node = $this->record('news', 'news', 'hello');

        $this->get('/news/hello');
        $this->get('/news/hello');

        $this->assertSame(2, $this->views($node));
    }

    public function test_a_repeated_visit_counts_once_when_a_period_is_configured(): void
    {
        config(['content.views.ttl' => 86400]);
        $node = $this->record('news', 'news', 'hello');

        $this->get('/news/hello');
        $this->get('/news/hello');

        $this->assertSame(1, $this->views($node));
    }

    public function test_a_signed_in_user_counts_by_default(): void
    {
        $node = $this->record('news', 'news', 'hello');

        $this->actingAs(User::factory()->create())->get('/news/hello')->assertOk();

        $this->assertSame(1, $this->views($node));
    }

    public function test_a_signed_in_user_is_excluded_by_configuration(): void
    {
        config(['content.views.count_authenticated' => false]);
        $node = $this->record('news', 'news', 'hello');

        $this->actingAs(User::factory()->create())->get('/news/hello')->assertOk();

        $this->assertSame(0, $this->views($node));
    }

    public function test_a_list_counts_nothing(): void
    {
        $node = $this->record('news', 'news', 'hello');

        $this->get('/news')->assertOk();

        $this->assertSame(0, $this->views($node));
    }

    public function test_not_found_and_invisible_nodes_count_nothing(): void
    {
        $hidden = $this->record('news', 'news', 'off', active: false);

        $this->get('/news/off')->assertNotFound();
        $this->get('/news/missing')->assertNotFound();
        $this->get('/unknown')->assertNotFound();

        $this->assertSame(0, $this->views($hidden));
    }

    public function test_bots_and_head_requests_do_not_count(): void
    {
        $node = $this->record('news', 'news', 'hello');

        $this->get('/news/hello', ['User-Agent' => 'Googlebot/2.1'])->assertOk();
        $this->head('/news/hello')->assertOk();
        $this->assertSame(0, $this->views($node));
    }

    public function test_a_disabled_counter_counts_nothing(): void
    {
        $node = $this->record('news', 'news', 'hello');
        config(['content.views.enabled' => false]);

        $this->get('/news/hello')->assertOk();

        $this->assertSame(0, $this->views($node));
    }

    public function test_a_failure_does_not_change_the_response(): void
    {
        config(['content.views.ttl' => 86400]);
        $node = $this->record('news', 'news', 'hello');
        Cache::partialMock()->shouldReceive('add')->andThrow(new RuntimeException('cache down'));
        Log::shouldReceive('warning')->once();

        $this->get('/news/hello')->assertOk()->assertSee('Hello');

        $this->assertSame(0, $this->views($node));
    }

    public function test_a_visit_adds_one_to_an_existing_counter(): void
    {
        $node = $this->record('news', 'news', 'hello');
        $node->incrementViews(4);

        $this->get('/news/hello')->assertOk();

        $this->assertSame(5, $this->views($node));
    }

    public function test_popular_nodes_come_first_when_the_type_sorts_by_views(): void
    {
        $type = ContentType::create([
            'key' => 'news', 'name' => 'News', 'slug' => 'news',
            'settings' => ['public' => ['sort' => ['field' => 'views', 'direction' => 'desc']]],
        ]);
        foreach (['Three' => 3, 'Thirty' => 30, 'Twelve' => 12] as $title => $views) {
            app(ContentNodeCreator::class)
                ->create($type, ['title' => $title], slug: strtolower($title), authorId: $this->authorId())
                ->incrementViews($views);
        }

        $html = $this->get('/news')->assertOk()->getContent();

        $this->assertLessThan(strpos($html, 'Twelve'), strpos($html, 'Thirty'));
        $this->assertLessThan(strpos($html, 'Three'), strpos($html, 'Twelve'));
    }
}
