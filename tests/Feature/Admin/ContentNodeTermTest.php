<?php

namespace Webtack\Ecms\Tests\Feature\Admin;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Webtack\Ecms\Content\ContentNodeCreator;
use Webtack\Ecms\Content\ContentValueReader;
use Webtack\Ecms\Models\ContentField;
use Webtack\Ecms\Models\ContentNode;
use Webtack\Ecms\Models\ContentType;
use Webtack\Ecms\Models\Term;
use Webtack\Ecms\Models\Vocabulary;
use Webtack\Ecms\Taxonomy\TermWriter;
use Webtack\Ecms\Tests\Fixtures\User;
use Webtack\Ecms\Tests\TestCase;

class ContentNodeTermTest extends TestCase
{
    use RefreshDatabase;

    private Vocabulary $categories;

    private Term $clothes;

    private Term $jackets;

    private Term $shoes;

    protected function setUp(): void
    {
        parent::setUp();

        ContentType::create(['key' => 'news', 'name' => 'News']);
        $this->categories = Vocabulary::factory()->create(['key' => 'categories', 'name' => 'Categories']);
        $this->clothes = $this->term('Clothes', weight: 0);
        $this->jackets = $this->term('Jackets', $this->clothes);
        $this->shoes = $this->term('Shoes', weight: 1);
        $this->termField('category');
        $this->termField('tags', multiple: true, maxItems: 2);
    }

    private function admin(): static
    {
        return $this->actingAs(User::factory()->create());
    }

    private function term(string $name, ?Term $parent = null, int $weight = 0, ?Vocabulary $vocabulary = null): Term
    {
        return app(TermWriter::class)->create($vocabulary ?? $this->categories, [
            'parent_uuid' => $parent?->uuid, 'weight' => $weight, 'translations' => ['default' => ['name' => $name]],
        ]);
    }

    private function termField(string $key, bool $multiple = false, bool $required = false, int $maxItems = 0): ContentField
    {
        return ContentField::create([
            'content_type' => 'news', 'key' => $key, 'name' => ucfirst($key), 'type' => 'term', 'is_multiple' => $multiple,
            'is_required' => $required, 'max_items' => $maxItems, 'settings' => ['vocabulary' => 'categories'], 'weight' => 10,
        ]);
    }

    /**
     * @param  array<string, mixed>  $values
     */
    private function node(array $values): ContentNode
    {
        return app(ContentNodeCreator::class)->create(ContentType::findByKey('news'), ['title' => 'Post', ...$values], null, authorId: $this->authorId());
    }

    /** @return array<string, mixed> */
    private function values(ContentNode $node): array
    {
        return app(ContentValueReader::class)->load([$node])[$node->id];
    }

    public function test_the_form_gets_the_terms_of_the_vocabulary_in_tree_order_with_depth(): void
    {
        $this->admin()->get('/admin/content-types/news/nodes/create')->assertInertia(fn (Assert $page) => $page
            ->where('fields', fn ($fields) => collect($fields)->firstWhere('key', 'category')['settings']['terms'] === [
                ['uuid' => $this->clothes->uuid, 'name' => 'Clothes', 'depth' => 1],
                ['uuid' => $this->jackets->uuid, 'name' => 'Jackets', 'depth' => 2],
                ['uuid' => $this->shoes->uuid, 'name' => 'Shoes', 'depth' => 1],
            ]));
    }

    public function test_a_single_term_is_saved_and_marked_when_the_form_reopens(): void
    {
        $this->admin()->post('/admin/content-types/news/nodes', ['slug' => 'one', 'values' => ['title' => 'One', 'category' => $this->jackets->uuid]])
            ->assertSessionHasNoErrors();

        $node = ContentNode::findBySlug('one');
        $this->assertSame($this->jackets->uuid, $this->values($node)['category']);

        $this->admin()->get("/admin/content-types/news/nodes/{$node->uuid}/edit")
            ->assertInertia(fn (Assert $page) => $page->where('values.category', $this->jackets->uuid));
    }

    public function test_several_terms_are_saved_in_the_chosen_order(): void
    {
        $this->admin()->post('/admin/content-types/news/nodes', ['slug' => 'two', 'values' => ['title' => 'Two', 'tags' => [$this->shoes->uuid, $this->clothes->uuid]]])
            ->assertSessionHasNoErrors();

        $this->assertSame([$this->shoes->uuid, $this->clothes->uuid], $this->values(ContentNode::findBySlug('two'))['tags']);
    }

    public function test_the_item_limit_is_enforced(): void
    {
        $this->admin()->post('/admin/content-types/news/nodes', ['slug' => 'three', 'values' => [
            'title' => 'Three', 'tags' => [$this->shoes->uuid, $this->clothes->uuid, $this->jackets->uuid],
        ]])->assertSessionHasErrors();

        $this->assertNull(ContentNode::findBySlug('three'));
    }

    public function test_clearing_an_optional_single_term_removes_the_value(): void
    {
        $node = $this->node(['category' => $this->shoes->uuid]);

        $this->admin()->put("/admin/content-types/news/nodes/{$node->uuid}", [
            'slug' => $node->slug, 'published_at' => $node->published_at->format('Y-m-d\TH:i'), 'values' => ['title' => 'Post', 'category' => ''],
        ])->assertSessionHasNoErrors();

        $this->assertNull($this->values($node->fresh())['category'] ?? null);
        $this->assertSame(0, DB::table('content_values_string')->where('field_key', 'category')->count());
    }

    public function test_a_term_of_another_vocabulary_or_a_missing_term_is_rejected_next_to_the_field(): void
    {
        $foreign = $this->term('Foreign', vocabulary: Vocabulary::factory()->create(['key' => 'tags']));

        $this->admin()->post('/admin/content-types/news/nodes', ['slug' => 'bad', 'values' => ['title' => 'Bad', 'category' => $foreign->uuid]])
            ->assertSessionHasErrors('values.category');
        $this->admin()->post('/admin/content-types/news/nodes', ['slug' => 'bad', 'values' => ['title' => 'Bad', 'category' => (string) Str::uuid7()]])
            ->assertSessionHasErrors('values.category');
        $this->assertNull(ContentNode::findBySlug('bad'));
    }

    public function test_a_required_term_field_needs_a_choice(): void
    {
        $this->termField('topic', required: true);

        $this->admin()->post('/admin/content-types/news/nodes', ['slug' => 'req', 'values' => ['title' => 'Req', 'topic' => '']])
            ->assertSessionHasErrors('values.topic');
    }

    public function test_the_list_shows_term_names_and_does_not_sort_the_column(): void
    {
        $this->node(['category' => $this->jackets->uuid, 'tags' => [$this->shoes->uuid, $this->clothes->uuid]]);
        $type = ContentType::findByKey('news');
        $type->update(['settings' => ['list' => ['visible' => ['title', 'category', 'tags']]]]);

        $this->admin()->get('/admin/content-types/news/nodes')->assertInertia(fn (Assert $page) => $page
            ->where('table.rows.0.category', 'Jackets')
            ->where('table.rows.0.tags', ['Shoes', 'Clothes'])
            ->where('columns', fn ($columns) => collect($columns)->firstWhere('key', 'category')['sortable'] === false
                && collect($columns)->firstWhere('key', 'tags')['sortable'] === false
                && collect($columns)->firstWhere('key', 'title')['sortable'] === true));
    }
}
