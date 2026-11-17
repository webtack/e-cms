<?php

namespace Webtack\Ecms\Tests\Feature\Admin;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Webtack\Ecms\Content\ContentLanguages;
use Webtack\Ecms\Content\TranslationSaver;
use Webtack\Ecms\Models\ContentNode;
use Webtack\Ecms\Tests\Feature\Content\Concerns\BuildsArticleType;
use Webtack\Ecms\Tests\Fixtures\User;
use Webtack\Ecms\Tests\TestCase;

/**
 * The Translations column and the "Without translation in" filter of the node list.
 */
class ContentNodeIndexTranslationTest extends TestCase
{
    use BuildsArticleType, RefreshDatabase;

    private const URL = '/admin/content-types/article/nodes';

    private ContentNode $a;

    private ContentNode $b;

    private ContentNode $c;

    protected function setUp(): void
    {
        parent::setUp();

        $this->enableLanguages('en', 'pl');
        $this->a = $this->makeArticle(['title' => 'A'], 'a');
        $this->b = $this->makeArticle(['title' => 'B'], 'b');
        $this->c = $this->makeArticle(['title' => 'C'], 'c');
        app(TranslationSaver::class)->save($this->a, 'en', ['title' => 'A en']);
        app(TranslationSaver::class)->save($this->a, 'pl', ['title' => 'A pl']);
        app(TranslationSaver::class)->save($this->b, 'en', ['title' => 'B en']);
    }

    private function admin(): static
    {
        return $this->actingAs(User::factory()->create());
    }

    /** @return array<string, array<int, string>> uuid => translations of the rows */
    private function translations(string $query = ''): array
    {
        $result = [];
        $this->admin()->get(self::URL.$query)->assertInertia(function (Assert $page) use (&$result) {
            $page->where('table.rows', function ($rows) use (&$result) {
                $result = collect($rows)->mapWithKeys(fn ($row) => [$row['slug'] => $row['translations'] ?? 'absent'])->all();

                return true;
            });
        });

        return $result;
    }

    public function test_the_column_lists_the_translated_languages_of_each_node(): void
    {
        $this->admin()->get(self::URL)->assertInertia(fn (Assert $page) => $page
            ->where('columns', fn ($columns) => collect($columns)->last() === ['key' => 'translations', 'name' => 'Translations', 'type' => 'text', 'sortable' => false, 'service' => true])
            ->where('translationLanguages', fn ($languages) => collect($languages)->pluck('code')->all() === ['en', 'pl']));

        $this->assertSame(['a' => ['en', 'pl'], 'b' => ['en'], 'c' => []], $this->translations());
    }

    public function test_the_filter_keeps_nodes_without_a_translation_in_the_language(): void
    {
        $this->assertEqualsCanonicalizing(['b', 'c'], array_keys($this->translations('?filters[0][c]=translations&filters[0][o]=eq&filters[0][v]=pl')));
        $this->assertSame(['c'], array_keys($this->translations('?filters[0][c]=translations&filters[0][o]=eq&filters[0][v]=en')));
    }

    public function test_the_filter_combines_with_search_and_reports_the_total(): void
    {
        $this->admin()->get(self::URL.'?filters[0][c]=translations&filters[0][o]=eq&filters[0][v]=pl&q=B')->assertInertia(fn (Assert $page) => $page
            ->where('table.meta.total', 1)
            ->where('table.query.filters.0.value', 'pl'));
    }

    public function test_an_unknown_language_in_the_filter_is_ignored(): void
    {
        $this->assertCount(3, $this->translations('?filters[0][c]=translations&filters[0][o]=eq&filters[0][v]=de'));
    }

    public function test_a_single_language_site_gets_the_unchanged_payload(): void
    {
        app(ContentLanguages::class)->setMultilingual(false);

        $this->admin()->get(self::URL.'?filters[0][c]=translations&filters[0][o]=eq&filters[0][v]=pl')->assertInertia(fn (Assert $page) => $page
            ->missing('translationLanguages')
            ->where('table.meta.total', 3)
            ->where('columns', fn ($columns) => ! collect($columns)->pluck('key')->contains('translations')));
        $this->assertSame(['a' => 'absent', 'b' => 'absent', 'c' => 'absent'], $this->translations());
    }

    public function test_the_query_count_does_not_grow_with_the_page(): void
    {
        $count = function (): int {
            DB::flushQueryLog();
            DB::enableQueryLog();
            $this->admin()->get(self::URL)->assertOk();

            return count(DB::getQueryLog());
        };

        $before = $count();
        foreach (range(1, 6) as $i) {
            app(TranslationSaver::class)->save($this->makeArticle(['title' => "N{$i}"], "n{$i}"), 'en', ['title' => "E{$i}"]);
        }

        $this->assertSame($before, $count());
    }
}
