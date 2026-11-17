<?php

namespace Webtack\Ecms\Tests\Feature\Taxonomy;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Webtack\Ecms\Models\Term;
use Webtack\Ecms\Models\TermTranslation;
use Webtack\Ecms\Models\Vocabulary;
use Webtack\Ecms\Taxonomy\TermTree;
use Webtack\Ecms\Tests\TestCase;

class TermTreeTest extends TestCase
{
    use RefreshDatabase;

    private Vocabulary $vocabulary;

    private Term $clothes;

    private Term $jackets;

    private Term $shoes;

    private Term $winter;

    protected function setUp(): void
    {
        parent::setUp();

        $this->vocabulary = Vocabulary::factory()->create();
        $this->clothes = Term::factory()->in($this->vocabulary)->create(['slug' => 'clothes']);
        $this->jackets = Term::factory()->under($this->clothes)->create(['slug' => 'jackets']);
        $this->shoes = Term::factory()->under($this->clothes)->create(['slug' => 'shoes', 'weight' => 1]);
        $this->winter = Term::factory()->under($this->jackets)->create(['slug' => 'winter']);
    }

    public function test_descendants_on_several_levels(): void
    {
        $uuids = app(TermTree::class)->descendantUuids([$this->clothes->uuid]);

        $this->assertEqualsCanonicalizing(
            [$this->clothes->uuid, $this->jackets->uuid, $this->shoes->uuid, $this->winter->uuid],
            $uuids,
        );
    }

    public function test_nested_input_terms_appear_once(): void
    {
        $uuids = app(TermTree::class)->descendantUuids([$this->clothes->uuid, $this->jackets->uuid]);

        $this->assertCount(4, $uuids);
        $this->assertSame($uuids, array_values(array_unique($uuids)));
    }

    public function test_a_leaf_gives_only_itself(): void
    {
        $this->assertSame([$this->winter->uuid], app(TermTree::class)->descendantUuids([$this->winter->uuid]));
    }

    public function test_the_number_of_queries_equals_the_number_of_levels(): void
    {
        DB::enableQueryLog();
        app(TermTree::class)->descendantUuids([$this->clothes->uuid]);
        $queries = count(DB::getQueryLog());
        DB::disableQueryLog();

        // clothes -> jackets/shoes -> winter -> (nothing): the last query finds no more terms.
        $this->assertSame(3, $queries);
    }

    public function test_damaged_data_with_a_cycle_does_not_loop(): void
    {
        DB::table('taxonomy_terms')->where('uuid', $this->clothes->uuid)->update(['parent_uuid' => $this->winter->uuid]);

        $this->assertCount(4, app(TermTree::class)->descendantUuids([$this->clothes->uuid]));
    }

    public function test_heights(): void
    {
        $tree = app(TermTree::class);

        $this->assertSame(3, $tree->height($this->clothes));
        $this->assertSame(1, $tree->height($this->shoes));
        $this->assertSame(3, $tree->vocabularyHeight($this->vocabulary));
        $this->assertSame(0, $tree->vocabularyHeight(Vocabulary::factory()->create()));
    }

    public function test_ancestors_are_listed_nearest_first(): void
    {
        $this->assertSame(
            [$this->jackets->uuid, $this->clothes->uuid],
            app(TermTree::class)->ancestorUuids($this->winter),
        );
    }

    public function test_the_tree_is_ordered_and_names_have_no_fallback(): void
    {
        TermTranslation::factory()->create(['term_uuid' => $this->clothes->uuid, 'lang' => 'default', 'name' => 'Одяг']);
        TermTranslation::factory()->create(['term_uuid' => $this->clothes->uuid, 'lang' => 'en', 'name' => 'Clothes']);
        TermTranslation::factory()->create(['term_uuid' => $this->jackets->uuid, 'lang' => 'default', 'name' => 'Куртки']);

        $default = app(TermTree::class)->tree($this->vocabulary, 'default');
        $english = app(TermTree::class)->tree($this->vocabulary, 'en');

        $this->assertCount(1, $default);
        $this->assertSame('Одяг', $default[0]['name']);
        $this->assertSame(['jackets', 'shoes'], array_column($default[0]['children'], 'slug'));
        $this->assertSame('winter', $default[0]['children'][0]['children'][0]['slug']);
        $this->assertSame('Clothes', $english[0]['name']);
        $this->assertNull($english[0]['children'][0]['name']);
    }

    public function test_the_fallback_mode_fills_a_blank_name_and_description_independently(): void
    {
        TermTranslation::factory()->create(['term_uuid' => $this->clothes->uuid, 'lang' => 'default', 'name' => 'Одяг', 'description' => 'Весь одяг']);
        TermTranslation::factory()->create(['term_uuid' => $this->clothes->uuid, 'lang' => 'en', 'name' => null, 'description' => 'All clothes']);
        TermTranslation::factory()->create(['term_uuid' => $this->jackets->uuid, 'lang' => 'default', 'name' => 'Куртки']);

        $english = app(TermTree::class)->tree($this->vocabulary, 'en', fallback: true);

        $this->assertSame('Одяг', $english[0]['name']);
        $this->assertSame('All clothes', $english[0]['description']);
        $this->assertSame('Куртки', $english[0]['children'][0]['name']);
    }
}
