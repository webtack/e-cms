<?php

namespace Webtack\Ecms\Tests\Feature\Theme;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Webtack\Ecms\Content\ContentNodeCreator;
use Webtack\Ecms\Models\ContentNode;
use Webtack\Ecms\Models\ContentType;
use Webtack\Ecms\Tests\Feature\Theme\Concerns\UsesFixtureThemes;
use Webtack\Ecms\Tests\TestCase;

/** The counter reaches public templates as the integer attribute `views` of the node. */
class NodeViewsTemplateTest extends TestCase
{
    use RefreshDatabase;
    use UsesFixtureThemes;

    protected function setUp(): void
    {
        parent::setUp();

        // Counting is switched off so the printed value is the stored one, not the one after the visit.
        config(['content.views.enabled' => false]);

        $this->makeTheme('default', [
            'html' => '', 'layout' => '', 'home' => '', 'errors/404' => '404',
            'pages/page' => 'PAGE={{ var_export($node->views, true) }}',
            'content/details' => 'DETAILS={{ var_export($node->views, true) }}',
            'content/list' => '@foreach ($paginator as $card)[{{ $card->slug }}={{ var_export($card->views, true) }}]@endforeach',
        ]);
        $this->useTheme('default');
    }

    private function node(ContentType $type, string $slug, int $views): ContentNode
    {
        $node = app(ContentNodeCreator::class)->create($type, ['title' => ucfirst($slug)], slug: $slug, authorId: $this->authorId(), views: $views);

        return $node;
    }

    public function test_a_details_template_reads_views_as_an_integer(): void
    {
        $type = ContentType::create(['key' => 'news', 'name' => 'News', 'slug' => 'news']);
        $this->node($type, 'hello', 12);

        $this->get('/news/hello')->assertOk()->assertSee('DETAILS=12', false);
    }

    public function test_a_page_template_reads_views_as_an_integer(): void
    {
        $this->node(ContentType::create(['key' => 'static', 'name' => 'Static', 'kind' => 'page']), 'about', 7);

        $this->get('/about')->assertOk()->assertSee('PAGE=7', false);
    }

    public function test_the_cards_of_a_list_have_their_own_views(): void
    {
        $type = ContentType::create(['key' => 'news', 'name' => 'News', 'slug' => 'news']);
        $this->node($type, 'one', 3);
        $this->node($type, 'two', 0);

        $this->get('/news')->assertOk()->assertSee('[one=3]', false)->assertSee('[two=0]', false);
    }
}
