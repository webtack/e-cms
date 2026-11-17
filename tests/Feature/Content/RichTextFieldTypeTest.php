<?php

namespace Webtack\Ecms\Tests\Feature\Content;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Webtack\Ecms\Content\ContentNodeQuery;
use Webtack\Ecms\Content\ContentValueReader;
use Webtack\Ecms\Content\ContentValueWriter;
use Webtack\Ecms\Content\FieldTypeRegistry;
use Webtack\Ecms\Content\HtmlSanitizer;
use Webtack\Ecms\Content\NodeQueryParams;
use Webtack\Ecms\Models\ContentField;
use Webtack\Ecms\Models\ContentNode;
use Webtack\Ecms\Models\ContentType;
use Webtack\Ecms\Models\File;
use Webtack\Ecms\Tests\Feature\Content\Concerns\BuildsArticleType;
use Webtack\Ecms\Tests\TestCase;

class RichTextFieldTypeTest extends TestCase
{
    use BuildsArticleType, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->articleType();
        $this->field('content', ['is_required' => false, 'is_translatable' => true]);
    }

    /**
     * @param  array<string, mixed>  $extra
     */
    private function field(string $key, array $extra = [], ?array $settings = null): ContentField
    {
        return ContentField::create(['content_type' => 'article', 'key' => $key, 'name' => ucfirst($key), 'type' => 'richtext', 'settings' => $settings, ...$extra]);
    }

    private function writer(): ContentValueWriter
    {
        return app(ContentValueWriter::class);
    }

    /** @return array<string, mixed> */
    private function read(ContentNode $node): array
    {
        return app(ContentValueReader::class)->load([$node])[$node->id];
    }

    private function stored(ContentNode $node, string $key = 'content'): array
    {
        return DB::table('content_values_text')->where('content_node_id', $node->id)->where('field_key', $key)
            ->orderBy('weight')->pluck('value')->all();
    }

    private function assertInvalid(callable $call, string $key): void
    {
        try {
            $call();
            $this->fail('A ValidationException was expected.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey($key, $e->errors());
        }
    }

    public function test_value_is_stored_in_the_text_table_and_read_back(): void
    {
        $node = $this->makeArticle(['title' => 'A', 'content' => '<p>Привіт, <strong>світе</strong></p>']);

        $this->assertSame(['<p>Привіт, <strong>світе</strong></p>'], $this->stored($node));
        $this->assertSame('<p>Привіт, <strong>світе</strong></p>', $this->read($node)['content']);
    }

    public function test_value_is_sanitised_on_write(): void
    {
        $node = $this->makeArticle(['title' => 'A', 'content' => '<p onclick="x()">Hi</p><script>alert(1)</script><img src="a.png">']);

        $this->assertSame(['<p>Hi</p>'], $this->stored($node));
        $this->assertSame('<p>Hi</p>', $this->read($node)['content']);
    }

    public function test_empty_document_clears_an_optional_field(): void
    {
        $node = $this->makeArticle(['title' => 'A', 'content' => '<p>Text</p>']);

        $this->writer()->write($node, ['content' => '<p></p>']);

        $this->assertSame([], $this->stored($node));
    }

    public function test_frame_only_value_is_content_not_blank(): void
    {
        $type = app(FieldTypeRegistry::class)->get('richtext');
        $frame = '<iframe src="https://www.youtube-nocookie.com/embed/abc"></iframe>';

        $this->assertFalse($type->isBlank($frame));
        $this->assertTrue($type->isBlank('<p></p><iframe src="https://evil.example/x"></iframe>'));
        $this->assertSame('', app(HtmlSanitizer::class)->text($frame));

        $node = $this->makeArticle(['title' => 'A', 'content' => $frame]);

        $this->assertCount(1, $this->stored($node));
    }

    public function test_empty_document_is_rejected_for_a_required_field(): void
    {
        $this->field('summary', ['is_required' => true]);

        $this->assertInvalid(fn () => $this->makeArticle(['title' => 'A', 'summary' => '<p></p>']), 'summary');
        $this->assertInvalid(fn () => $this->makeArticle(['title' => 'A', 'summary' => '<p>&nbsp;</p>']), 'summary');
    }

    public function test_a_table_without_text_is_empty_and_a_table_with_text_is_accepted(): void
    {
        $this->field('summary', ['is_required' => true]);

        $this->assertInvalid(fn () => $this->makeArticle(['title' => 'A', 'summary' => '<table><tr><td></td><td><p></p></td></tr></table>']), 'summary');

        $node = $this->makeArticle(['title' => 'A', 'summary' => '<table><tbody><tr><td>10:00</td></tr></tbody></table>']);

        $this->assertSame(['<table><tbody><tr><td>10:00</td></tr></tbody></table>'], $this->stored($node, 'summary'));
    }

    public function test_length_counts_the_text_of_table_cells(): void
    {
        $this->field('teaser', settings: ['max' => 5]);

        $this->makeArticle(['title' => 'A', 'teaser' => '<table><tr><td>ab</td><td>cd</td></tr></table>']);
        $this->assertInvalid(fn () => $this->makeArticle(['title' => 'B', 'teaser' => '<table><tr><td>abc</td><td>def</td></tr></table>']), 'teaser');
    }

    public function test_length_counts_visible_text_only(): void
    {
        $this->field('teaser', settings: ['max' => 5]);

        $node = $this->makeArticle(['title' => 'A', 'teaser' => '<p><strong>Hello</strong></p>']);

        $this->assertSame(['<p><strong>Hello</strong></p>'], $this->stored($node, 'teaser'));
        $this->assertInvalid(fn () => $this->writer()->write($node, ['teaser' => '<p>Hello!</p>']), 'teaser');
    }

    public function test_min_length_counts_visible_text_only(): void
    {
        $this->field('teaser', settings: ['min' => 3]);

        $this->assertInvalid(fn () => $this->makeArticle(['title' => 'A', 'teaser' => '<p><strong>ab</strong></p>']), 'teaser');
    }

    public function test_search_finds_the_text(): void
    {
        $this->makeArticle(['title' => 'A', 'content' => '<p>Green lamp</p>']);
        $this->makeArticle(['title' => 'B', 'content' => '<p>Chair</p>']);

        $page = app(ContentNodeQuery::class)->paginate(
            ContentType::findByKey('article'),
            new NodeQueryParams(search: 'lamp', searchFields: ['content'])
        );

        $this->assertSame(['A'], collect($page->items())->map(fn (ContentNode $n) => $n->values['title'])->all());
    }

    public function test_multiple_field_drops_empty_documents(): void
    {
        $this->field('notes', ['is_multiple' => true]);

        $node = $this->makeArticle(['title' => 'A', 'notes' => ['<p>a</p>', '<p></p>']]);

        $this->assertSame(['<p>a</p>'], $this->stored($node, 'notes'));
    }

    public function test_translations_are_independent(): void
    {
        $this->enableLanguages('en', 'uk');
        $node = $this->makeArticle(['title' => 'A', 'content' => '<p>Hello</p>']);

        $this->writer()->write($node, ['content' => '<p>Привіт</p>'], 'uk');

        $this->assertSame(['<p>Hello</p>', '<p>Привіт</p>'], DB::table('content_values_text')
            ->where('content_node_id', $node->id)->where('field_key', 'content')->orderBy('id')->pluck('value')->all());
    }

    public function test_other_types_keep_clearing_empty_strings(): void
    {
        $node = $this->makeArticle(['title' => 'A', 'note' => 'x', 'body' => 'text']);

        $this->writer()->write($node, ['note' => '', 'body' => '']);

        $this->assertSame([], $this->stored($node, 'body'));
        $this->assertSame(0, DB::table('content_values_string')->where('content_node_id', $node->id)->where('field_key', 'note')->count());
    }

    // --- Images ---

    private function image(string $profile = 'image'): File
    {
        $ext = $profile === 'image' ? 'png' : 'pdf';
        $uuid = File::newUuid();

        return File::factory()->create(['uuid' => $uuid, 'profile' => $profile, 'path' => "{$uuid}.{$ext}", 'mime' => $profile === 'image' ? 'image/png' : 'application/pdf']);
    }

    private function img(File $file): string
    {
        return '<img src="/storage/'.$file->path.'">';
    }

    /** @return list<string> */
    private function related(ContentNode $node, ?string $lang = null): array
    {
        return DB::table('media_relations')->where('owner_id', $node->id)->when($lang, fn ($q) => $q->where('lang', $lang))
            ->orderBy('file_uuid')->pluck('file_uuid')->all();
    }

    public function test_a_value_with_only_an_image_is_content(): void
    {
        $this->field('summary', ['is_required' => true]);
        $file = $this->image();

        $node = $this->makeArticle(['title' => 'A', 'summary' => '<p>'.$this->img($file).'</p>']);

        $this->assertCount(1, $this->stored($node, 'summary'));
        $this->assertTrue(app(FieldTypeRegistry::class)->get('richtext')->isBlank('<p></p>'));
    }

    public function test_an_unknown_or_foreign_profile_image_is_rejected(): void
    {
        $missing = '<img src="/storage/'.File::newUuid().'.png">';
        $document = $this->image('document');

        $this->assertInvalid(fn () => $this->makeArticle(['title' => 'A', 'content' => $missing]), 'content');
        $this->assertInvalid(fn () => $this->makeArticle(['title' => 'A', 'content' => '<img src="/storage/'.$document->uuid.'.png">']), 'content');
        $this->assertSame(0, DB::table('media_relations')->count());
    }

    public function test_file_uuids_are_unique_and_cover_lists(): void
    {
        $type = app(FieldTypeRegistry::class)->get('richtext');
        $a = $this->image();
        $b = $this->image();

        $this->assertSame([$a->uuid], $type->fileUuids($this->img($a).$this->img($a)));
        $this->assertSame([$a->uuid, $b->uuid], $type->fileUuids(['<p>'.$this->img($a).'</p>', $this->img($b)]));
        $this->assertSame([], $type->fileUuids(null));
        $this->assertSame([], $type->fileUuids('<img src="https://evil.example/'.$a->path.'">'));
    }

    public function test_relations_follow_the_images_of_the_text(): void
    {
        $a = $this->image();
        $b = $this->image();

        $node = $this->makeArticle(['title' => 'A', 'content' => $this->img($a).$this->img($b).$this->img($b)]);
        $this->assertEqualsCanonicalizing([$a->uuid, $b->uuid], $this->related($node));
        $this->assertSame(['content'], DB::table('media_relations')->where('owner_id', $node->id)->pluck('role')->unique()->all());

        $this->writer()->write($node, ['content' => $this->img($a)]);
        $this->assertSame([$a->uuid], $this->related($node));
        $this->assertNotNull(File::findByUuid($b->uuid));
    }

    public function test_relations_are_kept_per_language(): void
    {
        $this->enableLanguages('uk');
        $a = $this->image();
        $node = $this->makeArticle(['title' => 'A', 'content' => '<p>text</p>']);

        $this->writer()->write($node, ['content' => $this->img($a)], 'uk');

        $this->assertSame([$a->uuid], $this->related($node, 'uk'));
        $this->assertSame([], $this->related($node, 'default'));
    }

    public function test_deleting_the_node_clears_the_relations(): void
    {
        $node = $this->makeArticle(['title' => 'A', 'content' => $this->img($this->image())]);

        $node->delete();

        $this->assertSame(0, DB::table('media_relations')->count());
    }
}
