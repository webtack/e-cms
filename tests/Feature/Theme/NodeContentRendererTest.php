<?php

namespace Webtack\Ecms\Tests\Feature\Theme;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Webtack\Ecms\Content\ContentNodeCreator;
use Webtack\Ecms\Content\ContentValueReader;
use Webtack\Ecms\Content\ContentValueWriter;
use Webtack\Ecms\Content\PresentContext;
use Webtack\Ecms\Models\AppLanguage;
use Webtack\Ecms\Models\ContentField;
use Webtack\Ecms\Models\ContentNode;
use Webtack\Ecms\Models\ContentType;
use Webtack\Ecms\Support\AppSettings;
use Webtack\Ecms\Tests\Feature\Theme\Concerns\UsesFixtureThemes;
use Webtack\Ecms\Tests\TestCase;
use Webtack\Ecms\Theme\BlockRenderer;
use Webtack\Ecms\Theme\FieldRenderer;
use Webtack\Ecms\Theme\NodeContentRenderer;

class NodeContentRendererTest extends TestCase
{
    use RefreshDatabase, UsesFixtureThemes;

    /** A node_ref field prints the title and the content of each target: `(Title:content)`. */
    private const REF = '@foreach ($items as $item)({{ $item["title"] }}:{!! $item["content"] !!})@endforeach';

    private ContentType $type;

    protected function setUp(): void
    {
        parent::setUp();

        $this->type = ContentType::create(['key' => 'article', 'name' => 'Article', 'slug' => 'articles']);
        ContentField::create(['content_type' => 'article', 'key' => 'body', 'name' => 'Body', 'type' => 'string']);
        ContentField::create(['content_type' => 'article', 'key' => 'secret', 'name' => 'Secret', 'type' => 'string']);
        ContentField::create(['content_type' => 'article', 'key' => 'related', 'name' => 'Related', 'type' => 'node_ref', 'is_multiple' => true, 'settings' => ['content_type' => 'article']]);
        $this->type->update(['settings' => ['public' => ['page_fields' => ['body', 'related', 'secret'], 'list_fields' => ['body', 'related']]]]);
    }

    /** @param array<string, mixed> $attributes */
    private function article(string $title, array $attributes = [], ?ContentType $type = null): ContentNode
    {
        return app(ContentNodeCreator::class)->create(
            $type ?? $this->type,
            $type === null ? ['title' => $title, 'body' => "Body of {$title}", 'secret' => 'hidden'] : ['title' => $title],
            slug: ($type === null ? '' : 'block-').strtolower($title),
            authorId: $this->authorId(),
            publishedAt: $attributes['published_at'] ?? null,
            isActive: $attributes['is_active'] ?? true,
            unpublishedAt: $attributes['unpublished_at'] ?? null,
        );
    }

    private function link(ContentNode $from, ContentNode ...$to): void
    {
        app(ContentValueWriter::class)->write($from, ['related' => array_map(fn (ContentNode $node) => $node->uuid, $to)]);
    }

    /** @param array<string, string> $views */
    private function theme(array $views = []): void
    {
        $this->makeTheme('default', [
            'fields/field' => '<{{ $key }}={{ implode(",", $items) }}>',
            'fields/field-node_ref' => self::REF,
            'content/preview' => '[{{ $values["title"] }}|{{ $url }}@fields($values)]',
            'blocks/block' => '{block:@fields($values)}',
            ...$views,
        ]);
        $this->useTheme('default');
    }

    private function render(ContentNode|string $target, ?ContentNode $current = null, ?string $url = null): string
    {
        return app(NodeContentRenderer::class)->render($target instanceof ContentNode ? $target->uuid : $target, $current, $url);
    }

    /** The `related` field of `$from` rendered the way a page does. */
    private function renderRelated(ContentNode $from): string
    {
        return $this->renderField($from, 'related');
    }

    private function renderField(ContentNode $from, string $key): string
    {
        $field = ContentField::query()->where('content_type', 'article')->where('key', $key)->firstOrFail();

        $values = app(ContentValueReader::class)->load([$from])[$from->id];

        return app(FieldRenderer::class)->render($field, $values[$key], $from, $this->type, $values);
    }

    public function test_a_visible_node_renders_its_card(): void
    {
        $node = $this->article('One');
        $this->theme();

        $html = $this->render($node, null, '/articles/one');

        $this->assertSame('[One|/articles/one<body=Body of One>]', $html);
        $this->assertStringNotContainsString('hidden', $html);
    }

    public function test_the_card_cascade_of_the_target_type_and_node(): void
    {
        $one = $this->article('One');
        $two = $this->article('Two');
        $this->theme(['article/preview' => 'TYPE', "article/preview--{$two->id}" => 'NODE']);

        $this->assertSame('TYPE', $this->render($one, null, '/a'));
        $this->assertSame('NODE', $this->render($two, null, '/b'));
    }

    public function test_the_card_gets_the_list_fields_and_not_the_page_only_ones(): void
    {
        $node = $this->article('One');
        $this->theme();

        $html = $this->render($node, null, '/a');

        $this->assertStringContainsString('<body=Body of One>', $html);
        $this->assertStringNotContainsString('secret', $html);
    }

    public function test_a_kind_without_list_fields_uses_its_page_fields(): void
    {
        $page = ContentType::create(['key' => 'doc', 'name' => 'Doc', 'kind' => 'page']);
        ContentField::create(['content_type' => 'doc', 'key' => 'text', 'name' => 'Text', 'type' => 'string']);
        $page->update(['settings' => ['public' => ['page_fields' => ['text']]]]);
        $node = app(ContentNodeCreator::class)->create($page, ['title' => 'About', 'text' => 'About text'], slug: 'about', authorId: $this->authorId());
        $this->theme();

        $this->assertSame('[About|/about<text=About text>]', $this->render($node, null, '/about'));
    }

    public function test_a_node_that_is_not_visible_or_missing_renders_nothing(): void
    {
        $nodes = [
            $this->article('Off', ['is_active' => false]),
            $this->article('Soon', ['published_at' => now()->addDay()]),
            $this->article('Gone', ['published_at' => now()->subDays(2), 'unpublished_at' => now()->subDay()]),
        ];
        $deleted = $this->article('Deleted');
        $deleted->delete();
        $this->theme();

        foreach ([...$nodes, $deleted] as $node) {
            $this->assertSame('', $this->render($node), $node->slug);
        }

        $this->assertSame('', $this->render('0190a1b2-0000-7000-8000-000000000000'));
        $this->assertSame('', $this->render('not-a-uuid'));
    }

    public function test_an_invisible_target_gives_no_item_in_the_field(): void
    {
        $from = $this->article('From');
        $off = $this->article('Off', ['is_active' => false]);
        $this->link($from, $off);
        $this->theme();

        $this->assertSame('', $this->renderRelated($from));
    }

    private function block(): ContentNode
    {
        $block = ContentType::create(['key' => 'banner', 'name' => 'Banner', 'kind' => 'block']);
        ContentField::create(['content_type' => 'banner', 'key' => 'text', 'name' => 'Text', 'type' => 'string']);
        $block->update(['settings' => ['public' => ['page_fields' => ['text']]]]);

        return app(ContentNodeCreator::class)->create($block, ['title' => 'Footer', 'text' => 'Footer text'], slug: 'block-footer', authorId: $this->authorId());
    }

    public function test_a_block_renders_its_own_template(): void
    {
        $node = $this->block();
        $this->theme(['blocks/block-banner--footer' => 'OWN:@fields($values)']);

        $html = $this->render($node);

        $this->assertSame('OWN:<text=Footer text>', $html);
        $this->assertSame($html, app(BlockRenderer::class)->render('block-footer'));
    }

    public function test_a_block_without_its_own_template_uses_the_generic_one(): void
    {
        $node = $this->block();
        $this->theme();

        $this->assertSame('{block:<text=Footer text>}', $this->render($node));
    }

    public function test_a_block_uses_the_template_of_the_owner_type_and_gets_the_owner(): void
    {
        $block = $this->block();
        $owner = $this->article('Owner');
        $this->theme(['article/block-banner' => 'OWNER:{{ $owner->slug }}:@fields($values)']);

        $this->assertSame('OWNER:owner:<text=Footer text>', $this->render($block, $owner));
    }

    public function test_a_block_of_another_owner_type_keeps_the_shared_template(): void
    {
        $block = $this->block();
        $owner = $this->article('Owner');
        $this->theme(['other/block-banner' => 'OTHER']);

        $this->assertSame('{block:<text=Footer text>}', $this->render($block, $owner));
    }

    public function test_the_owner_templates_come_before_the_shared_ones_and_the_slug_one_is_the_most_specific(): void
    {
        $block = $this->block();
        $owner = $this->article('Owner');
        $this->theme([
            'blocks/block-banner--footer' => 'SHARED-SLUG',
            'article/block-banner' => 'OWNER-TYPE',
        ]);

        $this->assertSame('OWNER-TYPE', $this->render($block, $owner));

        $this->makeTheme('default', [
            'fields/field' => '<{{ $key }}={{ implode(",", $items) }}>',
            'blocks/block-banner--footer' => 'SHARED-SLUG',
            'article/block-banner' => 'OWNER-TYPE',
            'article/block-banner--footer' => 'OWNER-SLUG',
        ]);

        $this->assertSame('OWNER-SLUG', $this->render($block, $owner));
    }

    public function test_the_owner_of_a_block_inside_a_card_is_the_node_of_that_card(): void
    {
        $block = $this->block();
        ContentType::create(['key' => 'note', 'name' => 'Note']);
        ContentField::create(['content_type' => 'note', 'key' => 'related', 'name' => 'Related', 'type' => 'node_ref', 'is_multiple' => true, 'settings' => ['content_type' => 'banner']]);
        $note = ContentType::query()->where('key', 'note')->firstOrFail();
        $note->update(['settings' => ['public' => ['page_fields' => ['related'], 'list_fields' => ['related']]]]);
        $card = app(ContentNodeCreator::class)->create($note, ['title' => 'Card'], slug: 'card', authorId: $this->authorId());
        app(ContentValueWriter::class)->write($card, ['related' => [$block->uuid]]);
        $page = $this->article('Page');
        $this->theme(['note/block-banner' => 'NOTE-OWNER:{{ $owner->slug }}', 'article/block-banner' => 'ARTICLE-OWNER']);

        $this->assertSame('[Card|/c(Footer:NOTE-OWNER:card)]', $this->render($card, $page, '/c'));
    }

    public function test_a_card_ignores_the_owner_type(): void
    {
        $target = $this->article('Target');
        $owner = $this->article('Owner');
        $this->theme(['article/block-article' => 'NOT-A-CARD']);

        $this->assertSame('[Target|/t<body=Body of Target>]', $this->render($target, $owner, '/t'));
    }

    public function test_the_template_of_the_target_type_wins_over_the_generic_one(): void
    {
        $node = $this->article('One');
        $this->theme(['article/field-string--body' => 'TYPE']);

        $this->assertStringContainsString('TYPE', $this->render($node, null, '/a'));
    }

    public function test_a_node_does_not_render_itself(): void
    {
        $node = $this->article('One');
        $this->link($node, $node);
        $this->theme();

        // Rendered as the page of the node itself, the self-reference has no content.
        $this->assertSame('', $this->render($node, $node));
        // Rendered for another page, the card shows once and its own reference is cut.
        $this->assertSame('[One|/a<body=Body of One>(One:)]', $this->render($node, null, '/a'));
    }

    public function test_a_cycle_through_a_card_is_rendered_once(): void
    {
        $a = $this->article('A');
        $b = $this->article('B');
        $this->link($a, $b);
        $this->link($b, $a);
        $this->theme();

        $this->assertSame('[B|/b<body=Body of B>(A:)]', $this->render($b, $a, '/b'));
    }

    public function test_a_cycle_through_a_block_is_rendered_once(): void
    {
        $block = $this->block();
        ContentField::create(['content_type' => 'banner', 'key' => 'related', 'name' => 'Related', 'type' => 'node_ref', 'settings' => ['content_type' => 'article']]);
        ContentField::create(['content_type' => 'article', 'key' => 'banner', 'name' => 'Banner', 'type' => 'node_ref', 'settings' => ['content_type' => 'banner']]);
        $block->contentType->update(['settings' => ['public' => ['page_fields' => ['text', 'related']]]]);
        $this->type->update(['settings' => ['public' => ['page_fields' => ['body', 'banner'], 'list_fields' => ['body']]]]);
        $node = $this->article('One');
        app(ContentValueWriter::class)->write($node, ['banner' => $block->uuid]);
        app(ContentValueWriter::class)->write($block, ['related' => $node->uuid]);
        $this->theme(['blocks/block' => '{block:@fields($values)}']);

        $html = $this->renderField($node, 'banner');

        $this->assertSame(1, substr_count($html, 'Footer text'));
        $this->assertStringContainsString('(One:)', $html);
    }

    public function test_the_chain_stops_after_three_levels(): void
    {
        [$a, $b, $c, $d, $e] = array_map(fn (string $title) => $this->article($title), ['A', 'B', 'C', 'D', 'E']);
        $this->link($a, $b);
        $this->link($b, $c);
        $this->link($c, $d);
        $this->link($d, $e);
        $this->theme();

        $html = $this->render($b, $a, '/b');

        $this->assertStringContainsString('Body of B', $html);
        $this->assertStringContainsString('Body of C', $html);
        $this->assertStringContainsString('Body of D', $html);
        $this->assertStringContainsString('(E:)', $html);
        $this->assertStringNotContainsString('Body of E', $html);
        $this->assertSame(3, NodeContentRenderer::MAX_DEPTH);
    }

    public function test_content_does_not_count_a_view(): void
    {
        config(['content.views.enabled' => true]);
        $node = $this->article('One');
        $this->theme();

        $before = (int) ContentNode::query()->whereKey($node->id)->value('views');
        $this->render($node, null, '/a');

        $this->assertSame($before, (int) ContentNode::query()->whereKey($node->id)->value('views'));
    }

    public function test_the_reading_language_is_not_substituted(): void
    {
        $node = $this->article('One');
        $this->theme();

        app(AppSettings::class)->set('content.multilingual', true);
        AppLanguage::factory()->active()->create(['code' => 'uk']);
        $this->app->instance(PresentContext::class, new PresentContext(app(ContentValueReader::class), 'uk'));
        $this->app->forgetInstance(NodeContentRenderer::class);

        $this->assertStringNotContainsString('Body of One', $this->render($node, null, '/a'));
    }

    public function test_the_chain_is_clean_after_a_failing_template(): void
    {
        $a = $this->article('A');
        $b = $this->article('B');
        $this->theme(['article/preview' => '@if ($node->slug === "a")@php throw new RuntimeException("boom"); @endphp@endif<card>']);

        try {
            $this->render($a, $b, '/a');
            $this->fail('The template should have thrown.');
        } catch (\Throwable) {
            // The failure is expected.
        }

        // Neither `a` nor the seeded `b` stayed on the chain.
        $this->assertSame('<card>', $this->render($b, null, '/b'));
        $this->assertSame('<card>', $this->render($b, $a, '/b'));
    }

    public function test_a_node_ref_template_receives_the_content_in_its_items(): void
    {
        $from = $this->article('From');
        $one = $this->article('One');
        $this->link($from, $one);
        $this->theme();

        $this->assertSame('(One:[One|/articles/one<body=Body of One>])', $this->renderRelated($from));
    }
}
