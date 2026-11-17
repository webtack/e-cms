<?php

namespace Webtack\Ecms\Tests\Feature\Public;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Webtack\Ecms\Content\ContentNodeCreator;
use Webtack\Ecms\Content\ContentValueWriter;
use Webtack\Ecms\Content\SearchResult;
use Webtack\Ecms\Content\SiteSearch;
use Webtack\Ecms\Content\ValueOwner;
use Webtack\Ecms\Models\ContentField;
use Webtack\Ecms\Models\ContentNode;
use Webtack\Ecms\Models\ContentType;
use Webtack\Ecms\Tests\Feature\Content\Concerns\BuildsArticleType;
use Webtack\Ecms\Tests\TestCase;

class SiteSearchTest extends TestCase
{
    use BuildsArticleType;
    use RefreshDatabase;

    private ContentType $news;

    private ContentType $pages;

    protected function setUp(): void
    {
        parent::setUp();

        $this->news = ContentType::create(['key' => 'news', 'name' => 'News', 'slug' => 'news']);
        ContentField::create(['content_type' => 'news', 'key' => 'body', 'name' => 'Body', 'type' => 'richtext', 'weight' => 10]);
        ContentField::create(['content_type' => 'news', 'key' => 'summary', 'name' => 'Summary', 'type' => 'text', 'weight' => 11]);
        ContentField::create(['content_type' => 'news', 'key' => 'link', 'name' => 'Link', 'type' => 'url', 'weight' => 12]);
        $this->pages = ContentType::create(['key' => 'static', 'name' => 'Static', 'kind' => 'page']);
    }

    /** @param  array<string, mixed>  $values */
    private function record(string $slug, array $values, ?ContentType $type = null, array $attributes = []): ContentNode
    {
        return app(ContentNodeCreator::class)->create(
            $type ?? $this->news,
            $values,
            slug: $slug,
            authorId: $this->authorId(),
            publishedAt: $attributes['published_at'] ?? null,
            isActive: $attributes['is_active'] ?? true,
            unpublishedAt: $attributes['unpublished_at'] ?? null,
        );
    }

    /** @return list<string> Slugs of the results of one page, in order. */
    private function urls(string $term, string $lang = 'default', int $perPage = 15): array
    {
        return array_map(fn ($result) => $result->url, app(SiteSearch::class)->search($term, $lang, $perPage)->items());
    }

    public function test_a_page_is_found_by_its_title(): void
    {
        $this->record('lamp-museum', ['title' => 'Lamp museum'], $this->pages);
        $this->record('other', ['title' => 'Other'], $this->pages);

        $this->assertSame(['/lamp-museum'], $this->urls('lamp'));
    }

    public function test_a_record_is_found_by_a_text_or_richtext_field_and_links_to_its_type(): void
    {
        $this->record('first', ['title' => 'First', 'body' => '<p>A <b>lamp</b> story</p>']);
        $this->record('second', ['title' => 'Second', 'summary' => 'About a lamp']);
        $this->record('third', ['title' => 'Third', 'body' => '<p>Nothing</p>']);

        $this->assertEqualsCanonicalizing(['/news/first', '/news/second'], $this->urls('lamp'));
    }

    public function test_the_match_ignores_case(): void
    {
        $this->record('lamps', ['title' => 'LAMP museum']);

        $this->assertSame(['/news/lamps'], $this->urls('lamp'));
        $this->assertSame(['/news/lamps'], $this->urls('MUSEUM'));
    }

    public function test_only_visible_nodes_are_returned(): void
    {
        $this->record('visible', ['title' => 'Lamp visible']);
        $this->record('disabled', ['title' => 'Lamp disabled'], attributes: ['is_active' => false]);
        $this->record('scheduled', ['title' => 'Lamp scheduled'], attributes: ['published_at' => now()->addDay()]);
        $this->record('expired', ['title' => 'Lamp expired'], attributes: ['published_at' => now()->subDays(2), 'unpublished_at' => now()->subDay()]);

        $this->assertSame(['/news/visible'], $this->urls('lamp'));
    }

    public function test_blocks_and_records_of_a_type_without_a_slug_are_not_returned(): void
    {
        $block = ContentType::create(['key' => 'hero', 'name' => 'Hero', 'kind' => 'block']);
        $hidden = ContentType::create(['key' => 'internal', 'name' => 'Internal']);
        $this->record('block-abcdefgh', ['title' => 'Lamp block'], $block);
        $this->record('internal-one', ['title' => 'Lamp internal'], $hidden);
        $this->record('shown', ['title' => 'Lamp shown']);

        $this->assertSame(['/news/shown'], $this->urls('lamp'));
        $this->assertSame(1, app(SiteSearch::class)->search('lamp', 'default', 15)->total());
    }

    public function test_fields_that_are_not_text_are_not_searched(): void
    {
        $this->record('linked', ['title' => 'Plain', 'link' => 'https://example.com/lamp']);

        $this->assertSame([], $this->urls('lamp'));
    }

    public function test_the_values_of_the_type_itself_are_not_searched(): void
    {
        app(ContentValueWriter::class)->write(ValueOwner::forType($this->news), ['section_title' => 'Lamp section']);
        $this->record('plain', ['title' => 'Plain']);

        $this->assertSame([], $this->urls('lamp'));
    }

    public function test_the_search_looks_through_every_language_of_a_node(): void
    {
        $this->enableLanguages('en');
        $translated = $this->record('translated', ['title' => 'Lamp base']);
        app(ContentValueWriter::class)->write(ValueOwner::forNode($translated), ['title' => 'Lantern'], 'en');
        $this->record('untranslated', ['title' => 'Lamp only base']);

        // The reading language never limits the match: the base text is found from the English site.
        $this->assertEqualsCanonicalizing(['/news/translated', '/news/untranslated'], $this->urls('lamp'));
        $this->assertEqualsCanonicalizing(['/news/translated', '/news/untranslated'], $this->urls('lamp', 'en'));
        // A word that occurs only in the English value is found from the base site too.
        $this->assertSame(['/news/translated'], $this->urls('lantern'));
        $this->assertSame(['/news/translated'], $this->urls('lantern', 'en'));
    }

    public function test_the_title_is_shown_in_the_reading_language_with_a_fallback(): void
    {
        $this->enableLanguages('en');
        $translated = $this->record('translated', ['title' => 'Lamp base']);
        app(ContentValueWriter::class)->write(ValueOwner::forNode($translated), ['title' => 'Lantern'], 'en');
        $this->record('untranslated', ['title' => 'Lamp only base']);

        $titles = collect(app(SiteSearch::class)->search('lamp', 'en', 15)->items())->mapWithKeys(fn ($r) => [$r->url => $r->title]);

        $this->assertSame('Lantern', $titles['/news/translated']);
        $this->assertSame('Lamp only base', $titles['/news/untranslated']);
    }

    public function test_a_node_that_matches_in_several_languages_appears_once_and_the_excerpt_comes_from_the_match(): void
    {
        $this->enableLanguages('en');
        ContentField::query()->where('content_type', 'news')->where('key', 'body')->update(['is_translatable' => true]);
        $node = $this->record('both', ['title' => 'Story', 'body' => '<p>About the lamp and the light</p>']);
        app(ContentValueWriter::class)->write(ValueOwner::forNode($node), ['body' => '<p>Completely different words</p>'], 'en');

        $page = app(SiteSearch::class)->search('lamp', 'en', 15);

        $this->assertSame(1, $page->total());
        // The English body has no match, so the excerpt is taken from the base-language text that does.
        $this->assertStringContainsString('lamp', $page->items()[0]->excerpt);
        $this->assertStringNotContainsString('Completely', $page->items()[0]->excerpt);
        $this->assertSame(1, app(SiteSearch::class)->search('different', 'default', 15)->total());
    }

    public function test_percent_and_underscore_are_literal(): void
    {
        $this->record('percent', ['title' => 'Sale 100% today']);
        $this->record('number', ['title' => 'Sale 1000 today']);
        $this->record('underscore', ['title' => 'file_name here']);
        $this->record('letter', ['title' => 'fileXname here']);

        $this->assertSame(['/news/percent'], $this->urls('100%'));
        $this->assertSame(['/news/underscore'], $this->urls('file_name'));
    }

    public function test_a_node_with_several_matching_values_appears_once(): void
    {
        $this->record('many', ['title' => 'Lamp', 'body' => '<p>lamp</p>', 'summary' => 'lamp lamp']);

        $page = app(SiteSearch::class)->search('lamp', 'default', 15);

        $this->assertSame(1, $page->total());
        $this->assertCount(1, $page->items());
    }

    public function test_the_results_are_newest_first_and_paginated(): void
    {
        foreach ([3, 2, 1] as $days) {
            $this->record("n{$days}", ['title' => "Lamp {$days}"], attributes: ['published_at' => now()->subDays($days)]);
        }

        $first = app(SiteSearch::class)->search('lamp', 'default', 2, 1);
        $second = app(SiteSearch::class)->search('lamp', 'default', 2, 2);

        $this->assertSame(3, $first->total());
        $this->assertSame(['/news/n1', '/news/n2'], array_map(fn ($r) => $r->url, $first->items()));
        $this->assertSame(['/news/n3'], array_map(fn ($r) => $r->url, $second->items()));
    }

    public function test_the_excerpt_is_plain_text_and_not_longer_than_the_meta_description_limit(): void
    {
        config(['content.meta_description_max' => 40]);
        $this->record('long', ['title' => 'Lamp', 'body' => '<p>The <b>lamp</b> museum collects very old lamps from many countries</p>']);

        $result = app(SiteSearch::class)->search('lamp', 'default', 15)->items()[0];

        $this->assertStringNotContainsString('<', $result->excerpt);
        $this->assertLessThanOrEqual(40, mb_strlen($result->excerpt));
        $this->assertStringStartsWith('The lamp museum', $result->excerpt);
        $this->assertStringEndsWith('…', $result->excerpt);
    }

    public function test_the_excerpt_cut_never_breaks_a_multibyte_letter(): void
    {
        // "є" ends with the byte 0x94, which is also the last byte of "—": a byte-wise trim would corrupt it.
        config(['content.meta_description_max' => 12]);
        $this->record('cut', ['title' => 'Lamp', 'summary' => 'святкує річчя']);

        $excerpt = app(SiteSearch::class)->search('lamp', 'default', 15)->items()[0]->excerpt;

        $this->assertTrue(mb_check_encoding($excerpt, 'UTF-8'));
        $this->assertSame('святкує…', $excerpt);
    }

    private function firstResult(string $term): SearchResult
    {
        return app(SiteSearch::class)->search($term, 'default', 15)->items()[0];
    }

    public function test_the_title_parts_flag_every_occurrence_and_join_back_to_the_title(): void
    {
        $this->record('museum', ['title' => 'Lamp museum of the lamp', 'summary' => 'Old lamps of Lamp street']);

        $result = $this->firstResult('lamp');

        $this->assertSame([
            ['text' => 'Lamp', 'match' => true],
            ['text' => ' museum of the ', 'match' => false],
            ['text' => 'lamp', 'match' => true],
        ], $result->titleParts);
        $this->assertSame($result->title, implode('', array_column($result->titleParts, 'text')));
        $this->assertSame($result->excerpt, implode('', array_column($result->excerptParts, 'text')));
        $this->assertSame(['lamp', 'Lamp'], array_column(array_filter($result->excerptParts, fn ($p) => $p['match']), 'text'));
    }

    public function test_a_title_without_the_term_is_one_unflagged_part_and_no_excerpt_gives_no_parts(): void
    {
        $this->record('plain', ['title' => 'Museum', 'body' => '<p>The lamp</p>']);
        $this->record('no-text', ['title' => 'Only lamp title'], attributes: ['published_at' => now()->subDay()]);

        $results = app(SiteSearch::class)->search('lamp', 'default', 15)->items();

        $this->assertSame([['text' => 'Museum', 'match' => false]], $results[0]->titleParts);
        $this->assertNull($results[1]->excerpt);
        $this->assertSame([], $results[1]->excerptParts);
    }

    public function test_the_excerpt_is_cut_around_a_match_deep_in_a_long_text(): void
    {
        config(['content.meta_description_max' => 60]);
        $this->record('deep', ['title' => 'Deep', 'body' => '<p>'.str_repeat('alpha beta gamma ', 30).'the lamp is here '.str_repeat('delta epsilon zeta ', 30).'</p>']);

        $excerpt = $this->firstResult('lamp')->excerpt;

        $this->assertStringContainsString('the lamp is here', $excerpt);
        $this->assertStringStartsWith('…', $excerpt);
        $this->assertStringEndsWith('…', $excerpt);
        $this->assertLessThanOrEqual(60, mb_strlen($excerpt));
    }

    public function test_the_excerpt_comes_from_the_first_field_that_contains_the_term(): void
    {
        $this->record('second', ['title' => 'Second', 'body' => '<p>Nothing relevant here</p>', 'summary' => 'A short lamp summary']);

        $this->assertSame('A short lamp summary', $this->firstResult('lamp')->excerpt);
    }

    public function test_a_match_at_the_start_has_no_leading_ellipsis_and_a_match_at_the_end_has_no_trailing_one(): void
    {
        config(['content.meta_description_max' => 50]);
        $this->record('start', ['title' => 'Start', 'summary' => 'lamp '.str_repeat('word ', 40)]);

        $start = $this->firstResult('lamp')->excerpt;
        $this->assertStringStartsWith('lamp word', $start);
        $this->assertStringEndsWith('…', $start);

        ContentNode::query()->delete();
        $this->record('end', ['title' => 'End', 'summary' => str_repeat('word ', 40).'lamp']);

        $end = $this->firstResult('lamp')->excerpt;
        $this->assertStringStartsWith('…', $end);
        $this->assertStringEndsWith(' lamp', $end);
        $this->assertLessThanOrEqual(50, mb_strlen($end));
    }

    public function test_a_title_only_match_keeps_the_start_of_the_text_as_the_excerpt(): void
    {
        config(['content.meta_description_max' => 40]);
        $this->record('title-only', ['title' => 'Lamp', 'summary' => 'The beginning of a long text without the word that matched']);

        $result = $this->firstResult('lamp');

        $this->assertStringStartsWith('The beginning of a long text', $result->excerpt);
        $this->assertSame([], array_filter($result->excerptParts, fn ($p) => $p['match']));
    }

    public function test_the_excerpt_window_never_breaks_a_multibyte_letter_and_keeps_the_limit(): void
    {
        config(['content.meta_description_max' => 40]);
        $this->record('uk', ['title' => 'Uk', 'summary' => str_repeat('слово ', 20).'лампа світить '.str_repeat('ще слово ', 20)]);

        $excerpt = $this->firstResult('лампа')->excerpt;

        $this->assertTrue(mb_check_encoding($excerpt, 'UTF-8'));
        $this->assertStringContainsString('лампа', $excerpt);
        $this->assertLessThanOrEqual(40, mb_strlen($excerpt));
    }

    public function test_a_term_near_the_limit_stays_inside_the_window(): void
    {
        config(['content.meta_description_max' => 30]);
        $term = 'extraordinarilylongterm';
        $this->record('long-term', ['title' => 'Long', 'summary' => str_repeat('word ', 20).$term.' '.str_repeat('word ', 20)]);

        $excerpt = $this->firstResult($term)->excerpt;

        $this->assertStringContainsString($term, $excerpt);
        $this->assertLessThanOrEqual(30, mb_strlen($excerpt));
    }

    public function test_a_record_has_the_section_title_of_its_type_and_a_page_has_none(): void
    {
        app(ContentValueWriter::class)->write(ValueOwner::forType($this->news), ['section_title' => 'Latest news']);
        $this->record('story', ['title' => 'Lamp story']);
        $this->record('about-lamps', ['title' => 'About lamps'], $this->pages);

        $byUrl = collect(app(SiteSearch::class)->search('lamp', 'default', 15)->items())->keyBy('url');

        $this->assertSame('Latest news', $byUrl['/news/story']->sectionTitle);
        $this->assertSame('content', $byUrl['/news/story']->kind);
        $this->assertNull($byUrl['/about-lamps']->sectionTitle);
        $this->assertSame('page', $byUrl['/about-lamps']->kind);
    }

    public function test_a_result_has_the_publication_date(): void
    {
        $this->record('dated', ['title' => 'Lamp dated'], attributes: ['published_at' => now()->subDays(3)->startOfMinute()]);

        $result = app(SiteSearch::class)->search('lamp', 'default', 15)->items()[0];

        $this->assertTrue($result->publishedAt->equalTo(now()->subDays(3)->startOfMinute()));
    }

    public function test_the_section_title_falls_back_to_the_type_name(): void
    {
        $this->record('story', ['title' => 'Lamp story']);

        $this->assertSame('News', app(SiteSearch::class)->search('lamp', 'default', 15)->items()[0]->sectionTitle);
    }

    public function test_the_number_of_queries_does_not_depend_on_the_number_of_results(): void
    {
        $this->record('one', ['title' => 'Lamp one', 'body' => '<p>text</p>']);
        $this->record('two', ['title' => 'Lamp two', 'body' => '<p>text</p>']);
        $this->record('page-one', ['title' => 'Lamp page'], $this->pages);

        // The section titles are cached per request (scoped service), so warm them up and compare like with like.
        app(SiteSearch::class)->search('lamp', 'default', 15);
        $few = $this->countQueries(fn () => app(SiteSearch::class)->search('lamp', 'default', 15));

        foreach (range(1, 8) as $i) {
            $this->record("more-{$i}", ['title' => "Lamp more {$i}", 'body' => '<p>text</p>']);
        }

        $many = $this->countQueries(fn () => app(SiteSearch::class)->search('lamp', 'default', 15));

        $this->assertSame($few, $many);
    }

    private function countQueries(callable $callback): int
    {
        DB::flushQueryLog();
        DB::enableQueryLog();
        $callback();
        $count = count(DB::getQueryLog());
        DB::disableQueryLog();

        return $count;
    }
}
