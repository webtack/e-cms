<?php

namespace Webtack\Ecms\Tests\Feature\Taxonomy;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Webtack\Ecms\Files\Owners\TermOwner;
use Webtack\Ecms\Models\Term;
use Webtack\Ecms\Models\TermTranslation;
use Webtack\Ecms\Tests\TestCase;

class TermOwnerTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_owner_type_is_registered(): void
    {
        $this->assertSame(TermOwner::class, config('files.owners.taxonomy_term'));
    }

    public function test_a_term_is_described_by_its_default_name_with_a_link_to_its_form(): void
    {
        $named = Term::factory()->create();
        TermTranslation::factory()->create(['term_uuid' => $named->uuid, 'lang' => 'default', 'name' => 'Куртки']);
        TermTranslation::factory()->create(['term_uuid' => $named->uuid, 'lang' => 'en', 'name' => 'Jackets']);
        $unnamed = Term::factory()->create(['slug' => 'no-name']);

        $described = (new TermOwner)->describe([$named->id, $unnamed->id, 9999]);

        $this->assertSame([
            $named->id => ['label' => 'Куртки', 'url' => "/admin/taxonomy/{$named->vocabulary_key}/terms/{$named->uuid}/edit"],
            $unnamed->id => ['label' => 'no-name', 'url' => "/admin/taxonomy/{$unnamed->vocabulary_key}/terms/{$unnamed->uuid}/edit"],
        ], $described);
        $this->assertSame([], (new TermOwner)->describe([]));
    }

    public function test_many_terms_are_described_in_a_fixed_number_of_queries(): void
    {
        $ids = Term::factory()->count(4)->create()->pluck('id')->all();

        DB::enableQueryLog();
        (new TermOwner)->describe($ids);
        $queries = count(DB::getQueryLog());
        DB::disableQueryLog();

        $this->assertSame(2, $queries);
    }
}
