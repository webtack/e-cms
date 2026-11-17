<?php

namespace Webtack\Ecms\Tests\Feature\Taxonomy;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Webtack\Ecms\Content\ContentNodeCreator;
use Webtack\Ecms\Models\ContentField;
use Webtack\Ecms\Models\ContentType;
use Webtack\Ecms\Models\Term;
use Webtack\Ecms\Models\Vocabulary;
use Webtack\Ecms\Taxonomy\TermUsage;
use Webtack\Ecms\Tests\Feature\Content\Concerns\BuildsArticleType;
use Webtack\Ecms\Tests\TestCase;

class TermUsageTest extends TestCase
{
    use BuildsArticleType, RefreshDatabase;

    public function test_the_usage_lists_the_field_and_the_number_of_records(): void
    {
        $this->articleType();
        $vocabulary = Vocabulary::factory()->create(['key' => 'tags']);
        $term = Term::factory()->in($vocabulary)->create();
        $other = Term::factory()->in($vocabulary)->create();
        ContentField::create(['content_type' => 'article', 'key' => 'topics', 'name' => 'Topics', 'type' => 'term', 'is_multiple' => true, 'settings' => ['vocabulary' => 'tags']]);

        $this->makeArticle(['title' => 'One', 'topics' => [$term->uuid, $other->uuid]], 'one');
        $this->makeArticle(['title' => 'Two', 'topics' => [$term->uuid]], 'two');

        $this->assertSame(
            [['owner' => 'node', 'content_type' => 'article', 'field_key' => 'topics', 'count' => 2]],
            app(TermUsage::class)->forTerm($term)->all(),
        );
        $this->assertSame(1, app(TermUsage::class)->forTerm($other)->first()['count']);
    }

    public function test_an_unused_term_and_a_string_field_with_the_same_text_do_not_count(): void
    {
        $this->articleType();
        $term = Term::factory()->create();

        // A plain string field may hold any text, including a uuid; it is not a use of the term.
        $this->makeArticle(['title' => $term->uuid]);

        $this->assertTrue(app(TermUsage::class)->forTerm($term)->isEmpty());
    }

    public function test_usage_in_another_content_type_is_listed_separately(): void
    {
        $vocabulary = Vocabulary::factory()->create(['key' => 'cats']);
        $term = Term::factory()->in($vocabulary)->create();
        $this->articleType();
        ContentType::create(['key' => 'news', 'name' => 'News']);
        foreach (['article', 'news'] as $type) {
            ContentField::create(['content_type' => $type, 'key' => 'cat', 'name' => 'Cat', 'type' => 'term', 'settings' => ['vocabulary' => 'cats']]);
        }
        $this->makeArticle(['title' => 'A', 'cat' => $term->uuid]);
        $news = app(ContentNodeCreator::class)->create(ContentType::findByKey('news'), ['title' => 'N', 'cat' => $term->uuid], null, authorId: $this->authorId());

        $this->assertSame(['article', 'news'], app(TermUsage::class)->forTerm($term)->pluck('content_type')->all());
    }
}
