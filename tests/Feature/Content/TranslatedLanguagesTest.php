<?php

namespace Webtack\Ecms\Tests\Feature\Content;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Webtack\Ecms\Content\TranslatedLanguages;
use Webtack\Ecms\Content\TranslationSaver;
use Webtack\Ecms\Content\ValueOwner;
use Webtack\Ecms\Models\ContentNode;
use Webtack\Ecms\Models\ContentType;
use Webtack\Ecms\Tests\Feature\Content\Concerns\BuildsArticleType;
use Webtack\Ecms\Tests\TestCase;

class TranslatedLanguagesTest extends TestCase
{
    use BuildsArticleType, RefreshDatabase;

    public function test_languages_are_listed_per_node(): void
    {
        $this->enableLanguages('en', 'pl');
        $a = $this->makeArticle(['title' => 'A']);
        $b = $this->makeArticle(['title' => 'B'], 'b');
        $c = $this->makeArticle(['title' => 'C'], 'c');
        app(TranslationSaver::class)->save($a, 'pl', ['title' => 'Apl']);
        app(TranslationSaver::class)->save($a, 'en', ['body' => 'Body']);
        app(TranslationSaver::class)->save($b, 'en', ['title' => 'Ben']);

        $this->assertSame(
            [$a->id => ['en', 'pl'], $b->id => ['en'], $c->id => []],
            app(TranslatedLanguages::class)->forNodes([$a->id, $b->id, $c->id]),
        );
    }

    public function test_a_page_of_nodes_costs_a_constant_number_of_queries(): void
    {
        $this->enableLanguages('en');
        $ids = [];
        foreach (range(1, 5) as $i) {
            $node = $this->makeArticle(['title' => "N{$i}"], "n{$i}");
            app(TranslationSaver::class)->save($node, 'en', ['title' => "E{$i}"]);
            $ids[] = $node->id;
        }

        DB::enableQueryLog();
        app(TranslatedLanguages::class)->forNodes($ids);

        $this->assertCount(1, DB::getQueryLog());
    }

    public function test_the_filter_keeps_nodes_without_a_value_in_the_language(): void
    {
        $this->enableLanguages('en', 'pl');
        $a = $this->makeArticle(['title' => 'A']);
        $b = $this->makeArticle(['title' => 'B'], 'b');
        app(TranslationSaver::class)->save($a, 'pl', ['title' => 'Apl']);
        app(TranslationSaver::class)->save($b, 'en', ['title' => 'Ben']);

        $service = app(TranslatedLanguages::class);

        $this->assertSame([$b->id], $service->withoutTranslation(ContentNode::query(), 'pl')->pluck('id')->all());
        $this->assertSame([$a->id], $service->withoutTranslation(ContentNode::query(), 'en')->pluck('id')->all());
    }

    public function test_type_languages_cover_every_tab_and_ignore_nodes(): void
    {
        $this->enableLanguages('en', 'pl');
        $type = ContentType::create(['key' => 'news', 'name' => 'News']);
        $saver = app(TranslationSaver::class);
        $saver->save(ValueOwner::forType($type), 'en', ['meta_title' => 'SEO']);
        $this->makeArticle(['title' => 'A']);
        $saver->save(ContentNode::query()->firstOrFail(), 'pl', ['title' => 'Apl']);

        $this->assertSame(['en'], app(TranslatedLanguages::class)->forType($type));
    }
}
