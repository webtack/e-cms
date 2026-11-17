<?php

namespace Webtack\Ecms\Tests\Feature\Theme;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Webtack\Ecms\Content\ContentNodeCreator;
use Webtack\Ecms\Content\ContentValueWriter;
use Webtack\Ecms\Content\ValueOwner;
use Webtack\Ecms\Models\ContentNode;
use Webtack\Ecms\Models\ContentType;
use Webtack\Ecms\Tests\Feature\Theme\Concerns\UsesFixtureThemes;
use Webtack\Ecms\Tests\TestCase;
use Webtack\Ecms\Theme\Breadcrumbs;

/** The trail by page kind, its addresses, the JSON-LD, and how it reaches templates and the `breadcrumbs` hook. */
class BreadcrumbsTest extends TestCase
{
    use RefreshDatabase, UsesFixtureThemes;

    protected function setUp(): void
    {
        parent::setUp();

        app()->setLocale('en');
    }

    private function home(): string
    {
        return rtrim(url('/'), '/').'/';
    }

    private function type(string $key, ?string $slug, ?string $parent = null, string $kind = 'content'): ContentType
    {
        return ContentType::create(['key' => $key, 'name' => "{$key} (admin)", 'slug' => $slug, 'parent_type' => $parent, 'kind' => $kind]);
    }

    private function node(ContentType $type, string $slug, ?string $title = 'Hello'): ContentNode
    {
        return app(ContentNodeCreator::class)->create($type, $title === null ? [] : ['title' => $title], slug: $slug, authorId: $this->authorId());
    }

    /** @return list<array{title: string, url: string|null}> */
    private function trail(ContentType $type, ?ContentNode $node, ?string $title): array
    {
        return app(Breadcrumbs::class)->for($type, $node, $title);
    }

    private function nested(): array
    {
        $common = $this->type('common', 'common');
        $institutions = $this->type('institutions', 'common/institutions', 'common');

        return [$common, $institutions, $this->node($institutions, 'hello')];
    }

    public function test_a_record_of_a_nested_type(): void
    {
        [, $institutions, $node] = $this->nested();

        $this->assertSame([
            ['title' => 'Home', 'url' => $this->home()],
            ['title' => 'common (admin)', 'url' => url('/common')],
            ['title' => 'institutions (admin)', 'url' => url('/common/institutions')],
            ['title' => 'Hello', 'url' => null],
        ], $this->trail($institutions, $node, 'Hello'));
    }

    public function test_types_are_titled_with_their_section_title(): void
    {
        $news = $this->type('news', 'news');
        app(ContentValueWriter::class)->write(ValueOwner::forType($news), ['section_title' => 'Новини']);

        $trail = $this->trail($news, $this->node($news, 'hello'), 'Hello');

        $this->assertSame('Новини', $trail[1]['title']);
    }

    public function test_a_parent_type_without_a_slug_is_skipped(): void
    {
        $this->type('group', null);
        $child = $this->type('news', 'news');
        // Written around the validation, which refuses a child under a parent without a slug.
        DB::table('content_types')->where('key', 'news')->update(['parent_type' => 'group']);
        $child = $child->fresh();

        $this->assertSame(
            [['title' => 'Home', 'url' => $this->home()], ['title' => 'news (admin)', 'url' => null]],
            $this->trail($child, null, null),
        );
    }

    public function test_a_record_without_a_title_uses_its_slug(): void
    {
        $news = $this->type('news', 'news');

        $trail = $this->trail($news, $this->node($news, 'no-title', 'Has title'), null);

        $this->assertSame('no-title', $trail[2]['title']);
    }

    public function test_a_list_ends_with_the_type_as_the_current_page(): void
    {
        $news = $this->type('news', 'news');

        $this->assertSame(
            [['title' => 'Home', 'url' => $this->home()], ['title' => 'news (admin)', 'url' => null]],
            $this->trail($news, null, null),
        );
    }

    public function test_a_page_is_home_and_its_own_title(): void
    {
        $static = $this->type('static', null, kind: 'page');

        $this->assertSame(
            [['title' => 'Home', 'url' => $this->home()], ['title' => 'About', 'url' => null]],
            $this->trail($static, $this->node($static, 'about', 'About'), 'About'),
        );
    }

    public function test_home_and_404_have_an_empty_trail(): void
    {
        $this->assertSame([], app(Breadcrumbs::class)->for(null, null, null));
    }

    public function test_a_looping_hierarchy_does_not_hang(): void
    {
        $this->type('a', 'a');
        $b = $this->type('b', 'a/b', 'a');
        DB::table('content_types')->where('key', 'a')->update(['parent_type' => 'b']);

        $this->assertCount(3, $this->trail($b->fresh(), null, null));
    }

    public function test_the_home_title_follows_the_language(): void
    {
        app()->setLocale('uk');

        $this->assertSame('Головна', $this->trail($this->type('news', 'news'), null, null)[0]['title']);
    }

    public function test_the_json_ld_of_a_record_trail(): void
    {
        [, $institutions, $node] = $this->nested();
        $json = json_decode(app(Breadcrumbs::class)->jsonLd($this->trail($institutions, $node, 'Hello')), true);

        $this->assertSame('BreadcrumbList', $json['@type']);
        $this->assertSame('https://schema.org', $json['@context']);
        $this->assertSame([1, 2, 3, 4], array_column($json['itemListElement'], 'position'));
        $this->assertSame(url('/common'), $json['itemListElement'][1]['item']);
        $this->assertArrayNotHasKey('item', $json['itemListElement'][3]);
        $this->assertSame('Hello', $json['itemListElement'][3]['name']);
    }

    public function test_the_json_ld_cannot_close_the_script_and_keeps_readable_text(): void
    {
        $text = app(Breadcrumbs::class)->jsonLd([
            ['title' => 'Головна', 'url' => $this->home()],
            ['title' => '</script><script>alert(1)</script>', 'url' => null],
        ]);

        $this->assertStringNotContainsString('</script>', $text);
        $this->assertStringContainsString('Головна', $text);
        $this->assertStringContainsString('"https:\/\/', str_replace('/', '\/', $text));
    }

    public function test_a_short_trail_has_no_json_ld(): void
    {
        $this->assertNull(app(Breadcrumbs::class)->jsonLd([['title' => 'Home', 'url' => $this->home()]]));
        $this->assertNull(app(Breadcrumbs::class)->jsonLd([]));
    }

    /** A theme whose document shell, layout and a partial all print both variables. */
    private function fixtureTheme(?string $hooks = null): void
    {
        $print = 'T={!! json_encode($breadcrumbs, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES) !!};LD={!! var_export($breadcrumbsJsonLd, true) !!}';
        $this->makeTheme('default', [
            'html' => 'HTML[{{ var_export($breadcrumbsJsonLd !== null, true) }}]@yield("content")',
            'layout' => '@extends("theme::html")@section("content")LAYOUT[{{ count($breadcrumbs) }}]@include("theme::partials.crumbs")@yield("body")@endsection',
            'partials/crumbs' => 'PARTIAL[{{ count($breadcrumbs) }}]',
            'home' => '@extends("theme::layout")@section("body")'.$print.'@endsection',
            'errors/404' => '@extends("theme::layout")@section("body")'.$print.'@endsection',
            'pages/page' => '@extends("theme::layout")@section("body")'.$print.'@endsection',
            'content/details' => '@extends("theme::layout")@section("body")'.$print.'@endsection',
            'content/list' => '@extends("theme::layout")@section("body")'.$print.';SECTION={{ $sectionTitle }}@endsection',
        ], $hooks);
        $this->useTheme('default');
    }

    public function test_templates_the_shell_and_partials_receive_the_trail(): void
    {
        $this->fixtureTheme();
        [, , $node] = $this->nested();

        $this->get('/common/institutions/hello')->assertOk()
            ->assertSee('"title":"Hello","url":null', false)
            ->assertSee('HTML[true]', false)
            ->assertSee('LAYOUT[4]', false)
            ->assertSee('PARTIAL[4]', false);
    }

    public function test_home_and_404_have_empty_variables(): void
    {
        $this->fixtureTheme();

        $this->get('/')->assertOk()->assertSee('T=[];LD=NULL', false)->assertSee('HTML[false]', false);
        $this->get('/missing')->assertNotFound()->assertSee('T=[];LD=NULL', false);
    }

    public function test_a_list_and_a_page_get_their_trail_and_the_list_its_section_title(): void
    {
        $this->fixtureTheme();
        $news = $this->type('news', 'news');
        app(ContentValueWriter::class)->write(ValueOwner::forType($news), ['section_title' => 'Новини']);
        $this->node($this->type('static', null, kind: 'page'), 'about', 'About');

        $this->get('/news')->assertOk()->assertSee('"title":"Новини","url":null', false)->assertSee('SECTION=Новини', false);
        $this->get('/about')->assertOk()->assertSee('"title":"About","url":null', false);
    }

    public function test_a_list_of_a_type_without_a_section_title_uses_the_name(): void
    {
        $this->fixtureTheme();
        $this->type('news', 'news');

        $this->get('/news')->assertOk()->assertSee('SECTION=news (admin)', false);
    }

    public function test_the_hook_can_clear_or_retitle_the_trail(): void
    {
        $this->fixtureTheme(<<<'PHP'
<?php
use Webtack\Ecms\Theme\ThemeHooks;
return function (ThemeHooks $hooks): void {
    $hooks->preprocess('breadcrumbs', function (array &$vars) {
        if (count($vars['breadcrumbs']) > 2) {
            $vars['breadcrumbs'][0]['title'] = 'Start';
        } else {
            $vars['breadcrumbs'] = [];
        }
    });
};
PHP);
        $news = $this->type('news', 'news');
        $this->node($news, 'hello');

        $this->get('/news/hello')->assertOk()->assertSee('Start', false)->assertSee('"name":"Start"', false);
        $this->get('/news')->assertOk()->assertSee('T=[];LD=NULL', false);
    }
}
