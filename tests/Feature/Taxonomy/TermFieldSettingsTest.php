<?php

namespace Webtack\Ecms\Tests\Feature\Taxonomy;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Webtack\Ecms\Models\ContentField;
use Webtack\Ecms\Models\ContentType;
use Webtack\Ecms\Models\Term;
use Webtack\Ecms\Models\Vocabulary;
use Webtack\Ecms\Tests\Feature\Content\Concerns\BuildsArticleType;
use Webtack\Ecms\Tests\TestCase;

class TermFieldSettingsTest extends TestCase
{
    use BuildsArticleType, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        ContentType::create(['key' => 'article', 'name' => 'Article']);
        Vocabulary::factory()->create(['key' => 'categories']);
        Vocabulary::factory()->create(['key' => 'tags']);
    }

    /** @param array<string, mixed>|null $settings */
    private function field(?array $settings, string $key = 'category'): ContentField
    {
        return ContentField::create(['content_type' => 'article', 'key' => $key, 'name' => 'Category', 'type' => 'term', 'settings' => $settings]);
    }

    public function test_the_vocabulary_is_required(): void
    {
        foreach ([null, [], ['vocabulary' => ''], ['vocabulary' => 5]] as $settings) {
            try {
                $this->field($settings);
                $this->fail('A field without a vocabulary was saved.');
            } catch (ValidationException $e) {
                $this->assertArrayHasKey('settings.vocabulary', $e->errors());
            }
        }
    }

    public function test_an_unknown_vocabulary_is_rejected(): void
    {
        $this->expectException(ValidationException::class);
        $this->field(['vocabulary' => 'nope']);
    }

    public function test_the_vocabulary_of_a_field_with_values_is_fixed(): void
    {
        $field = $this->field(['vocabulary' => 'categories']);
        $term = Term::factory()->in(Vocabulary::findByKey('categories'))->create();
        $this->makeArticle(['title' => 'A', 'category' => $term->uuid]);

        try {
            $field->fresh()->update(['settings' => ['vocabulary' => 'tags']]);
            $this->fail('The vocabulary of a field with values was changed.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('settings.vocabulary', $e->errors());
        }

        $field->fresh()->update(['name' => 'Renamed']);
        $this->assertSame('Renamed', $field->fresh()->name);
    }

    public function test_the_vocabulary_of_an_empty_field_can_change(): void
    {
        $field = $this->field(['vocabulary' => 'categories']);

        $field->update(['settings' => ['vocabulary' => 'tags']]);

        $this->assertSame('tags', $field->fresh()->settings['vocabulary']);
    }

    public function test_other_types_are_not_affected(): void
    {
        $field = ContentField::create(['content_type' => 'article', 'key' => 'note2', 'name' => 'Note', 'type' => 'string']);

        $this->assertNotNull($field->id);
    }
}
