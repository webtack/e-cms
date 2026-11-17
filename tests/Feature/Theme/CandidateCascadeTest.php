<?php

namespace Webtack\Ecms\Tests\Feature\Theme;

use Webtack\Ecms\Models\ContentNode;
use Webtack\Ecms\Models\ContentType;
use Webtack\Ecms\Tests\Feature\Theme\Concerns\UsesFixtureThemes;
use Webtack\Ecms\Tests\TestCase;
use Webtack\Ecms\Theme\PreviewRenderer;
use Webtack\Ecms\Theme\ThemeException;

class CandidateCascadeTest extends TestCase
{
    use UsesFixtureThemes;

    public function test_the_more_specific_candidate_wins(): void
    {
        $this->makeTheme('default', ['pages/page' => 'GENERAL', 'pages/page-landing' => 'LANDING']);
        $manager = $this->useTheme('default');

        $this->assertSame('LANDING', $manager->render(['pages/page-landing--5', 'pages/page-landing', 'pages/page']));
    }

    public function test_the_candidate_matters_more_than_the_theme(): void
    {
        $this->makeTheme('default', ['pages/page-landing--5' => 'DEFAULT-5', 'pages/page' => 'DEFAULT']);
        $this->makeTheme('custom', ['pages/page-landing' => 'CUSTOM-LANDING']);
        $manager = $this->useTheme('custom');

        $this->assertSame('DEFAULT-5', $manager->render(['pages/page-landing--5', 'pages/page-landing', 'pages/page']));
    }

    public function test_the_active_theme_wins_for_the_same_candidate(): void
    {
        $this->makeTheme('default', ['pages/page' => 'DEFAULT']);
        $this->makeTheme('custom', ['pages/page' => 'CUSTOM']);
        $manager = $this->useTheme('custom');

        $this->assertSame('CUSTOM', $manager->render(['pages/page']));
    }

    public function test_no_candidate_throws_with_every_tried_name(): void
    {
        $this->makeTheme('default', ['home' => 'HOME']);
        $manager = $this->useTheme('default');

        try {
            $manager->render(['pages/page-a', 'pages/page-b', 'pages/page']);
            $this->fail('A ThemeException was expected.');
        } catch (ThemeException $e) {
            $this->assertStringContainsString('pages/page-a, pages/page-b, pages/page', $e->getMessage());
        }
    }

    public function test_the_debug_comment_lists_candidates_and_the_used_template(): void
    {
        $this->makeTheme('default', ['pages/page' => 'PAGE']);
        $manager = $this->useTheme('default');
        config(['app.debug' => true]);

        $html = $manager->render(['pages/page-landing', 'pages/page']);

        $this->assertStringContainsString('<!-- theme: candidates [pages/page-landing, pages/page] ; used: pages/page -->', $html);
    }

    public function test_there_is_no_comment_without_debug(): void
    {
        $this->makeTheme('default', ['pages/page' => 'PAGE']);
        $manager = $this->useTheme('default');

        $this->assertStringNotContainsString('<!--', $manager->render(['pages/page']));
    }

    public function test_the_comment_cannot_be_closed_early_by_a_candidate(): void
    {
        $this->makeTheme('default', ['pages/page' => 'PAGE']);
        $manager = $this->useTheme('default');
        config(['app.debug' => true]);

        $html = $manager->render(['evil--><script>', 'pages/page']);

        $this->assertStringNotContainsString('--><script>', $html);
    }

    private function card(int $id = 7): array
    {
        $node = new ContentNode(['slug' => 'story']);
        $node->id = $id;
        $node->content_type = 'news';

        return [$node, new ContentType(['key' => 'news', 'slug' => 'news'])];
    }

    public function test_the_preview_candidates_go_from_the_record_to_the_global_card(): void
    {
        [$node] = $this->card();

        $this->assertSame(['news/preview--7', 'news/preview', 'content/preview'], app(PreviewRenderer::class)->candidates($node));
    }

    public function test_a_card_of_one_record_overrides_the_card_of_its_type(): void
    {
        $this->makeTheme('default', ['content/preview' => 'GLOBAL', 'news/preview' => 'NEWS', 'news/preview--7' => 'NEWS-7']);
        $this->useTheme('default');
        [$seven, $type] = $this->card(7);
        [$eight] = $this->card(8);

        $this->assertSame('NEWS-7', app(PreviewRenderer::class)->render($seven, $type, []));
        $this->assertSame('NEWS', app(PreviewRenderer::class)->render($eight, $type, []));
    }

    public function test_the_global_card_is_used_when_the_type_has_none(): void
    {
        $this->makeTheme('default', ['content/preview' => 'GLOBAL {{ $url }}']);
        $this->useTheme('default');
        [$node, $type] = $this->card();

        $this->assertSame('GLOBAL /news/story', app(PreviewRenderer::class)->render($node, $type, []));
    }

    public function test_the_card_url_of_a_type_with_a_slug_of_several_segments(): void
    {
        $this->makeTheme('default', ['content/preview' => '{{ $url }}']);
        $this->useTheme('default');
        [$node] = $this->card();
        $type = new ContentType(['key' => 'news', 'slug' => 'common/institutions']);

        $this->assertSame('/common/institutions/story', app(PreviewRenderer::class)->render($node, $type, []));
    }

    public function test_the_content_preview_hook_can_change_the_variables(): void
    {
        $this->makeTheme('default', ['content/preview' => '{{ $extra }}'], <<<'PHP'
<?php
use Webtack\Ecms\Theme\ThemeHooks;
return function (ThemeHooks $hooks): void {
    $hooks->preprocess('content.preview', function (array &$vars) { $vars['extra'] = 'HOOKED'; });
};
PHP);
        $this->useTheme('default');
        [$node, $type] = $this->card();

        $this->assertSame('HOOKED', app(PreviewRenderer::class)->render($node, $type, []));
    }

    public function test_the_preview_directive_reads_the_type_from_the_template(): void
    {
        $this->makeTheme('default', ['content/preview' => 'CARD', 'list' => '@foreach ($items as $item)@preview($item)@endforeach']);
        $manager = $this->useTheme('default');
        [$node, $type] = $this->card();

        $this->assertSame('CARDCARD', $manager->render(['list'], ['items' => [$node, $node], 'type' => $type]));
    }
}
