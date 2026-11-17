<?php

namespace Webtack\Ecms\Tests\Feature\Taxonomy;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Webtack\Ecms\Content\ContentNodeQuery;
use Webtack\Ecms\Content\NodeQueryParams;
use Webtack\Ecms\Models\ContentField;
use Webtack\Ecms\Models\ContentNode;
use Webtack\Ecms\Models\ContentType;
use Webtack\Ecms\Models\Term;
use Webtack\Ecms\Models\Vocabulary;
use Webtack\Ecms\Tests\Feature\Content\Concerns\BuildsArticleType;
use Webtack\Ecms\Tests\TestCase;

class TermFilterTest extends TestCase
{
    use BuildsArticleType, RefreshDatabase;

    private ContentType $type;

    private Term $clothes;

    private Term $jackets;

    private Term $winter;

    private Term $electronics;

    protected function setUp(): void
    {
        parent::setUp();

        $this->type = $this->articleType();
        $categories = Vocabulary::factory()->create(['key' => 'categories']);
        $this->clothes = Term::factory()->in($categories)->create();
        $this->jackets = Term::factory()->under($this->clothes)->create();
        $this->winter = Term::factory()->under($this->jackets)->create();
        $this->electronics = Term::factory()->in($categories)->create();

        ContentField::create(['content_type' => 'article', 'key' => 'category', 'name' => 'Category', 'type' => 'term', 'settings' => ['vocabulary' => 'categories']]);
        ContentField::create(['content_type' => 'article', 'key' => 'topics', 'name' => 'Topics', 'type' => 'term', 'is_multiple' => true, 'settings' => ['vocabulary' => 'categories']]);
    }

    /**
     * @param  array<int, array<string, mixed>>  $filters
     * @return list<string> Slugs of the matching nodes, in query order.
     */
    private function slugs(array $filters): array
    {
        $page = app(ContentNodeQuery::class)->paginate($this->type, new NodeQueryParams(fields: ['title'], filters: $filters));

        return collect($page->items())->map(fn (ContentNode $node) => $node->slug)->all();
    }

    /** @param array<string, mixed> $values */
    private function node(string $slug, array $values): void
    {
        $this->makeArticle(['title' => $slug, ...$values], $slug);
    }

    public function test_a_record_in_a_subcategory_matches_its_ancestor(): void
    {
        $this->node('winter-coat', ['category' => $this->winter->uuid]);

        $this->assertSame(['winter-coat'], $this->slugs([['field' => 'category', 'operator' => 'eq', 'value' => $this->clothes->uuid]]));
    }

    public function test_a_record_directly_in_the_term_matches(): void
    {
        $this->node('direct', ['category' => $this->clothes->uuid]);

        $this->assertSame(['direct'], $this->slugs([['field' => 'category', 'value' => $this->clothes->uuid]]));
    }

    public function test_a_record_in_another_branch_does_not_match(): void
    {
        $this->node('phone', ['category' => $this->electronics->uuid]);
        $this->node('parent-of-filter', ['category' => $this->clothes->uuid]);

        $this->assertSame(['parent-of-filter'], $this->slugs([['field' => 'category', 'operator' => 'eq', 'value' => $this->clothes->uuid]]));
        $this->assertSame([], $this->slugs([['field' => 'category', 'operator' => 'eq', 'value' => $this->jackets->uuid]]));
    }

    public function test_in_returns_each_record_once(): void
    {
        $this->node('coat', ['category' => $this->winter->uuid]);
        $this->node('phone', ['category' => $this->electronics->uuid]);

        $slugs = $this->slugs([['field' => 'category', 'operator' => 'in', 'value' => [$this->clothes->uuid, $this->jackets->uuid, $this->electronics->uuid]]]);

        $this->assertEqualsCanonicalizing(['coat', 'phone'], $slugs);
    }

    public function test_a_multiple_field_matches_when_any_value_is_a_descendant(): void
    {
        $this->node('mixed', ['topics' => [$this->electronics->uuid, $this->winter->uuid]]);

        $this->assertSame(['mixed'], $this->slugs([['field' => 'topics', 'value' => $this->clothes->uuid]]));
    }

    public function test_an_unknown_term_is_a_filter_value_error(): void
    {
        try {
            $this->slugs([['field' => 'category', 'value' => '0190a1b2-7c3e-7f0a-8b1c-2d3e4f5a6b7c']]);
            $this->fail('An unknown term was accepted.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('filters.0.value', $e->errors());
        }
    }

    public function test_an_unsupported_operator_is_rejected(): void
    {
        try {
            $this->slugs([['field' => 'category', 'operator' => 'gt', 'value' => $this->clothes->uuid]]);
            $this->fail('The operator gt was accepted.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('filters.0.operator', $e->errors());
        }
    }

    public function test_it_combines_with_another_filter_by_and(): void
    {
        $this->node('cheap-coat', ['category' => $this->winter->uuid, 'visits' => 5]);
        $this->node('popular-coat', ['category' => $this->winter->uuid, 'visits' => 500]);

        $slugs = $this->slugs([
            ['field' => 'category', 'value' => $this->clothes->uuid],
            ['field' => 'visits', 'operator' => 'gte', 'value' => 100],
        ]);

        $this->assertSame(['popular-coat'], $slugs);
    }
}
