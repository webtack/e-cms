<?php

namespace Webtack\Ecms\Tests\Feature\Content;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Webtack\Ecms\Content\ContentNodeCreator;
use Webtack\Ecms\Content\ContentValueWriter;
use Webtack\Ecms\Models\ContentField;
use Webtack\Ecms\Models\ContentNode;
use Webtack\Ecms\Models\ContentType;
use Webtack\Ecms\Reference\ReferenceUsage;
use Webtack\Ecms\Tests\Feature\Content\Concerns\BuildsArticleType;
use Webtack\Ecms\Tests\TestCase;

class RefUsageTest extends TestCase
{
    use BuildsArticleType, RefreshDatabase;

    private ContentType $news;

    protected function setUp(): void
    {
        parent::setUp();

        $this->articleType();
        $this->news = ContentType::create(['key' => 'news', 'name' => 'News']);
        ContentType::create(['key' => 'hero', 'name' => 'Hero', 'kind' => 'block']);
        ContentField::create(['content_type' => 'article', 'key' => 'related', 'name' => 'Related', 'type' => 'node_ref', 'settings' => ['content_type' => 'news']]);
        ContentField::create(['content_type' => 'article', 'key' => 'source', 'name' => 'Source', 'type' => 'type_ref', 'settings' => ['kind' => 'block']]);
    }

    private function news(string $title = 'First'): ContentNode
    {
        return app(ContentNodeCreator::class)->create($this->news, ['title' => $title], authorId: $this->authorId());
    }

    private function write(ContentNode $node, string $key, string $value): void
    {
        app(ContentValueWriter::class)->write($node, [$key => $value]);
    }

    public function test_a_referenced_node_cannot_be_deleted(): void
    {
        $target = $this->news();
        $this->write($this->makeArticle(), 'related', $target->uuid);

        $this->assertSame(1, app(ReferenceUsage::class)->forNode($target)->first()['count']);

        $this->expectException(ValidationException::class);
        $target->delete();
    }

    public function test_a_node_without_references_is_deleted(): void
    {
        $target = $this->news();
        $unused = $this->news('Unused');
        $this->write($this->makeArticle(), 'related', $target->uuid);

        $unused->delete();

        $this->assertModelMissing($unused);
    }

    public function test_a_node_is_deleted_once_the_reference_is_cleared(): void
    {
        $target = $this->news();
        $article = $this->makeArticle();
        $this->write($article, 'related', $target->uuid);
        $this->write($article, 'related', '');

        $target->delete();

        $this->assertModelMissing($target);
    }

    public function test_a_referenced_type_cannot_be_deleted(): void
    {
        $this->write($this->makeArticle(), 'source', 'hero');

        $this->assertSame('source', app(ReferenceUsage::class)->forType(ContentType::findByKey('hero'))->first()['field_key']);

        $this->expectException(ValidationException::class);
        ContentType::findByKey('hero')->delete();
    }

    public function test_a_type_whose_node_is_referenced_cannot_be_deleted(): void
    {
        $this->write($this->makeArticle(), 'related', $this->news()->uuid);

        $this->assertSame(1, app(ReferenceUsage::class)->forType($this->news)->count());

        $this->expectException(ValidationException::class);
        $this->news->delete();
    }

    public function test_a_type_without_references_is_deleted(): void
    {
        ContentType::findByKey('hero')->delete();

        $this->assertNull(ContentType::findByKey('hero'));
    }

    public function test_values_owned_by_the_deleted_type_do_not_count(): void
    {
        ContentField::create(['content_type' => 'news', 'key' => 'self_ref', 'name' => 'Self', 'type' => 'type_ref', 'settings' => ['kind' => 'content']]);
        $node = $this->news();
        $this->write($node, 'self_ref', 'news');

        $this->assertTrue(app(ReferenceUsage::class)->forType($this->news)->isEmpty());
    }

    public function test_the_values_of_a_node_about_itself_do_not_count(): void
    {
        ContentField::create(['content_type' => 'news', 'key' => 'sibling', 'name' => 'Sibling', 'type' => 'node_ref', 'settings' => ['content_type' => 'news']]);
        $node = $this->news();
        $this->write($node, 'sibling', $node->uuid);

        $this->assertTrue(app(ReferenceUsage::class)->forNode($node)->isEmpty());
    }
}
