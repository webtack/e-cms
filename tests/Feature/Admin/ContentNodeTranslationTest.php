<?php

namespace Webtack\Ecms\Tests\Feature\Admin;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Webtack\Ecms\Content\ContentLanguages;
use Webtack\Ecms\Content\ContentNodeCreator;
use Webtack\Ecms\Content\ContentValueReader;
use Webtack\Ecms\Models\ContentField;
use Webtack\Ecms\Models\ContentNode;
use Webtack\Ecms\Models\ContentType;
use Webtack\Ecms\Models\Role;
use Webtack\Ecms\Tests\Feature\Content\Concerns\BuildsArticleType;
use Webtack\Ecms\Tests\Fixtures\User;
use Webtack\Ecms\Tests\TestCase;

/**
 * The translation mode of the node edit page: `?lang=`, saving and clearing one language.
 */
class ContentNodeTranslationTest extends TestCase
{
    use BuildsArticleType, RefreshDatabase;

    private ContentNode $node;

    protected function setUp(): void
    {
        parent::setUp();

        $this->enableLanguages('en', 'pl');
        $this->articleType();
        $this->node = $this->makeArticle(['title' => 'Привіт', 'body' => 'Основа', 'visits' => 3, 'tags' => ['a', 'b']]);
    }

    private function admin(): static
    {
        return $this->actingAs(User::factory()->create());
    }

    private function url(string $suffix = '', string $section = 'content-types', ?ContentNode $node = null): string
    {
        return "/admin/{$section}/article/nodes/".($node ?? $this->node)->uuid.$suffix;
    }

    private function stored(string $lang, array $keys = ['title', 'body', 'tags']): array
    {
        return app(ContentValueReader::class)->load([$this->node->fresh()], $keys, $lang)[$this->node->id];
    }

    public function test_routes_have_names_in_every_section(): void
    {
        foreach (['content-types', 'page-types', 'block-types'] as $section) {
            $this->assertSame("/admin/{$section}/article/nodes/x/translations/en", route("admin.{$section}.nodes.translations.save", ['article', 'x', 'en'], false));
            $this->assertSame("/admin/{$section}/article/nodes/x/translations/en", route("admin.{$section}.nodes.translations.clear", ['article', 'x', 'en'], false));
        }
    }

    public function test_the_translation_mode_shows_translatable_fields_prefilled_from_the_base(): void
    {
        $this->admin()->get($this->url('/edit?lang=en'))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('fields', fn ($fields) => collect($fields)->where('translatable', true)->pluck('key')->sort()->values()->all() === ['body', 'meta_description', 'meta_image', 'meta_title', 'tags', 'title'])
            ->where('fields', fn ($fields) => collect($fields)->where('key', 'visits')->first()['translatable'] === false)
            ->where('values.visits', fn ($value) => $value !== null)
            ->where('translation.lang', 'en')
            ->where('translation.exists', false)
            ->where('translation.values.title', 'Привіт')
            ->where('translation.values.body', 'Основа')
            ->where('translation.values.tags', ['a', 'b'])
            ->missing('translation.values.visits')
            ->where('node.slug', $this->node->slug));
    }

    public function test_a_stored_translation_wins_per_field_and_is_marked(): void
    {
        $this->admin()->put($this->url('/translations/en'), ['values' => ['title' => 'Hello']])->assertRedirect();

        $this->admin()->get($this->url('/edit?lang=en'))->assertInertia(fn (Assert $page) => $page
            ->where('translation.exists', true)
            ->where('translation.values.title', 'Hello')
            ->where('translation.values.body', 'Основа')
            ->where('translation.languages', fn ($languages) => collect($languages)->pluck('translated', 'code')->all() === ['en' => true, 'pl' => false]));
    }

    public function test_the_base_mode_carries_the_switcher_and_no_values(): void
    {
        $this->admin()->get($this->url('/edit'))->assertInertia(fn (Assert $page) => $page
            ->where('translation.lang', null)
            ->where('translation.values', null)
            ->where('values.title', 'Привіт')
            ->where('values.visits', 3));
    }

    public function test_a_single_language_site_has_no_switcher(): void
    {
        app(ContentLanguages::class)->setMultilingual(false);

        $this->admin()->get($this->url('/edit'))->assertInertia(fn (Assert $page) => $page->where('translation', null));
        $this->admin()->get($this->url('/edit?lang=en'))->assertNotFound();
    }

    public function test_an_unknown_or_inactive_language_is_404(): void
    {
        $this->admin()->get($this->url('/edit?lang=de'))->assertNotFound();
        $this->admin()->put($this->url('/translations/de'), ['values' => ['title' => 'x']])->assertNotFound();
        $this->admin()->delete($this->url('/translations/de'))->assertNotFound();
    }

    public function test_saving_a_translated_title_keeps_the_base_and_stays_on_the_form(): void
    {
        $before = $this->node->fresh()->updated_at;
        $this->travel(5)->minutes();

        $this->admin()->put($this->url('/translations/en'), ['values' => ['title' => 'Hello']])
            ->assertRedirect($this->url('/edit?lang=en'))
            ->assertSessionHas('success');

        $this->assertSame('Hello', $this->stored('en')['title']);
        $this->assertSame('Привіт', $this->stored('default')['title']);
        $this->assertTrue($this->node->fresh()->updated_at->gt($before));
    }

    public function test_a_required_field_may_be_left_empty_and_its_translation_is_removed(): void
    {
        $this->admin()->put($this->url('/translations/en'), ['values' => ['title' => 'Hello']]);

        $this->admin()->put($this->url('/translations/en'), ['values' => ['title' => '']])->assertSessionHasNoErrors();

        $this->assertNull($this->stored('en')['title']);
    }

    public function test_a_value_equal_to_the_base_is_not_stored(): void
    {
        $this->admin()->put($this->url('/translations/en'), ['values' => ['title' => 'Привіт', 'body' => 'Основа', 'tags' => ['a', 'b']]])->assertSessionHasNoErrors();

        $this->assertSame(['title' => null, 'body' => null, 'tags' => []], $this->stored('en'));
    }

    public function test_a_multiple_field_is_saved_as_a_whole_list(): void
    {
        $this->admin()->put($this->url('/translations/en'), ['values' => ['tags' => ['a', 'c']]]);

        $this->assertSame(['a', 'c'], $this->stored('en')['tags']);
        $this->assertSame(['a', 'b'], $this->stored('default')['tags']);
    }

    public function test_a_non_translatable_field_is_a_form_error(): void
    {
        $this->admin()->put($this->url('/translations/en'), ['values' => ['title' => 'Hello', 'visits' => 9]])
            ->assertSessionHasErrors('values.visits');

        $this->assertNull($this->stored('en')['title']);
    }

    public function test_clearing_removes_the_language_and_returns_to_the_form(): void
    {
        $this->admin()->put($this->url('/translations/en'), ['values' => ['title' => 'Hello']]);
        $this->admin()->put($this->url('/translations/pl'), ['values' => ['title' => 'Czesc']]);

        $this->admin()->delete($this->url('/translations/en'))->assertRedirect($this->url('/edit?lang=en'));

        $this->assertNull($this->stored('en')['title']);
        $this->assertSame('Czesc', $this->stored('pl')['title']);
        $this->assertSame('Привіт', $this->stored('default')['title']);
    }

    public function test_pages_and_blocks_have_the_mode_too(): void
    {
        foreach (['page' => 'page-types', 'block' => 'block-types'] as $kind => $section) {
            $type = ContentType::create(['key' => "t{$kind}", 'name' => 'T', 'kind' => $kind]);
            $node = app(ContentNodeCreator::class)->create($type, ['title' => 'Base'], authorId: $this->authorId());
            $url = "/admin/{$section}/t{$kind}/nodes/{$node->uuid}";

            $this->admin()->get("{$url}/edit?lang=en")->assertInertia(fn (Assert $page) => $page->where('translation.values.title', 'Base'));
            $this->admin()->put("{$url}/translations/en", ['values' => ['title' => 'Home']])->assertRedirect("{$url}/edit?lang=en");

            $this->assertSame('Home', app(ContentValueReader::class)->load([$node], ['title'], 'en')[$node->id]['title']);
        }
    }

    public function test_the_access_level_of_the_base_edit_page_applies(): void
    {
        $user = User::factory()->withRole(Role::factory()->withPermissions(['node:article' => 'own'])->create())->create();
        $foreign = $this->node;
        $own = app(ContentNodeCreator::class)->create(ContentType::findByKey('article'), ['title' => 'Mine'], authorId: $user->id);
        $this->actingAs($user);

        $this->get($this->url('/edit?lang=en', node: $foreign))->assertForbidden();
        $this->put($this->url('/translations/en', node: $foreign), ['values' => ['title' => 'x']])->assertForbidden();
        $this->delete($this->url('/translations/en', node: $foreign))->assertForbidden();

        $this->get($this->url('/edit?lang=en', node: $own))->assertOk();
        $this->put($this->url('/translations/en', node: $own), ['values' => ['title' => 'Mine en']])->assertRedirect();
    }

    public function test_a_field_marked_not_translatable_stays_in_the_form_flagged(): void
    {
        ContentField::query()->where('key', 'body')->update(['is_translatable' => false]);

        $this->admin()->get($this->url('/edit?lang=en'))->assertInertia(fn (Assert $page) => $page
            ->where('fields', fn ($fields) => collect($fields)->firstWhere('key', 'body')['translatable'] === false)
            ->missing('translation.values.body'));
    }
}
