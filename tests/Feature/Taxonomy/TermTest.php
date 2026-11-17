<?php

namespace Webtack\Ecms\Tests\Feature\Taxonomy;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Webtack\Ecms\Models\ContentField;
use Webtack\Ecms\Models\Term;
use Webtack\Ecms\Models\TermTranslation;
use Webtack\Ecms\Models\Vocabulary;
use Webtack\Ecms\Tests\Feature\Content\Concerns\BuildsArticleType;
use Webtack\Ecms\Tests\TestCase;

class TermTest extends TestCase
{
    use BuildsArticleType, RefreshDatabase;

    private Vocabulary $vocabulary;

    protected function setUp(): void
    {
        parent::setUp();

        $this->vocabulary = Vocabulary::factory()->create();
    }

    private function term(string $slug, ?Term $parent = null, int $weight = 0): Term
    {
        return Term::create([
            'vocabulary_key' => $this->vocabulary->key,
            'parent_uuid' => $parent?->uuid,
            'slug' => $slug,
            'weight' => $weight,
        ]);
    }

    /** @return array<string, array<int, string>> */
    private function errorsOf(callable $action): array
    {
        try {
            $action();
        } catch (ValidationException $e) {
            return $e->errors();
        }

        $this->fail('No validation error was thrown.');
    }

    public function test_a_root_and_a_child_are_related_by_uuid(): void
    {
        $clothes = $this->term('clothes');
        $jackets = $this->term('jackets', $clothes);

        $this->assertTrue(Str::isUuid($clothes->uuid));
        $this->assertNull($clothes->parent);
        $this->assertSame($clothes->uuid, $jackets->parent->uuid);
        $this->assertSame([$jackets->uuid], $clothes->children->pluck('uuid')->all());
        $this->assertSame($this->vocabulary->key, $jackets->vocabulary->key);
        $this->assertSame(2, $this->vocabulary->terms()->count());
    }

    public function test_the_parent_must_be_in_the_same_vocabulary(): void
    {
        $foreign = Term::factory()->create();

        $errors = $this->errorsOf(fn () => $this->term('x', $foreign));

        $this->assertArrayHasKey('parent_uuid', $errors);
    }

    public function test_a_missing_parent_is_rejected(): void
    {
        $errors = $this->errorsOf(fn () => Term::create([
            'vocabulary_key' => $this->vocabulary->key, 'slug' => 'x', 'parent_uuid' => (string) Str::uuid7(),
        ]));

        $this->assertArrayHasKey('parent_uuid', $errors);
    }

    public function test_a_term_cannot_move_under_its_descendant_or_itself(): void
    {
        $clothes = $this->term('clothes');
        $jackets = $this->term('jackets', $clothes);
        $winter = $this->term('winter', $jackets);

        $this->assertArrayHasKey('parent_uuid', $this->errorsOf(fn () => $clothes->update(['parent_uuid' => $winter->uuid])));
        $this->assertArrayHasKey('parent_uuid', $this->errorsOf(fn () => $clothes->update(['parent_uuid' => $clothes->uuid])));
    }

    public function test_the_slug_is_unique_in_a_vocabulary_but_not_across_vocabularies(): void
    {
        $this->term('jackets');

        $this->assertArrayHasKey('slug', $this->errorsOf(fn () => $this->term('jackets')));
        $this->assertArrayHasKey('slug', $this->errorsOf(fn () => $this->term('Jackets')));

        $other = Vocabulary::factory()->create();
        Term::create(['vocabulary_key' => $other->key, 'slug' => 'jackets']);

        $this->assertSame(2, Term::query()->where('slug', 'jackets')->count());
    }

    public function test_the_slug_format_is_checked(): void
    {
        foreach (['', 'with space', '-x', 'x--y', str_repeat('a', 191)] as $slug) {
            $this->assertArrayHasKey('slug', $this->errorsOf(fn () => $this->term($slug)));
        }
    }

    public function test_the_vocabulary_and_uuid_of_a_term_are_immutable(): void
    {
        $term = $this->term('a');

        $this->assertArrayHasKey('vocabulary_key', $this->errorsOf(fn () => $term->update(['vocabulary_key' => Vocabulary::factory()->create()->key])));
        $this->assertArrayHasKey('uuid', $this->errorsOf(fn () => $term->update(['uuid' => (string) Str::uuid7()])));
    }

    public function test_siblings_are_ordered_by_weight_then_id(): void
    {
        $parent = $this->term('parent');
        $two = $this->term('two', $parent, 2);
        $zero = $this->term('zero', $parent, 0);
        $one = $this->term('one', $parent, 1);
        $oneToo = $this->term('one-too', $parent, 1);

        $this->assertSame(
            [$zero->uuid, $one->uuid, $oneToo->uuid, $two->uuid],
            $parent->children->pluck('uuid')->all(),
        );
    }

    public function test_the_depth_limit_applies_to_new_terms(): void
    {
        $this->vocabulary->update(['settings' => ['max_depth' => 2]]);
        $root = $this->term('root');
        $child = $this->term('child', $root);

        $this->assertArrayHasKey('parent_uuid', $this->errorsOf(fn () => $this->term('grandchild', $child)));
    }

    public function test_a_flat_vocabulary_takes_no_children(): void
    {
        $this->vocabulary->update(['settings' => ['max_depth' => 1]]);
        $root = $this->term('root');

        $this->assertArrayHasKey('parent_uuid', $this->errorsOf(fn () => $this->term('child', $root)));
    }

    public function test_moving_a_subtree_counts_its_whole_depth(): void
    {
        $this->vocabulary->update(['settings' => ['max_depth' => 3]]);
        $a = $this->term('a');
        $b = $this->term('b', $a);
        $branch = $this->term('branch');
        $this->term('leaf', $this->term('mid', $branch));

        // branch -> mid -> leaf is 3 levels; under "b" (level 2) it would reach level 5.
        $this->assertArrayHasKey('parent_uuid', $this->errorsOf(fn () => $branch->update(['parent_uuid' => $b->uuid])));
        $this->assertNull($branch->fresh()->parent_uuid);
    }

    public function test_a_term_with_children_cannot_be_deleted(): void
    {
        $parent = $this->term('parent');
        $this->term('child', $parent);

        $this->assertArrayHasKey('uuid', $this->errorsOf(fn () => $parent->delete()));
        $this->assertNotNull($parent->fresh());
    }

    public function test_a_used_term_cannot_be_deleted(): void
    {
        $term = $this->term('used');
        $this->useTerm($term);

        $this->assertArrayHasKey('uuid', $this->errorsOf(fn () => $term->delete()));
        $this->assertNotNull($term->fresh());
    }

    public function test_a_free_leaf_is_deleted_with_its_translations(): void
    {
        $term = $this->term('leaf');
        TermTranslation::factory()->create(['term_uuid' => $term->uuid, 'lang' => 'default', 'name' => 'Leaf']);

        $term->delete();

        $this->assertNull($term->fresh());
        $this->assertSame(0, TermTranslation::query()->count());
    }

    /** Store the term in a `term` field of an article. */
    private function useTerm(Term $term): void
    {
        $this->articleType();
        ContentField::create(['content_type' => 'article', 'key' => 'category', 'name' => 'Category', 'type' => 'term', 'settings' => ['vocabulary' => $this->vocabulary->key]]);

        $this->makeArticle(['title' => 'Hello', 'category' => $term->uuid]);
    }
}
