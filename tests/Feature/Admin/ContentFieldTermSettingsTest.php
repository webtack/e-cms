<?php

namespace Webtack\Ecms\Tests\Feature\Admin;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Webtack\Ecms\Models\ContentField;
use Webtack\Ecms\Models\ContentType;
use Webtack\Ecms\Models\Term;
use Webtack\Ecms\Models\Vocabulary;
use Webtack\Ecms\Taxonomy\TermWriter;
use Webtack\Ecms\Tests\Feature\Content\Concerns\BuildsArticleType;
use Webtack\Ecms\Tests\Fixtures\User;
use Webtack\Ecms\Tests\TestCase;

class ContentFieldTermSettingsTest extends TestCase
{
    use BuildsArticleType, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        ContentType::create(['key' => 'news', 'name' => 'News']);
        Vocabulary::factory()->create(['key' => 'tags', 'name' => 'Tags']);
        Vocabulary::factory()->create(['key' => 'categories', 'name' => 'Categories']);
    }

    private function admin(): static
    {
        return $this->actingAs(User::factory()->create());
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return [
            'key' => 'category', 'name' => 'Category', 'type' => 'term', 'is_multiple' => false, 'is_required' => false,
            'is_translatable' => false, 'weight' => 10, 'settings' => ['vocabulary' => 'categories'], ...$overrides,
        ];
    }

    private function field(): ContentField
    {
        return ContentField::query()->where('content_type', 'news')->where('key', 'category')->firstOrFail();
    }

    public function test_the_forms_offer_the_term_type_and_the_vocabularies_by_name(): void
    {
        $this->admin()->get('/admin/content-types/news/fields/create')->assertInertia(fn (Assert $page) => $page
            ->where('typeOptions', fn ($options) => in_array('term', $options->toArray(), true))
            ->where('vocabularies', [['key' => 'categories', 'name' => 'Categories'], ['key' => 'tags', 'name' => 'Tags']]));

        ContentField::create(['content_type' => 'news', 'key' => 'category', 'name' => 'Category', 'type' => 'term', 'settings' => ['vocabulary' => 'tags']]);
        $this->admin()->get('/admin/content-types/news/fields/category/edit')->assertInertia(fn (Assert $page) => $page
            ->where('field.settings', ['vocabulary' => 'tags'])
            ->has('vocabularies', 2));
    }

    public function test_a_term_field_is_created_with_its_vocabulary(): void
    {
        $this->admin()->post('/admin/content-types/news/fields', $this->payload())
            ->assertRedirect('/admin/content-types/news/fields')->assertSessionHasNoErrors();

        $this->assertSame(['vocabulary' => 'categories'], $this->field()->settings);
        $this->assertSame('term', $this->field()->type);
    }

    public function test_a_term_field_without_or_with_an_unknown_vocabulary_is_rejected_next_to_the_select(): void
    {
        $this->admin()->post('/admin/content-types/news/fields', $this->payload(['settings' => null]))
            ->assertSessionHasErrors('settings.vocabulary');
        $this->admin()->post('/admin/content-types/news/fields', $this->payload(['settings' => ['vocabulary' => '']]))
            ->assertSessionHasErrors('settings.vocabulary');
        $this->admin()->post('/admin/content-types/news/fields', $this->payload(['settings' => ['vocabulary' => 'missing']]))
            ->assertSessionHasErrors('settings.vocabulary');
        $this->admin()->post('/admin/content-types/news/fields', $this->payload(['settings' => ['vocabulary' => 'tags', 'mimes' => ['image/png']]]))
            ->assertSessionHasErrors('settings');

        $this->assertSame(0, ContentField::query()->where('key', 'category')->count());
    }

    public function test_other_types_reject_the_vocabulary_setting(): void
    {
        $this->admin()->post('/admin/content-types/news/fields', $this->payload(['key' => 'note', 'type' => 'string']))
            ->assertSessionHasErrors('settings');
        $this->admin()->post('/admin/content-types/news/fields', $this->payload(['key' => 'cover', 'type' => 'image']))
            ->assertSessionHasErrors('settings');
    }

    public function test_the_vocabulary_can_change_while_the_field_has_no_values(): void
    {
        ContentField::create(['content_type' => 'news', 'key' => 'category', 'name' => 'Category', 'type' => 'term', 'settings' => ['vocabulary' => 'tags']]);

        $this->admin()->put('/admin/content-types/news/fields/category', $this->payload(['settings' => ['vocabulary' => 'categories']]))
            ->assertSessionHasNoErrors();

        $this->assertSame(['vocabulary' => 'categories'], $this->field()->settings);
    }

    public function test_the_vocabulary_of_a_field_with_values_is_locked(): void
    {
        $this->articleType();
        ContentField::create(['content_type' => 'article', 'key' => 'category', 'name' => 'Category', 'type' => 'term', 'settings' => ['vocabulary' => 'tags']]);
        $term = app(TermWriter::class)->create(Vocabulary::findByKey('tags'), ['translations' => ['default' => ['name' => 'News']]]);
        $this->makeArticle(['title' => 'One', 'category' => $term->uuid], 'one');

        $this->admin()->get('/admin/content-types/article/fields/category/edit')
            ->assertInertia(fn (Assert $page) => $page->where('field.has_values', true));

        $this->admin()->put('/admin/content-types/article/fields/category', $this->payload(['settings' => ['vocabulary' => 'categories']]))
            ->assertSessionHasErrors('settings.vocabulary');

        $this->assertSame(['vocabulary' => 'tags'], ContentField::query()->where('content_type', 'article')->where('key', 'category')->firstOrFail()->settings);
        $this->assertSame(1, Term::query()->count());
    }
}
