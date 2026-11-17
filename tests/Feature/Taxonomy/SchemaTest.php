<?php

namespace Webtack\Ecms\Tests\Feature\Taxonomy;

use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Webtack\Ecms\Models\Term;
use Webtack\Ecms\Models\Vocabulary;
use Webtack\Ecms\Tests\TestCase;

class SchemaTest extends TestCase
{
    use RefreshDatabase;

    /** @param array<string, mixed> $extra */
    private function insertTerm(string $vocabularyKey, string $slug, array $extra = []): string
    {
        $uuid = fake()->uuid();
        DB::table('taxonomy_terms')->insert(['uuid' => $uuid, 'vocabulary_key' => $vocabularyKey, 'slug' => $slug, ...$extra]);

        return $uuid;
    }

    public function test_a_duplicate_slug_in_a_vocabulary_is_rejected_by_the_database(): void
    {
        $vocabulary = Vocabulary::factory()->create();
        $this->insertTerm($vocabulary->key, 'jackets');

        $this->expectException(QueryException::class);
        $this->insertTerm($vocabulary->key, 'Jackets');
    }

    public function test_the_same_slug_in_different_vocabularies_is_allowed(): void
    {
        $this->insertTerm(Vocabulary::factory()->create()->key, 'news');
        $this->insertTerm(Vocabulary::factory()->create()->key, 'news');

        $this->assertSame(2, Term::query()->where('slug', 'news')->count());
    }

    public function test_a_parent_with_children_cannot_be_deleted_in_the_database(): void
    {
        $vocabulary = Vocabulary::factory()->create();
        $parent = $this->insertTerm($vocabulary->key, 'parent');
        $this->insertTerm($vocabulary->key, 'child', ['parent_uuid' => $parent]);

        $this->expectException(QueryException::class);
        DB::table('taxonomy_terms')->where('uuid', $parent)->delete();
    }

    public function test_a_vocabulary_with_terms_cannot_be_deleted_in_the_database(): void
    {
        $vocabulary = Vocabulary::factory()->create();
        $this->insertTerm($vocabulary->key, 'a');

        $this->expectException(QueryException::class);
        DB::table('taxonomy_vocabularies')->where('key', $vocabulary->key)->delete();
    }

    public function test_translations_are_unique_per_language_and_cascade_with_the_term(): void
    {
        $uuid = $this->insertTerm(Vocabulary::factory()->create()->key, 'a');
        DB::table('taxonomy_term_translations')->insert(['term_uuid' => $uuid, 'lang' => 'default', 'name' => 'A']);

        try {
            DB::table('taxonomy_term_translations')->insert(['term_uuid' => $uuid, 'lang' => 'default', 'name' => 'B']);
            $this->fail('A second translation in one language was accepted.');
        } catch (QueryException) {
            // expected
        }

        DB::table('taxonomy_terms')->where('uuid', $uuid)->delete();

        $this->assertSame(0, DB::table('taxonomy_term_translations')->count());
    }
}
