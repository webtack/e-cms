<?php

namespace Webtack\Ecms\Tests\Feature\Admin;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Webtack\Ecms\Models\AppLanguage;
use Webtack\Ecms\Models\ContentField;
use Webtack\Ecms\Models\File;
use Webtack\Ecms\Models\Role;
use Webtack\Ecms\Models\Term;
use Webtack\Ecms\Models\TermTranslation;
use Webtack\Ecms\Models\Vocabulary;
use Webtack\Ecms\Taxonomy\TermWriter;
use Webtack\Ecms\Tests\Feature\Content\Concerns\BuildsArticleType;
use Webtack\Ecms\Tests\Fixtures\User;
use Webtack\Ecms\Tests\TestCase;

class TermAdminTest extends TestCase
{
    use BuildsArticleType, RefreshDatabase;

    private Vocabulary $vocabulary;

    protected function setUp(): void
    {
        parent::setUp();

        $this->vocabulary = Vocabulary::factory()->create(['key' => 'categories', 'name' => 'Categories']);
    }

    private function admin(): static
    {
        return $this->actingAs(User::factory()->create());
    }

    private function term(string $name, ?Term $parent = null, int $weight = 0, ?Vocabulary $vocabulary = null): Term
    {
        return app(TermWriter::class)->create($vocabulary ?? ($parent ? $parent->vocabulary : $this->vocabulary), [
            'parent_uuid' => $parent?->uuid,
            'weight' => $weight,
            'translations' => ['default' => ['name' => $name]],
        ]);
    }

    private function image(string $profile = 'image'): File
    {
        $uuid = File::newUuid();

        return File::factory()->create(['uuid' => $uuid, 'profile' => $profile, 'mime' => 'image/png', 'path' => "{$uuid}.png", 'size' => 1000]);
    }

    private function url(string $suffix = ''): string
    {
        return '/admin/taxonomy/categories/terms'.$suffix;
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get($this->url())->assertRedirect('/login');
    }

    public function test_index_lists_the_tree_with_depth_and_order(): void
    {
        $b = $this->term('B', weight: 0);
        $a = $this->term('A', weight: 1);
        $child = $this->term('Child', $b);
        $this->term('Grand', $child);

        $this->admin()->get($this->url())
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Taxonomy/Terms/Index')
                ->where('vocabulary.key', 'categories')
                ->has('terms', 4)
                ->where('terms.0.name', 'B')
                ->where('terms.0.depth', 1)
                ->where('terms.0.children_count', 1)
                ->where('terms.1.name', 'Child')
                ->where('terms.1.depth', 2)
                ->where('terms.2.name', 'Grand')
                ->where('terms.2.depth', 3)
                ->where('terms.3.uuid', $a->uuid)
                ->where('terms.3.usages', []));
    }

    public function test_index_reports_where_a_term_is_used(): void
    {
        $term = $this->term('Jackets');
        $this->useTerm($term, 2);

        $this->admin()->get($this->url())->assertInertia(fn (Assert $page) => $page
            ->where('terms.0.usages', [['owner' => 'node', 'content_type' => 'article', 'field_key' => 'category', 'count' => 2]]));
    }

    public function test_foreign_vocabulary_or_term_answers_404(): void
    {
        $other = Vocabulary::factory()->create(['key' => 'tags']);
        $foreign = $this->term('Foreign', vocabulary: $other);

        $this->admin()->get('/admin/taxonomy/missing/terms')->assertNotFound();
        $this->admin()->get($this->url("/{$foreign->uuid}/edit"))->assertNotFound();
        $this->admin()->put($this->url("/{$foreign->uuid}"), ['translations' => ['default' => ['name' => 'X']]])->assertNotFound();
        $this->admin()->delete($this->url("/{$foreign->uuid}"))->assertNotFound();
        $this->assertNotNull(Term::findByUuid($foreign->uuid));
    }

    public function test_create_form_prefills_the_weight_and_offers_parents(): void
    {
        $root = $this->term('Root', weight: 4);
        $this->term('Child', $root);

        $this->admin()->get($this->url('/create'))->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Taxonomy/Terms/Create')
            ->where('defaultWeight', 5)
            ->has('parentOptions', 2)
            ->where('parentOptions.0', ['uuid' => $root->uuid, 'name' => 'Root', 'depth' => 1])
            ->where('parentOptions.1.depth', 2));
    }

    public function test_store_creates_a_root_with_a_generated_slug(): void
    {
        $this->admin()->post($this->url(), [
            'translations' => ['default' => ['name' => 'Clothes', 'description' => 'All clothes']],
            'slug' => '', 'parent_uuid' => '', 'weight' => '', 'image' => '',
        ])->assertRedirect($this->url())->assertSessionHas('success');

        $term = Term::query()->firstOrFail();
        $this->assertSame('clothes', $term->slug);
        $this->assertNull($term->parent_uuid);
        $this->assertSame(0, $term->weight);
        $this->assertSame('All clothes', TermTranslation::query()->where('term_uuid', $term->uuid)->value('description'));
    }

    public function test_store_puts_a_new_term_after_its_siblings_and_under_a_parent(): void
    {
        $root = $this->term('Root', weight: 3);
        $this->term('First', $root, weight: 7);

        $this->admin()->post($this->url(), ['translations' => ['default' => ['name' => 'Second']], 'parent_uuid' => $root->uuid])
            ->assertSessionHasNoErrors();

        $second = Term::query()->where('slug', 'second')->firstOrFail();
        $this->assertSame($root->uuid, $second->parent_uuid);
        $this->assertSame(8, $second->weight);
    }

    public function test_store_rejects_a_taken_slug_a_missing_name_and_a_foreign_parent(): void
    {
        $this->term('Clothes');
        $foreign = $this->term('Foreign', vocabulary: Vocabulary::factory()->create(['key' => 'tags']));

        $this->admin()->post($this->url(), ['translations' => ['default' => ['name' => 'Other']], 'slug' => 'clothes'])
            ->assertSessionHasErrors('slug');
        $this->admin()->post($this->url(), ['translations' => ['default' => ['name' => '']]])
            ->assertSessionHasErrors('translations.default.name');
        $this->admin()->post($this->url(), ['translations' => ['default' => ['name' => 'X']], 'parent_uuid' => $foreign->uuid])
            ->assertSessionHasErrors('parent_uuid');
        $this->assertSame(2, Term::query()->count());
    }

    public function test_the_depth_limit_hides_parents_and_rejects_a_forced_one(): void
    {
        $limited = Vocabulary::factory()->maxDepth(2)->create(['key' => 'limited']);
        $root = $this->term('Root', vocabulary: $limited);
        $child = $this->term('Child', $root);

        $this->admin()->get('/admin/taxonomy/limited/terms/create')
            ->assertInertia(fn (Assert $page) => $page->has('parentOptions', 1)->where('parentOptions.0.uuid', $root->uuid));

        $this->admin()->post('/admin/taxonomy/limited/terms', ['translations' => ['default' => ['name' => 'Deep']], 'parent_uuid' => $child->uuid])
            ->assertSessionHasErrors('parent_uuid');
    }

    public function test_create_form_has_no_translations_or_switcher(): void
    {
        $this->enableLanguages('en');

        $this->admin()->get($this->url('/create'))
            ->assertInertia(fn (Assert $page) => $page->missing('languages')->missing('translation'));
    }

    public function test_base_form_keeps_translations_of_other_languages(): void
    {
        $this->enableLanguages('en');
        $term = $this->term('Base');
        $this->admin()->put($this->url("/{$term->uuid}/translations/en"), ['name' => 'English'])->assertSessionHasNoErrors();

        $this->admin()->put($this->url("/{$term->uuid}"), ['translations' => ['default' => ['name' => 'Changed'], 'en' => ['name' => 'Hacked']]])
            ->assertSessionHasNoErrors();

        $this->assertSame('English', TermTranslation::class::query()->where('term_uuid', $term->uuid)->where('lang', 'en')->value('name'));
        $this->assertSame('Changed', TermTranslation::class::query()->where('term_uuid', $term->uuid)->where('lang', 'default')->value('name'));
    }

    public function test_translation_mode_is_prefilled_with_base_values_and_marks_languages(): void
    {
        $this->enableLanguages('en', 'pl');
        $term = $this->term('Base');
        $this->admin()->put($this->url("/{$term->uuid}/translations/en"), ['name' => 'English'])->assertSessionHasNoErrors();

        $this->admin()->get($this->url("/{$term->uuid}/edit"))->assertInertia(fn (Assert $page) => $page
            ->where('translation.lang', null)
            ->where('translation.baseLang', 'xx')
            ->where('translation.languages.0.translated', true)
            ->where('translation.languages.1.translated', false)
            ->where('translation.values', null));

        $this->admin()->get($this->url("/{$term->uuid}/edit?lang=en"))->assertInertia(fn (Assert $page) => $page
            ->where('translation.lang', 'en')
            ->where('translation.exists', true)
            ->where('translation.values.name', 'English'));

        $this->admin()->get($this->url("/{$term->uuid}/edit?lang=pl"))->assertInertia(fn (Assert $page) => $page
            ->where('translation.exists', false)
            ->where('translation.values.name', 'Base'));
    }

    public function test_translation_mode_answers_404_for_unavailable_languages(): void
    {
        $term = $this->term('Base');
        $this->admin()->get($this->url("/{$term->uuid}/edit?lang=en"))->assertNotFound();

        $this->enableLanguages('en');
        AppLanguage::factory()->create(['code' => 'de', 'weight' => 5]);

        $this->admin()->get($this->url("/{$term->uuid}/edit?lang=de"))->assertNotFound();
        $this->admin()->get($this->url("/{$term->uuid}/edit?lang=zz"))->assertNotFound();
        $this->admin()->get($this->url("/{$term->uuid}/edit?lang=xx"))->assertOk();
        $this->admin()->put($this->url("/{$term->uuid}/translations/de"), ['name' => 'X'])->assertNotFound();
        $this->admin()->delete($this->url("/{$term->uuid}/translations/zz"))->assertNotFound();
    }

    public function test_translation_is_saved_for_one_language_only_and_equal_values_are_dropped(): void
    {
        $this->enableLanguages('en', 'pl');
        $term = $this->term('Base');
        $this->admin()->put($this->url("/{$term->uuid}/translations/pl"), ['name' => 'Polski'])->assertSessionHasNoErrors();

        $this->admin()->put($this->url("/{$term->uuid}/translations/en"), ['name' => 'Base', 'description' => 'Only a description'])
            ->assertRedirect($this->url("/{$term->uuid}/edit?lang=en"));

        $en = TermTranslation::class::query()->where('term_uuid', $term->uuid)->where('lang', 'en')->firstOrFail();
        $this->assertNull($en->name);
        $this->assertSame('Only a description', $en->description);
        $this->assertSame('Polski', TermTranslation::class::query()->where('term_uuid', $term->uuid)->where('lang', 'pl')->value('name'));

        $this->admin()->put($this->url("/{$term->uuid}/translations/en"), ['name' => '', 'description' => ''])->assertSessionHasNoErrors();
        $this->assertNull(TermTranslation::class::query()->where('term_uuid', $term->uuid)->where('lang', 'en')->first());
    }

    public function test_translation_is_cleared(): void
    {
        $this->enableLanguages('en');
        $term = $this->term('Base');
        $this->admin()->put($this->url("/{$term->uuid}/translations/en"), ['name' => 'English']);

        $this->admin()->delete($this->url("/{$term->uuid}/translations/en"))->assertRedirect($this->url("/{$term->uuid}/edit?lang=en"));

        $this->assertNull(TermTranslation::class::query()->where('term_uuid', $term->uuid)->where('lang', 'en')->first());
        $this->assertSame('Base', TermTranslation::class::query()->where('term_uuid', $term->uuid)->where('lang', 'default')->value('name'));
    }

    public function test_translation_routes_require_the_taxonomy_right(): void
    {
        $this->enableLanguages('en');
        $term = $this->term('Base');
        $user = User::factory()->withRole(Role::factory()->withPermissions([])->create())->create();

        $this->actingAs($user)->get($this->url("/{$term->uuid}/edit?lang=en"))->assertForbidden();
        $this->actingAs($user)->put($this->url("/{$term->uuid}/translations/en"), ['name' => 'X'])->assertForbidden();
        $this->actingAs($user)->delete($this->url("/{$term->uuid}/translations/en"))->assertForbidden();
        $this->assertSame(0, TermTranslation::query()->where('lang', 'en')->count());
    }

    public function test_translations_of_inactive_languages_are_not_written(): void
    {
        $this->admin()->post($this->url(), ['translations' => ['default' => ['name' => 'A'], 'en' => ['name' => 'Ignored']]])
            ->assertSessionHasNoErrors();

        $this->assertSame(0, TermTranslation::query()->where('lang', 'en')->count());
    }

    public function test_edit_offers_no_own_subtree_as_parent_and_shows_the_term(): void
    {
        $clothes = $this->term('Clothes');
        $jackets = $this->term('Jackets', $clothes);
        $this->term('Winter', $jackets);
        $shoes = $this->term('Shoes');

        $this->admin()->get($this->url("/{$clothes->uuid}/edit"))->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Taxonomy/Terms/Edit')
            ->where('term.uuid', $clothes->uuid)
            ->where('term.name', 'Clothes')
            ->where('imageFile', null)
            ->has('parentOptions', 1)
            ->where('parentOptions.0.uuid', $shoes->uuid));
    }

    public function test_the_parent_options_respect_the_subtree_height(): void
    {
        $limited = Vocabulary::factory()->maxDepth(3)->create(['key' => 'limited']);
        $a = $this->term('A', vocabulary: $limited);
        $b = $this->term('B', $a);
        $c = $this->term('C', vocabulary: $limited);
        $d = $this->term('D', $c);
        $this->term('E', $d);

        // "A" has a subtree of height 2: it fits under a root (1 + 2 <= 3) but not under a depth-2 term.
        $this->admin()->get("/admin/taxonomy/limited/terms/{$a->uuid}/edit")->assertInertia(fn (Assert $page) => $page
            ->where('parentOptions', fn ($options) => collect($options)->pluck('uuid')->all() === [$c->uuid]));
        $this->assertNotNull($b);
    }

    public function test_update_moves_a_term_under_another_parent(): void
    {
        $clothes = $this->term('Clothes');
        $shoes = $this->term('Shoes');
        $jackets = $this->term('Jackets', $clothes);

        $this->admin()->from($this->url("/{$jackets->uuid}"))->put($this->url("/{$jackets->uuid}"), [
            'translations' => ['default' => ['name' => 'Jackets']], 'parent_uuid' => $shoes->uuid, 'slug' => 'jackets', 'weight' => '2',
        ])->assertRedirect($this->url("/{$jackets->uuid}"))->assertSessionHas('success');

        $this->assertSame($shoes->uuid, $jackets->fresh()->parent_uuid);
        $this->assertSame(2, $jackets->fresh()->weight);
    }

    public function test_update_rejects_a_descendant_as_parent(): void
    {
        $clothes = $this->term('Clothes');
        $jackets = $this->term('Jackets', $clothes);

        $this->admin()->put($this->url("/{$clothes->uuid}"), ['translations' => ['default' => ['name' => 'Clothes']], 'parent_uuid' => $jackets->uuid])
            ->assertSessionHasErrors('parent_uuid');
        $this->assertNull($clothes->fresh()->parent_uuid);
    }

    public function test_update_keeps_the_slug_and_weight_when_blank_and_moves_to_root(): void
    {
        $clothes = $this->term('Clothes', weight: 5);
        $jackets = $this->term('Jackets', $clothes);

        $this->admin()->put($this->url("/{$jackets->uuid}"), ['translations' => ['default' => ['name' => 'Jackets']], 'parent_uuid' => '', 'slug' => '', 'weight' => ''])
            ->assertSessionHasNoErrors();

        $this->assertNull($jackets->fresh()->parent_uuid);
        $this->assertSame('jackets', $jackets->fresh()->slug);
    }

    public function test_the_image_is_bound_replaced_cleared_and_checked(): void
    {
        $first = $this->image();
        $second = $this->image();
        $document = $this->image('document');
        $term = $this->term('Jackets');
        $data = fn (string $image) => ['translations' => ['default' => ['name' => 'Jackets']], 'image' => $image];

        $this->admin()->put($this->url("/{$term->uuid}"), $data($first->path))->assertSessionHasNoErrors();
        $this->assertSame($first->path, $term->fresh()->image);

        $this->admin()->get($this->url("/{$term->uuid}/edit"))
            ->assertInertia(fn (Assert $page) => $page->where('imageFile.uuid', $first->uuid)->where('imageFile.preview', Storage::disk('public')->url("styles/thumb/{$first->uuid}.webp"))->where('term.image', $first->path));

        $this->admin()->put($this->url("/{$term->uuid}"), $data($second->path))->assertSessionHasNoErrors();
        $this->assertSame($second->path, $term->fresh()->image);

        $this->admin()->put($this->url("/{$term->uuid}"), $data($document->path))->assertSessionHasErrors('image');
        $this->assertSame($second->path, $term->fresh()->image);

        $this->admin()->put($this->url("/{$term->uuid}"), $data(''))->assertSessionHasNoErrors();
        $this->assertNull($term->fresh()->image);
    }

    public function test_the_media_library_shows_the_term_with_a_link_to_its_form(): void
    {
        $image = $this->image();
        $term = $this->term('Jackets');
        $this->admin()->put($this->url("/{$term->uuid}"), ['translations' => ['default' => ['name' => 'Jackets']], 'image' => $image->path]);

        $this->admin()->getJson("/admin/media/{$image->uuid}")
            ->assertOk()
            ->assertJsonPath('usages.0.owner_type', 'taxonomy_term')
            ->assertJsonPath('usages.0.label', 'Jackets')
            ->assertJsonPath('usages.0.url', "/admin/taxonomy/categories/terms/{$term->uuid}/edit")
            ->assertJsonPath('usages.0.role', 'image');
    }

    public function test_destroy_deletes_a_free_leaf_with_its_translations(): void
    {
        $term = $this->term('Jackets');

        $this->admin()->delete($this->url("/{$term->uuid}"))->assertRedirect($this->url())->assertSessionHas('success');

        $this->assertNull(Term::findByUuid($term->uuid));
        $this->assertSame(0, TermTranslation::query()->where('term_uuid', $term->uuid)->count());
    }

    public function test_destroy_keeps_a_term_with_children(): void
    {
        $clothes = $this->term('Clothes');
        $this->term('Jackets', $clothes);

        $this->admin()->from($this->url())->delete($this->url("/{$clothes->uuid}"))
            ->assertRedirect($this->url())
            ->assertSessionHas('error', 'Cannot delete a term that has child terms. Move or delete them first.');

        $this->assertNotNull(Term::findByUuid($clothes->uuid));
    }

    public function test_destroy_keeps_a_used_term_and_names_the_field_and_count(): void
    {
        $term = $this->term('Jackets');
        $this->useTerm($term, 2);

        $this->admin()->from($this->url())->delete($this->url("/{$term->uuid}"))
            ->assertSessionHas('error', 'Cannot delete a term that is used: category (article): 2.');

        $this->assertNotNull(Term::findByUuid($term->uuid));
    }

    public function test_reorder_rewrites_the_weights_by_position(): void
    {
        $a = $this->term('A', weight: 0);
        $b = $this->term('B', weight: 1);
        $child = $this->term('Child', $a, weight: 9);

        $this->admin()->put($this->url('/order'), ['order' => [$b->uuid, $a->uuid, $child->uuid]])
            ->assertSessionHasNoErrors()->assertSessionHas('success');

        $this->assertSame(1, $a->fresh()->weight);
        $this->assertSame(0, $b->fresh()->weight);
        $this->assertSame(2, $child->fresh()->weight);
        $this->assertSame($a->uuid, $child->fresh()->parent_uuid);
    }

    public function test_reorder_rejects_an_incomplete_foreign_or_repeated_list(): void
    {
        $a = $this->term('A', weight: 0);
        $b = $this->term('B', weight: 1);
        $foreign = $this->term('Foreign', vocabulary: Vocabulary::factory()->create(['key' => 'tags']));

        $this->admin()->put($this->url('/order'), ['order' => [$b->uuid]])->assertSessionHasErrors('order');
        $this->admin()->put($this->url('/order'), ['order' => [$b->uuid, $a->uuid, $foreign->uuid]])->assertSessionHasErrors('order');
        $this->admin()->put($this->url('/order'), ['order' => [$b->uuid, $b->uuid]])->assertSessionHasErrors('order.1');
        $this->assertSame(0, $a->fresh()->weight);
        $this->assertSame(1, $b->fresh()->weight);
    }

    /** Stores `$count` article nodes that hold the term in the field `category`. */
    private function useTerm(Term $term, int $count): void
    {
        $this->articleType();
        ContentField::create(['content_type' => 'article', 'key' => 'category', 'name' => 'Category', 'type' => 'term', 'settings' => ['vocabulary' => $this->vocabulary->key]]);

        for ($i = 0; $i < $count; $i++) {
            $this->makeArticle(['title' => "Node $i", 'category' => $term->uuid], "node-$i");
        }
    }
}
