<?php

namespace Webtack\Ecms\Tests\Feature\Taxonomy;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Webtack\Ecms\Files\FileRelations;
use Webtack\Ecms\Models\File;
use Webtack\Ecms\Models\MediaRelation;
use Webtack\Ecms\Models\Term;
use Webtack\Ecms\Models\Vocabulary;
use Webtack\Ecms\Taxonomy\TermWriter;
use Webtack\Ecms\Tests\Feature\Content\Concerns\BuildsArticleType;
use Webtack\Ecms\Tests\TestCase;

class TermWriterTest extends TestCase
{
    use BuildsArticleType, RefreshDatabase;

    private Vocabulary $vocabulary;

    protected function setUp(): void
    {
        parent::setUp();

        $this->vocabulary = Vocabulary::factory()->create();
    }

    private function writer(): TermWriter
    {
        return app(TermWriter::class);
    }

    private function image(string $profile = 'image'): File
    {
        $uuid = File::newUuid();

        return File::factory()->create(['uuid' => $uuid, 'profile' => $profile, 'mime' => 'image/png', 'path' => "{$uuid}.png", 'size' => 1000]);
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

    public function test_a_term_is_created_with_translations_and_a_generated_slug(): void
    {
        $this->enableLanguages('en');

        $term = $this->writer()->create($this->vocabulary, [
            'translations' => ['default' => ['name' => 'Куртки', 'description' => 'Теплі'], 'en' => ['name' => 'Jackets']],
        ]);

        $this->assertSame('kurtky', $term->slug);
        $this->assertSame(['Куртки', 'Jackets'], $term->translations()->orderBy('id')->pluck('name')->all());
        $this->assertSame('Теплі', $term->translations()->where('lang', 'default')->value('description'));
    }

    public function test_the_generated_slug_gets_a_suffix_inside_the_vocabulary(): void
    {
        $first = $this->writer()->create($this->vocabulary, ['translations' => ['default' => ['name' => 'News']]]);
        $second = $this->writer()->create($this->vocabulary, ['translations' => ['default' => ['name' => 'News']]]);
        $third = $this->writer()->create($this->vocabulary, ['translations' => ['default' => ['name' => 'News']]]);
        $other = $this->writer()->create(Vocabulary::factory()->create(), ['translations' => ['default' => ['name' => 'News']]]);

        $this->assertSame(['news', 'news-2', 'news-3', 'news'], [$first->slug, $second->slug, $third->slug, $other->slug]);
    }

    public function test_an_explicit_slug_is_kept(): void
    {
        $term = $this->writer()->create($this->vocabulary, ['slug' => 'custom', 'translations' => ['default' => ['name' => 'Anything']]]);

        $this->assertSame('custom', $term->slug);
    }

    public function test_the_default_name_is_required(): void
    {
        $this->assertArrayHasKey('translations.default.name', $this->errorsOf(
            fn () => $this->writer()->create($this->vocabulary, ['slug' => 'a', 'translations' => []]),
        ));
        $this->assertSame(0, Term::query()->count());
    }

    public function test_an_unavailable_language_is_rejected_and_nothing_is_stored(): void
    {
        $errors = $this->errorsOf(fn () => $this->writer()->create($this->vocabulary, [
            'translations' => ['default' => ['name' => 'A'], 'en' => ['name' => 'A']],
        ]));

        $this->assertArrayHasKey('lang', $errors);
        $this->assertSame(0, Term::query()->count());
    }

    public function test_translations_are_independent_and_a_blank_one_is_removed(): void
    {
        $this->enableLanguages('en');
        $term = $this->writer()->create($this->vocabulary, ['translations' => ['default' => ['name' => 'Куртки'], 'en' => ['name' => 'Jackets']]]);

        $this->writer()->update($term, ['translations' => ['en' => ['name' => 'Coats']]]);
        $this->assertSame('Куртки', $term->translations()->where('lang', 'default')->value('name'));
        $this->assertSame('Coats', $term->translations()->where('lang', 'en')->value('name'));

        $this->writer()->update($term, ['translations' => ['en' => ['name' => '']]]);
        $this->assertSame(1, $term->translations()->count());

        $this->assertArrayHasKey('translations.default.name', $this->errorsOf(
            fn () => $this->writer()->update($term, ['translations' => ['default' => ['name' => ' ']]]),
        ));
    }

    public function test_a_translation_may_hold_only_a_description(): void
    {
        $this->enableLanguages('en');
        $e = $this->writer()->create($this->vocabulary, ['translations' => ['default' => ['name' => 'Base'], 'en' => ['description' => 'Only text']]]);

        $row = $e->translations()->where('lang', 'en')->firstOrFail();
        $this->assertNull($row->name);
        $this->assertSame('Only text', $row->description);

        $this->writer()->update($e, ['translations' => ['en' => ['description' => '']]]);
        $this->assertSame(0, $e->translations()->where('lang', 'en')->count());
    }

    public function test_saving_one_language_drops_values_equal_to_the_base_and_leaves_other_languages(): void
    {
        $this->enableLanguages('en', 'pl');
        $e = $this->writer()->create($this->vocabulary, ['translations' => [
            'default' => ['name' => 'Base', 'description' => 'Base text'], 'pl' => ['name' => 'Polski'],
        ]]);

        $this->writer()->saveTranslation($e, 'en', ['name' => 'Base', 'description' => 'English text']);

        $en = $e->translations()->where('lang', 'en')->firstOrFail();
        $this->assertNull($en->name);
        $this->assertSame('English text', $en->description);
        $this->assertSame('Polski', $e->translations()->where('lang', 'pl')->value('name'));
        $this->assertSame('Base', $e->translations()->where('lang', 'default')->value('name'));
        $this->assertSame('Base text', $e->translations()->where('lang', 'default')->value('description'));
    }

    public function test_saving_nothing_new_deletes_the_translation(): void
    {
        $this->enableLanguages('en');
        $e = $this->writer()->create($this->vocabulary, ['translations' => ['default' => ['name' => 'Base', 'description' => 'Text'], 'en' => ['name' => 'English']]]);

        $this->writer()->saveTranslation($e, 'en', ['name' => 'Base', 'description' => 'Text']);

        $this->assertSame(0, $e->translations()->where('lang', 'en')->count());
        $this->assertSame(1, $e->translations()->count());
    }

    public function test_saving_one_language_rejects_the_base_and_unavailable_languages(): void
    {
        $this->enableLanguages('en');
        $e = $this->writer()->create($this->vocabulary, ['translations' => ['default' => ['name' => 'Base']]]);

        $this->assertArrayHasKey('lang', $this->errorsOf(fn () => $this->writer()->saveTranslation($e, 'default', ['name' => 'X'])));
        $this->assertArrayHasKey('lang', $this->errorsOf(fn () => $this->writer()->saveTranslation($e, 'zz', ['name' => 'X'])));
        $this->assertArrayHasKey('lang', $this->errorsOf(fn () => $this->writer()->clearTranslation($e, 'default')));
        $this->assertSame(1, $e->translations()->count());
    }

    public function test_clearing_one_language_keeps_the_rest(): void
    {
        $this->enableLanguages('en', 'pl');
        $e = $this->writer()->create($this->vocabulary, ['translations' => ['default' => ['name' => 'Base'], 'en' => ['name' => 'English'], 'pl' => ['name' => 'Polski']]]);

        $this->writer()->clearTranslation($e, 'en');
        $this->writer()->clearTranslation($e, 'en');

        $this->assertSame(['default', 'pl'], $e->translations()->orderBy('lang')->pluck('lang')->all());
    }

    public function test_the_image_is_bound_replaced_and_cleared(): void
    {
        $first = $this->image();
        $second = $this->image();
        $term = $this->writer()->create($this->vocabulary, ['image' => $first->path, 'translations' => ['default' => ['name' => 'A']]]);

        $this->assertSame($first->path, $term->fresh()->image);
        $this->assertSame([Term::MEDIA_OWNER], MediaRelation::query()->where('file_uuid', $first->uuid)->pluck('owner_type')->all());

        $this->writer()->update($term, ['image' => $second->path]);
        $this->assertSame([$first->uuid => 0, $second->uuid => 1], app(FileRelations::class)->usageCounts([$first->uuid, $second->uuid]));
        $this->assertNotNull(File::findByUuid($first->uuid));

        $this->writer()->update($term, ['image' => '']);
        $this->assertNull($term->fresh()->image);
        $this->assertSame([$second->uuid => 0], app(FileRelations::class)->usageCounts([$second->uuid]));
    }

    public function test_a_non_image_or_missing_file_is_rejected(): void
    {
        $document = $this->image('document');

        $this->assertArrayHasKey('image', $this->errorsOf(
            fn () => $this->writer()->create($this->vocabulary, ['image' => $document->path, 'translations' => ['default' => ['name' => 'A']]]),
        ));
        $this->assertArrayHasKey('image', $this->errorsOf(
            fn () => $this->writer()->create($this->vocabulary, ['image' => str_repeat('a', 32).'.png', 'translations' => ['default' => ['name' => 'A']]]),
        ));
        $this->assertSame(0, Term::query()->count());
    }

    public function test_deleting_a_term_with_an_image_frees_the_file(): void
    {
        $image = $this->image();
        $term = $this->writer()->create($this->vocabulary, ['image' => $image->path, 'translations' => ['default' => ['name' => 'A']]]);

        $term->delete();

        $this->assertSame([$image->uuid => 0], app(FileRelations::class)->usageCounts([$image->uuid]));
        $this->assertNotNull(File::findByUuid($image->uuid));
    }

    public function test_an_update_can_move_the_term(): void
    {
        $root = $this->writer()->create($this->vocabulary, ['translations' => ['default' => ['name' => 'Root']]]);
        $leaf = $this->writer()->create($this->vocabulary, ['translations' => ['default' => ['name' => 'Leaf']]]);

        $this->writer()->update($leaf, ['parent_uuid' => $root->uuid, 'weight' => 5]);

        $this->assertSame($root->uuid, $leaf->fresh()->parent_uuid);
        $this->assertSame(5, $leaf->fresh()->weight);
    }
}
