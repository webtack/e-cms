<?php

namespace Webtack\Ecms\Tests\Feature\Taxonomy;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Webtack\Ecms\Models\ContentField;
use Webtack\Ecms\Models\ContentType;
use Webtack\Ecms\Models\Term;
use Webtack\Ecms\Models\Vocabulary;
use Webtack\Ecms\Tests\TestCase;

class VocabularyTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_vocabulary_key_is_immutable(): void
    {
        $vocabulary = Vocabulary::create(['key' => 'categories', 'name' => 'Categories']);

        $this->assertSame('categories', Vocabulary::findByKey('categories')->key);

        foreach (['key' => 'other'] as $attribute => $value) {
            try {
                $vocabulary->fresh()->update([$attribute => $value]);
                $this->fail("The {$attribute} was changed.");
            } catch (ValidationException $e) {
                $this->assertArrayHasKey($attribute, $e->errors());
            }
        }
    }

    public function test_an_invalid_or_taken_key_is_rejected(): void
    {
        Vocabulary::create(['key' => 'tags', 'name' => 'Tags']);

        foreach (['Tags', '1tags', 'ta-gs', 'tags'] as $key) {
            try {
                Vocabulary::create(['key' => $key, 'name' => 'X']);
                $this->fail("The key [{$key}] was accepted.");
            } catch (ValidationException $e) {
                $this->assertArrayHasKey('key', $e->errors());
            }
        }
    }

    public function test_the_maximum_depth_must_be_a_positive_integer(): void
    {
        foreach ([0, -1, 'deep', 1.5] as $depth) {
            try {
                Vocabulary::create(['key' => 'v'.random_int(1, 99999), 'name' => 'X', 'settings' => ['max_depth' => $depth]]);
                $this->fail('A depth of '.json_encode($depth).' was accepted.');
            } catch (ValidationException $e) {
                $this->assertArrayHasKey('settings.max_depth', $e->errors());
            }
        }

        $this->assertSame(2, Vocabulary::factory()->maxDepth(2)->create()->maxDepth());
        $this->assertNull(Vocabulary::factory()->create()->maxDepth());
    }

    public function test_the_depth_cannot_drop_below_the_existing_tree(): void
    {
        $vocabulary = Vocabulary::factory()->maxDepth(3)->create();
        $root = Term::factory()->in($vocabulary)->create();
        $child = Term::factory()->under($root)->create();
        Term::factory()->under($child)->create();

        $this->expectException(ValidationException::class);
        $vocabulary->update(['settings' => ['max_depth' => 2]]);
    }

    public function test_the_depth_can_drop_to_the_existing_height(): void
    {
        $vocabulary = Vocabulary::factory()->maxDepth(3)->create();
        $root = Term::factory()->in($vocabulary)->create();
        Term::factory()->under($root)->create();

        $vocabulary->update(['settings' => ['max_depth' => 2]]);

        $this->assertSame(2, $vocabulary->fresh()->maxDepth());
    }

    public function test_a_vocabulary_with_terms_cannot_be_deleted(): void
    {
        $vocabulary = Vocabulary::factory()->create();
        Term::factory()->in($vocabulary)->create();

        try {
            $vocabulary->delete();
            $this->fail('The vocabulary was deleted.');
        } catch (ValidationException) {
            $this->assertNotNull($vocabulary->fresh());
        }
    }

    public function test_a_vocabulary_named_by_a_field_cannot_be_deleted(): void
    {
        $vocabulary = Vocabulary::factory()->create(['key' => 'categories']);
        $type = ContentType::create(['key' => 'article', 'name' => 'Article']);
        $field = ContentField::create(['content_type' => $type->key, 'key' => 'category', 'name' => 'Category', 'type' => 'term', 'settings' => ['vocabulary' => 'categories']]);

        try {
            $vocabulary->delete();
            $this->fail('The vocabulary was deleted.');
        } catch (ValidationException) {
            $this->assertNotNull($vocabulary->fresh());
        }

        $field->delete();
        $vocabulary->delete();

        $this->assertNull(Vocabulary::findByKey('categories'));
    }

    public function test_a_free_empty_vocabulary_is_deleted(): void
    {
        $vocabulary = Vocabulary::factory()->create();
        $vocabulary->delete();

        $this->assertNull(Vocabulary::query()->find($vocabulary->id));
    }
}
