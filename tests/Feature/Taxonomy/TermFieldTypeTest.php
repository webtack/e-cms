<?php

namespace Webtack\Ecms\Tests\Feature\Taxonomy;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Webtack\Ecms\Content\ContentValueReader;
use Webtack\Ecms\Content\ContentValueWriter;
use Webtack\Ecms\Content\FieldTypeRegistry;
use Webtack\Ecms\Models\ContentField;
use Webtack\Ecms\Models\ContentNode;
use Webtack\Ecms\Models\Term;
use Webtack\Ecms\Models\Vocabulary;
use Webtack\Ecms\Tests\Feature\Content\Concerns\BuildsArticleType;
use Webtack\Ecms\Tests\TestCase;

class TermFieldTypeTest extends TestCase
{
    use BuildsArticleType, RefreshDatabase;

    private Vocabulary $categories;

    private Vocabulary $tags;

    protected function setUp(): void
    {
        parent::setUp();

        $this->articleType();
        $this->categories = Vocabulary::factory()->create(['key' => 'categories']);
        $this->tags = Vocabulary::factory()->create(['key' => 'tags']);
        ContentField::create(['content_type' => 'article', 'key' => 'category', 'name' => 'Category', 'type' => 'term', 'settings' => ['vocabulary' => 'categories']]);
        ContentField::create(['content_type' => 'article', 'key' => 'topics', 'name' => 'Topics', 'type' => 'term', 'is_multiple' => true, 'max_items' => 2, 'settings' => ['vocabulary' => 'tags']]);
    }

    /** @param array<string, mixed> $values */
    private function write(ContentNode $node, array $values, string $lang = 'default'): void
    {
        app(ContentValueWriter::class)->write($node, $values, $lang);
    }

    /** @return array<string, mixed> */
    private function read(ContentNode $node, string $key): mixed
    {
        return app(ContentValueReader::class)->load([$node], [$key])[$node->id][$key];
    }

    public function test_the_type_is_registered_and_uses_the_string_table(): void
    {
        $type = app(FieldTypeRegistry::class)->get('term');

        $this->assertSame('content_values_string', $type->table());
        $this->assertSame(['eq', 'in'], $type->operators());
        $this->assertFalse($type->searchable());
    }

    public function test_a_term_is_saved_and_read_back_as_its_uuid(): void
    {
        $term = Term::factory()->in($this->categories)->create();
        $node = $this->makeArticle();

        $this->write($node, ['category' => strtoupper($term->uuid)]);

        $this->assertSame($term->uuid, $this->read($node, 'category'));
        $this->assertSame($term->uuid, DB::table('content_values_string')->where('field_key', 'category')->value('value'));
    }

    public function test_a_multiple_field_keeps_the_order(): void
    {
        [$a, $b] = Term::factory()->in($this->tags)->count(2)->create();
        $node = $this->makeArticle();

        $this->write($node, ['topics' => [$b->uuid, $a->uuid]]);

        $this->assertSame([$b->uuid, $a->uuid], $this->read($node, 'topics'));
    }

    public function test_the_item_limit_applies(): void
    {
        $node = $this->makeArticle();
        $terms = Term::factory()->in($this->tags)->count(3)->create()->pluck('uuid')->all();

        $this->expectException(ValidationException::class);
        $this->write($node, ['topics' => $terms]);
    }

    public function test_a_term_of_another_vocabulary_is_rejected(): void
    {
        $foreign = Term::factory()->in($this->tags)->create();

        try {
            $this->write($this->makeArticle(), ['category' => $foreign->uuid]);
            $this->fail('A term of another vocabulary was accepted.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('category', $e->errors());
        }
    }

    public function test_a_missing_term_and_a_non_uuid_are_rejected(): void
    {
        $term = Term::factory()->in($this->categories)->create();

        foreach ([(string) Str::uuid7(), $term->slug, '12'] as $value) {
            try {
                $this->write($this->makeArticle(), ['category' => $value]);
                $this->fail("The value [{$value}] was accepted.");
            } catch (ValidationException $e) {
                $this->assertArrayHasKey('category', $e->errors());
            }
        }
    }

    public function test_a_translated_value_is_an_own_row_pointing_at_a_term(): void
    {
        $this->enableLanguages('en');
        ContentField::query()->where('key', 'category')->update(['is_translatable' => true]);
        [$default, $english] = Term::factory()->in($this->categories)->count(2)->create();
        $node = $this->makeArticle();

        $this->write($node, ['category' => $default->uuid]);
        $this->write($node, ['category' => $english->uuid], 'en');

        $this->assertSame(2, DB::table('content_values_string')->where('field_key', 'category')->count());
    }

    public function test_a_write_of_a_term_that_vanished_is_rejected_with_the_field_key(): void
    {
        $term = Term::factory()->in($this->categories)->create();
        $node = $this->makeArticle();
        $type = app(FieldTypeRegistry::class)->get('term');

        // The reference check passes validation, then the term disappears before the lock is taken.
        DB::table('taxonomy_terms')->where('uuid', $term->uuid)->delete();

        try {
            $type->lockReferences([$term->uuid]);
            $this->fail('The lock accepted a vanished term.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('value', $e->errors());
        }
        $this->assertNotNull($node);
    }
}
