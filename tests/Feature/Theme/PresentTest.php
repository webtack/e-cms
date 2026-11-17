<?php

namespace Webtack\Ecms\Tests\Feature\Theme;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Webtack\Ecms\Content\ContentNodeCreator;
use Webtack\Ecms\Content\ContentValueReader;
use Webtack\Ecms\Content\FieldTypeRegistry;
use Webtack\Ecms\Content\PresentContext;
use Webtack\Ecms\Files\FileDescriptions;
use Webtack\Ecms\Models\ContentField;
use Webtack\Ecms\Models\ContentType;
use Webtack\Ecms\Models\File;
use Webtack\Ecms\Models\Vocabulary;
use Webtack\Ecms\Taxonomy\TermWriter;
use Webtack\Ecms\Tests\Feature\Content\Concerns\BuildsArticleType;
use Webtack\Ecms\Tests\TestCase;

/**
 * The public form of a field value (`FieldType::present`) for every type that changes it, and the number of
 * queries a list of values costs.
 */
class PresentTest extends TestCase
{
    use BuildsArticleType, RefreshDatabase;

    private const ID = 'dQw4w9WgXcQ';

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
    }

    /** @param array<string, mixed> $extra */
    private function field(string $type, array $extra = [], ?array $settings = null): ContentField
    {
        $this->articleTypeOnce();

        return ContentField::create(['content_type' => 'article', 'key' => 'f_'.uniqid(), 'name' => 'F', 'type' => $type, 'settings' => $settings, ...$extra]);
    }

    private function articleTypeOnce(): void
    {
        ContentType::findByKey('article') ?? $this->articleType();
    }

    private function present(string $type, mixed $value, ?ContentField $field = null, string $lang = 'default'): mixed
    {
        return app(FieldTypeRegistry::class)->get($type)->present(
            $value,
            $field ?? $this->field($type),
            new PresentContext(app(ContentValueReader::class), $lang),
        );
    }

    private function imageFile(): File
    {
        $uuid = File::newUuid();

        return File::factory()->create(['uuid' => $uuid, 'profile' => 'image', 'mime' => 'image/png', 'path' => "{$uuid}.png", 'size' => 1000]);
    }

    public function test_types_without_references_return_the_value_as_it_is(): void
    {
        $this->assertSame('text', $this->present('string', 'text'));
        $this->assertSame(5, $this->present('integer', 5));
        $this->assertTrue($this->present('boolean', true));
        $this->assertSame('2.50', $this->present('decimal', '2.50'));
    }

    public function test_a_file_reaches_the_template_in_the_context_language_with_fallback_to_the_original(): void
    {
        $this->enableLanguages('en', 'pl');
        $uuid = File::newUuid();
        File::factory()->create(['uuid' => $uuid, 'profile' => 'image', 'mime' => 'image/png', 'path' => "{$uuid}.png", 'original_name' => 'cat.png', 'description' => 'Кіт', 'size' => 1000]);
        $nameOnly = File::newUuid();
        File::factory()->create(['uuid' => $nameOnly, 'profile' => 'image', 'mime' => 'image/png', 'path' => "{$nameOnly}.png", 'original_name' => 'dog.png', 'description' => 'Пес', 'size' => 1000]);
        $descriptions = app(FileDescriptions::class);
        $descriptions->save($uuid, 'en', 'A cat', 'Cat');
        $descriptions->save($nameOnly, 'en', null, 'Dog');
        $field = $this->field('image');

        // Additional language: the translated values; a missing translated value falls back to the original one.
        $this->assertSame(['url' => "/storage/{$uuid}.png", 'name' => 'Cat', 'description' => 'A cat'], $this->present('image', "{$uuid}.png", $field, 'en'));
        $this->assertSame('Dog', $this->present('image', "{$nameOnly}.png", $field, 'en')['name']);
        $this->assertSame('Пес', $this->present('image', "{$nameOnly}.png", $field, 'en')['description']);
        // A language without translations and the base language read the original name and the base description.
        $this->assertSame(['url' => "/storage/{$uuid}.png", 'name' => 'cat.png', 'description' => 'Кіт'], $this->present('image', "{$uuid}.png", $field, 'pl'));
        $this->assertSame(['url' => "/storage/{$uuid}.png", 'name' => 'cat.png', 'description' => 'Кіт'], $this->present('image', "{$uuid}.png", $field));
        // The template helper `file()` is the same data.
        $context = new PresentContext(app(ContentValueReader::class), 'en');
        $this->assertSame(['Cat', 'A cat'], [$context->file($uuid)['name'], $context->file($uuid)['description']]);
    }

    public function test_a_list_of_translated_files_costs_a_fixed_number_of_queries(): void
    {
        $this->enableLanguages('en');
        $uuids = [];

        for ($i = 0; $i < 8; $i++) {
            $uuids[] = $this->imageFile()->uuid;
        }

        app(FileDescriptions::class)->saveMany(array_fill_keys($uuids, ['name' => 'N', 'description' => 'D']), 'en');
        $context = new PresentContext(app(ContentValueReader::class), 'en');

        DB::enableQueryLog();
        $context->preloadFiles($uuids);
        array_map(fn (string $uuid) => $context->file($uuid), $uuids);
        $queries = count(DB::getQueryLog());
        DB::disableQueryLog();

        $this->assertSame(2, $queries);
    }

    public function test_an_image_and_a_file_have_url_name_and_description(): void
    {
        $uuid = File::newUuid();
        File::factory()->create(['uuid' => $uuid, 'profile' => 'document', 'mime' => 'application/pdf', 'path' => "{$uuid}.pdf", 'original_name' => 'price.pdf', 'description' => 'Price list', 'size' => 1000]);
        $bare = File::newUuid();
        File::factory()->create(['uuid' => $bare, 'profile' => 'image', 'mime' => 'image/png', 'path' => "{$bare}.png", 'original_name' => 'cat.png', 'description' => '  ', 'size' => 1000]);
        $missing = File::newUuid();

        $file = $this->present('file', "{$uuid}.pdf");
        $image = $this->present('image', "{$bare}.png");
        $orphan = $this->present('image', "{$missing}.png");

        $this->assertSame(['url' => Storage::disk('public')->url("{$uuid}.pdf"), 'name' => 'price.pdf', 'description' => 'Price list'], $file);
        $this->assertSame('cat.png', $image['name']);
        $this->assertNull($image['description']);
        $this->assertSame("{$missing}.png", $orphan['name']);
        $this->assertStringEndsWith("{$missing}.png", $orphan['url']);
        $this->assertNull($this->present('image', 'not-a-file'));
    }

    public function test_richtext_is_cleaned_again(): void
    {
        $this->assertSame('<p>Hi</p>', $this->present('richtext', '<p onclick="x()">Hi</p>'));
        $this->assertNull($this->present('richtext', ''));
    }

    public function test_youtube_gives_the_id_the_links_and_the_texts(): void
    {
        $screen = $this->imageFile();

        $item = $this->present('youtube', [
            'url' => 'https://youtu.be/'.self::ID.'?t=10',
            'video_id' => self::ID,
            'title' => 'Clip',
            'description' => 'About',
            'screen' => $screen->path,
        ]);

        $this->assertSame(self::ID, $item['id']);
        $this->assertSame('https://www.youtube.com/watch?v='.self::ID, $item['url']);
        $this->assertSame('https://www.youtube-nocookie.com/embed/'.self::ID, $item['embed']);
        $this->assertSame('Clip', $item['title']);
        $this->assertStringEndsWith($screen->path, $item['screen']);
    }

    public function test_youtube_never_builds_the_embed_from_an_invalid_id(): void
    {
        $this->assertNull($this->present('youtube', ['url' => 'x', 'video_id' => '"><script>', 'title' => null]));
        $this->assertNull($this->present('youtube', null));
    }

    public function test_url_gives_the_link_and_the_title_for_valid_links_only(): void
    {
        $this->assertSame(['url' => '/about', 'title' => 'About'], $this->present('url', ['url' => '/about', 'title' => 'About']));
        $this->assertNull($this->present('url', ['url' => 'javascript:alert(1)', 'title' => 'Bad']));
    }

    public function test_a_term_is_its_name_in_the_reading_language(): void
    {
        Vocabulary::factory()->create(['key' => 'colors', 'name' => 'Colors']);
        $term = app(TermWriter::class)->create(Vocabulary::findByKey('colors'), ['translations' => ['default' => ['name' => 'Red']]]);
        $field = $this->field('term', settings: ['vocabulary' => 'colors']);

        $this->assertSame('Red', $this->present('term', $term->uuid, $field));
        $this->assertNull($this->present('term', $term->uuid, $field, 'en'));
    }

    public function test_a_term_name_and_a_node_title_fall_back_to_the_base_language_with_the_fallback(): void
    {
        $this->enableLanguages('en', 'pl');
        Vocabulary::factory()->create(['key' => 'colors', 'name' => 'Colors']);
        $term = app(TermWriter::class)->create(Vocabulary::findByKey('colors'), ['translations' => ['default' => ['name' => 'Червоний'], 'en' => ['name' => 'Red']]]);
        $news = ContentType::create(['key' => 'news', 'name' => 'News', 'slug' => 'news']);
        $story = app(ContentNodeCreator::class)->create($news, ['title' => 'Історія'], slug: 'story', authorId: $this->authorId());

        $english = new PresentContext(app(ContentValueReader::class), 'en', fallback: true);
        $polish = new PresentContext(app(ContentValueReader::class), 'pl', fallback: true);
        $strict = new PresentContext(app(ContentValueReader::class), 'pl');

        $this->assertSame('Red', $english->term($term->uuid));
        $this->assertSame('Червоний', $polish->term($term->uuid));
        $this->assertNull($strict->term($term->uuid));
        $this->assertSame('Історія', $polish->node($story->uuid)['title']);
        $this->assertNull($strict->node($story->uuid)['title']);
    }

    public function test_a_field_label_is_translated_with_a_fallback_to_the_stored_name(): void
    {
        $this->enableLanguages('en', 'pl');
        $field = $this->field('string', ['name' => 'Графік']);
        $field->setLabel('en', 'Schedule');

        $this->assertSame('Schedule', (new PresentContext(app(ContentValueReader::class), 'en', fallback: true))->label($field));
        $this->assertSame('Графік', (new PresentContext(app(ContentValueReader::class), 'pl', fallback: true))->label($field));
        $this->assertSame('Графік', (new PresentContext(app(ContentValueReader::class)))->label($field));
    }

    public function test_a_node_ref_is_the_uuid_title_and_address_of_a_visible_node(): void
    {
        $news = ContentType::create(['key' => 'news', 'name' => 'News', 'slug' => 'news']);
        $page = ContentType::create(['key' => 'static', 'name' => 'Static', 'kind' => 'page']);
        $creator = app(ContentNodeCreator::class);
        $story = $creator->create($news, ['title' => 'Story'], slug: 'story', authorId: $this->authorId());
        $about = $creator->create($page, ['title' => 'About'], slug: 'about', authorId: $this->authorId());
        $field = $this->field('node_ref', settings: ['content_type' => 'news']);

        $this->assertSame(['uuid' => $story->uuid, 'title' => 'Story', 'url' => '/news/story'], $this->present('node_ref', $story->uuid, $field));
        $this->assertSame(['uuid' => $about->uuid, 'title' => 'About', 'url' => '/about'], $this->present('node_ref', $about->uuid, $field));
    }

    public function test_addresses_of_a_type_with_a_slug_of_several_segments_join_all_the_segments(): void
    {
        $type = ContentType::create(['key' => 'institutions', 'name' => 'Institutions', 'slug' => 'common/institutions']);
        $node = app(ContentNodeCreator::class)->create($type, ['title' => 'Library'], slug: 'library', authorId: $this->authorId());

        $this->assertSame(
            ['uuid' => $node->uuid, 'title' => 'Library', 'url' => '/common/institutions/library'],
            $this->present('node_ref', $node->uuid, $this->field('node_ref', settings: ['content_type' => 'institutions'])),
        );
        $this->assertSame(
            ['key' => 'institutions', 'name' => 'Institutions', 'url' => '/common/institutions'],
            $this->present('type_ref', 'institutions', $this->field('type_ref', settings: ['kind' => 'content'])),
        );
    }

    public function test_a_node_ref_to_an_invisible_node_is_null(): void
    {
        $news = ContentType::create(['key' => 'news', 'name' => 'News', 'slug' => 'news']);
        $hidden = app(ContentNodeCreator::class)->create($news, ['title' => 'Hidden'], slug: 'hidden', authorId: $this->authorId(), isActive: false);
        $future = app(ContentNodeCreator::class)->create($news, ['title' => 'Soon'], slug: 'soon', authorId: $this->authorId(), publishedAt: now()->addDay());
        $field = $this->field('node_ref', settings: ['content_type' => 'news']);

        $this->assertNull($this->present('node_ref', $hidden->uuid, $field));
        $this->assertNull($this->present('node_ref', $future->uuid, $field));
        $this->assertNull($this->present('node_ref', '0190a1b2-0000-7000-8000-000000000000', $field));
    }

    public function test_a_node_ref_to_a_block_has_no_address(): void
    {
        $block = ContentType::create(['key' => 'blocks_a', 'name' => 'Blocks', 'kind' => 'block']);
        $node = app(ContentNodeCreator::class)->create($block, ['title' => 'Footer'], slug: 'block-footer', authorId: $this->authorId());
        $field = $this->field('node_ref', settings: ['content_type' => 'blocks_a']);

        $this->assertSame(['uuid' => $node->uuid, 'title' => 'Footer', 'url' => null], $this->present('node_ref', $node->uuid, $field));
    }

    public function test_a_type_ref_is_the_key_the_name_and_the_list_address(): void
    {
        ContentType::create(['key' => 'news', 'name' => 'News', 'slug' => 'news']);
        ContentType::create(['key' => 'static', 'name' => 'Static', 'kind' => 'page']);

        $field = $this->field('type_ref', settings: ['kind' => 'content']);

        $this->assertSame(['key' => 'news', 'name' => 'News', 'url' => '/news'], $this->present('type_ref', 'news', $field));
        $this->assertSame(['key' => 'static', 'name' => 'Static', 'url' => null], $this->present('type_ref', 'static', $field));
        $this->assertNull($this->present('type_ref', 'gone', $field));
    }

    public function test_a_list_of_references_costs_a_fixed_number_of_queries(): void
    {
        $news = ContentType::create(['key' => 'news', 'name' => 'News', 'slug' => 'news']);
        $creator = app(ContentNodeCreator::class);
        $uuids = [];

        for ($i = 0; $i < 12; $i++) {
            $uuids[] = $creator->create($news, ['title' => "Story {$i}"], slug: "story-{$i}", authorId: $this->authorId())->uuid;
        }

        $field = $this->field('node_ref', settings: ['content_type' => 'news']);
        $type = app(FieldTypeRegistry::class)->get('node_ref');
        $context = new PresentContext(app(ContentValueReader::class));

        DB::enableQueryLog();
        $type->preload($uuids, $field, $context);
        $items = array_map(fn (string $uuid) => $type->present($uuid, $field, $context), $uuids);
        $queries = count(DB::getQueryLog());
        DB::disableQueryLog();

        $this->assertCount(12, array_filter($items));
        // Visible nodes, their types, the title values: never one query per value.
        $this->assertLessThanOrEqual(4, $queries);
    }
}
