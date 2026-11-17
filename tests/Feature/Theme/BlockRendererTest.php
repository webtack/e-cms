<?php

namespace Webtack\Ecms\Tests\Feature\Theme;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Webtack\Ecms\Content\ContentNodeCreator;
use Webtack\Ecms\Models\ContentField;
use Webtack\Ecms\Models\ContentNode;
use Webtack\Ecms\Models\ContentType;
use Webtack\Ecms\Tests\Feature\Theme\Concerns\UsesFixtureThemes;
use Webtack\Ecms\Tests\TestCase;
use Webtack\Ecms\Theme\BlockRenderer;

class BlockRendererTest extends TestCase
{
    use RefreshDatabase, UsesFixtureThemes;

    private ContentType $type;

    protected function setUp(): void
    {
        parent::setUp();

        $this->type = ContentType::create(['key' => 'banner', 'name' => 'Banner', 'kind' => 'block']);
        ContentField::create(['content_type' => 'banner', 'key' => 'caption', 'name' => 'Caption', 'type' => 'string']);
    }

    /** @param array<string, mixed> $attributes */
    private function block(string $slug = 'block-footer', array $attributes = []): ContentNode
    {
        return app(ContentNodeCreator::class)->create(
            $this->type,
            ['title' => 'Footer', 'caption' => 'Hello block'],
            slug: $slug,
            authorId: $this->authorId(),
            publishedAt: $attributes['published_at'] ?? null,
            isActive: $attributes['is_active'] ?? true,
            unpublishedAt: $attributes['unpublished_at'] ?? null,
        );
    }

    private function render(string $slug = 'block-footer'): string
    {
        return app(BlockRenderer::class)->render($slug);
    }

    public function test_the_candidates_drop_the_block_prefix_of_the_slug(): void
    {
        $this->assertSame(
            ['blocks/block-banner--footer', 'blocks/block-banner', 'blocks/block'],
            app(BlockRenderer::class)->candidates('banner', 'block-footer'),
        );
    }

    public function test_the_candidates_of_a_block_with_an_owner_start_with_the_owner_directory(): void
    {
        $this->assertSame(
            ['article/block-banner--footer', 'article/block-banner', 'blocks/block-banner--footer', 'blocks/block-banner', 'blocks/block'],
            app(BlockRenderer::class)->candidates('banner', 'block-footer', 'article'),
        );
    }

    public function test_a_placed_block_ignores_the_owner_templates_and_has_no_owner(): void
    {
        $this->block();
        $this->makeTheme('default', [
            'blocks/block' => '{{ var_export($owner, true) }}',
            'article/block-banner' => 'OWNER-TYPE',
        ]);
        $this->useTheme('default');

        $this->assertSame('NULL', $this->render());
    }

    public function test_a_visible_block_renders_with_its_node_type_and_values(): void
    {
        $this->block();
        $this->makeTheme('default', ['blocks/block' => '{{ $node->slug }}|{{ $type->key }}|{{ $values["caption"] }}']);
        $this->useTheme('default');

        $this->assertSame('block-footer|banner|Hello block', $this->render());
    }

    public function test_the_template_of_the_block_wins_over_the_template_of_its_type(): void
    {
        $this->block();
        $this->block('block-header');
        $this->makeTheme('default', ['blocks/block' => 'GENERIC', 'blocks/block-banner' => 'TYPE', 'blocks/block-banner--footer' => 'FOOTER']);
        $this->useTheme('default');

        $this->assertSame('FOOTER', $this->render('block-footer'));
        $this->assertSame('TYPE', $this->render('block-header'));
    }

    public function test_the_generic_template_is_used_when_nothing_is_more_specific(): void
    {
        $this->block();
        $this->makeTheme('default', ['blocks/block' => 'GENERIC']);
        $this->useTheme('default');

        $this->assertSame('GENERIC', $this->render());
    }

    public function test_the_fields_of_a_block_are_printed_by_the_field_directives(): void
    {
        $this->block();
        $this->makeTheme('default', ['blocks/block' => '@fields($values)', 'fields/field' => '[{{ $key }}={{ implode(",", $items) }}]']);
        $this->useTheme('default');

        $this->assertSame('[caption=Hello block]', $this->render());
    }

    public function test_a_block_that_is_not_visible_renders_nothing(): void
    {
        $this->block('block-off', ['is_active' => false]);
        $this->block('block-soon', ['published_at' => now()->addDay()]);
        $this->block('block-gone', ['published_at' => now()->subDays(2), 'unpublished_at' => now()->subDay()]);
        $this->makeTheme('default', ['blocks/block' => 'SHOWN']);
        $this->useTheme('default');

        foreach (['block-off', 'block-soon', 'block-gone'] as $slug) {
            $this->assertSame('', $this->render($slug), $slug);
        }
    }

    public function test_a_block_that_does_not_exist_renders_nothing(): void
    {
        $this->makeTheme('default', ['blocks/block' => 'SHOWN']);
        $this->useTheme('default');

        $this->assertSame('', $this->render('block-missing'));
    }

    public function test_a_node_of_another_kind_is_not_a_block(): void
    {
        $page = ContentType::create(['key' => 'static', 'name' => 'Static', 'kind' => 'page']);
        app(ContentNodeCreator::class)->create($page, ['title' => 'About'], slug: 'about', authorId: $this->authorId());
        $this->makeTheme('default', ['blocks/block' => 'SHOWN']);
        $this->useTheme('default');

        $this->assertSame('', $this->render('about'));
    }

    public function test_the_directive_places_a_block_and_a_missing_one_does_not_break_the_template(): void
    {
        $this->block();
        $this->makeTheme('default', ['blocks/block' => 'B', 'probe' => '[@block("block-footer")][@block("block-missing")]']);
        $manager = $this->useTheme('default');

        $this->assertSame('[B][]', $manager->render(['probe']));
    }

    public function test_a_block_has_no_public_address_even_when_placed(): void
    {
        $this->block();

        $this->get('/block-footer')->assertNotFound();
    }
}
