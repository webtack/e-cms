<?php

namespace Webtack\Ecms\Tests\Feature\Admin;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Webtack\Ecms\Models\ContentField;
use Webtack\Ecms\Models\ContentType;
use Webtack\Ecms\Models\Term;
use Webtack\Ecms\Models\Vocabulary;
use Webtack\Ecms\Tests\Fixtures\User;
use Webtack\Ecms\Tests\TestCase;

class VocabularyAdminTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): static
    {
        return $this->actingAs(User::factory()->create());
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get('/admin/taxonomy')->assertRedirect('/login');
        $this->get('/admin/taxonomy/create')->assertRedirect('/login');
    }

    public function test_index_lists_vocabularies_with_term_counts_ordered_by_name(): void
    {
        $second = Vocabulary::factory()->create(['key' => 'tags', 'name' => 'Tags', 'settings' => ['max_depth' => 2]]);
        $first = Vocabulary::factory()->create(['key' => 'categories', 'name' => 'Categories']);
        Term::factory()->in($first)->count(2)->create();

        $this->admin()->get('/admin/taxonomy')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Taxonomy/Index')
                ->has('table.rows', 2)
                ->where('table.rows.0.key', 'categories')
                ->where('table.rows.0.terms_count', 2)
                ->where('table.rows.0.max_depth', null)
                ->where('table.rows.1.key', $second->key)
                ->where('table.rows.1.max_depth', 2));
    }

    public function test_index_searches_name_key_and_description(): void
    {
        Vocabulary::factory()->create(['key' => 'categories', 'name' => 'Rubrics']);
        Vocabulary::factory()->create(['key' => 'tags', 'name' => 'Labels', 'description' => 'free-form keywords']);

        $this->admin()->get('/admin/taxonomy?q=keywords')->assertInertia(fn (Assert $page) => $page
            ->has('table.rows', 1)->where('table.rows.0.key', 'tags'));
        $this->admin()->get('/admin/taxonomy?q=categ')->assertInertia(fn (Assert $page) => $page
            ->has('table.rows', 1)->where('table.rows.0.key', 'categories'));
    }

    public function test_store_creates_a_vocabulary_and_opens_its_terms(): void
    {
        $this->admin()->post('/admin/taxonomy', [
            'key' => 'categories', 'name' => 'Categories', 'description' => 'Site rubrics', 'max_depth' => '3',
        ])->assertRedirect('/admin/taxonomy/categories/terms')->assertSessionHas('success');

        $vocabulary = Vocabulary::findByKey('categories');
        $this->assertSame('Site rubrics', $vocabulary->description);
        $this->assertSame(3, $vocabulary->maxDepth());
    }

    public function test_store_without_depth_means_no_limit(): void
    {
        $this->admin()->post('/admin/taxonomy', ['key' => 'tags', 'name' => 'Tags', 'max_depth' => ''])->assertSessionHasNoErrors();

        $this->assertNull(Vocabulary::findByKey('tags')->maxDepth());
    }

    public function test_store_rejects_a_taken_key_a_bad_key_and_a_bad_depth(): void
    {
        Vocabulary::factory()->create(['key' => 'categories']);

        $this->admin()->post('/admin/taxonomy', ['key' => 'categories', 'name' => 'X'])->assertSessionHasErrors('key');
        $this->admin()->post('/admin/taxonomy', ['key' => 'Bad Key', 'name' => 'X'])->assertSessionHasErrors('key');
        $this->admin()->post('/admin/taxonomy', ['key' => 'tags', 'name' => 'X', 'max_depth' => '0'])->assertSessionHasErrors('max_depth');
        $this->admin()->post('/admin/taxonomy', ['key' => 'tags', 'name' => ''])->assertSessionHasErrors('name');
        $this->assertNull(Vocabulary::findByKey('tags'));
    }

    public function test_edit_shows_the_vocabulary(): void
    {
        Vocabulary::factory()->create(['key' => 'categories', 'name' => 'Categories', 'settings' => ['max_depth' => 4]]);

        $this->admin()->get('/admin/taxonomy/categories/edit')->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Taxonomy/Edit')
            ->where('vocabulary.key', 'categories')
            ->where('vocabulary.max_depth', 4));
    }

    public function test_unknown_key_answers_404(): void
    {
        $this->admin()->get('/admin/taxonomy/missing/edit')->assertNotFound();
        $this->admin()->put('/admin/taxonomy/missing', ['name' => 'X'])->assertNotFound();
        $this->admin()->delete('/admin/taxonomy/missing')->assertNotFound();
    }

    public function test_update_changes_name_but_never_the_key(): void
    {
        Vocabulary::factory()->create(['key' => 'categories', 'name' => 'Categories']);

        $this->admin()->from('/admin/taxonomy/categories')->put('/admin/taxonomy/categories', ['key' => 'other', 'name' => 'Rubrics', 'max_depth' => '5'])
            ->assertRedirect('/admin/taxonomy/categories')->assertSessionHas('success');

        $vocabulary = Vocabulary::findByKey('categories');
        $this->assertSame('Rubrics', $vocabulary->name);
        $this->assertSame(5, $vocabulary->maxDepth());
        $this->assertNull(Vocabulary::findByKey('other'));
    }

    public function test_update_clears_the_depth(): void
    {
        Vocabulary::factory()->create(['key' => 'categories', 'settings' => ['max_depth' => 2]]);

        $this->admin()->put('/admin/taxonomy/categories', ['name' => 'Categories', 'max_depth' => ''])->assertSessionHasNoErrors();

        $this->assertNull(Vocabulary::findByKey('categories')->maxDepth());
    }

    public function test_update_rejects_a_depth_below_the_actual_one(): void
    {
        $vocabulary = Vocabulary::factory()->create(['key' => 'categories']);
        $root = Term::factory()->in($vocabulary)->create();
        $child = Term::factory()->under($root)->create();
        Term::factory()->under($child)->create();

        $this->admin()->put('/admin/taxonomy/categories', ['name' => 'Categories', 'max_depth' => '2'])
            ->assertSessionHasErrors('max_depth');
        $this->assertNull($vocabulary->fresh()->maxDepth());

        $this->admin()->put('/admin/taxonomy/categories', ['name' => 'Categories', 'max_depth' => '3'])->assertSessionHasNoErrors();
        $this->assertSame(3, $vocabulary->fresh()->maxDepth());
    }

    public function test_destroy_deletes_an_empty_unused_vocabulary(): void
    {
        Vocabulary::factory()->create(['key' => 'categories']);

        $this->admin()->delete('/admin/taxonomy/categories')->assertRedirect('/admin/taxonomy')->assertSessionHas('success');

        $this->assertNull(Vocabulary::findByKey('categories'));
    }

    public function test_destroy_keeps_a_vocabulary_with_terms(): void
    {
        $vocabulary = Vocabulary::factory()->create(['key' => 'categories']);
        Term::factory()->in($vocabulary)->create();

        $this->admin()->from('/admin/taxonomy')->delete('/admin/taxonomy/categories')
            ->assertRedirect('/admin/taxonomy')->assertSessionHas('error', 'Cannot delete a vocabulary that has terms.');

        $this->assertNotNull(Vocabulary::findByKey('categories'));
    }

    public function test_destroy_keeps_a_vocabulary_used_by_a_field(): void
    {
        Vocabulary::factory()->create(['key' => 'categories']);
        $type = ContentType::create(['key' => 'article', 'name' => 'Article', 'kind' => 'content']);
        ContentField::create([
            'content_type' => $type->key, 'key' => 'category', 'name' => 'Category', 'type' => 'term',
            'settings' => ['vocabulary' => 'categories'],
        ]);

        $this->admin()->from('/admin/taxonomy')->delete('/admin/taxonomy/categories')
            ->assertSessionHas('error', 'Cannot delete a vocabulary that is used by a field.');

        $this->assertNotNull(Vocabulary::findByKey('categories'));
    }
}
