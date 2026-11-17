<?php

namespace Webtack\Ecms\Tests\Feature\Content;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Webtack\Ecms\Content\ContentNodeCreator;
use Webtack\Ecms\Content\ContentNodeQuery;
use Webtack\Ecms\Content\ContentValueReader;
use Webtack\Ecms\Content\ContentValueWriter;
use Webtack\Ecms\Content\NodeQueryParams;
use Webtack\Ecms\Content\ValueOwner;
use Webtack\Ecms\Models\ContentField;
use Webtack\Ecms\Models\ContentType;
use Webtack\Ecms\Models\File;
use Webtack\Ecms\Models\MediaRelation;
use Webtack\Ecms\Tests\Feature\Content\Concerns\BuildsArticleType;
use Webtack\Ecms\Tests\TestCase;

/**
 * Writing and reading the values of a content type itself (metadata and section fields).
 */
class TypeValuesTest extends TestCase
{
    use BuildsArticleType, RefreshDatabase;

    private ContentType $news;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        $this->news = ContentType::create(['key' => 'news', 'name' => 'News']);
    }

    private function write(array $values, string $lang = 'default', ?ContentType $type = null): void
    {
        app(ContentValueWriter::class)->write(ValueOwner::forType($type ?? $this->news), $values, $lang);
    }

    private function read(?array $keys = null, string $lang = 'default', ?ContentType $type = null): array
    {
        return app(ContentValueReader::class)->loadForType($type ?? $this->news, $keys, $lang);
    }

    public function test_type_values_fall_back_to_the_base_language_in_the_fallback_mode(): void
    {
        $this->enableLanguages('en');
        $this->write(['section_title' => 'Новини']);

        $strict = app(ContentValueReader::class)->loadForType($this->news, ['section_title'], 'en');
        $fallback = app(ContentValueReader::class)->loadForType($this->news, ['section_title'], 'en', fallback: true);

        $this->assertNull($strict['section_title']);
        $this->assertSame('Новини', $fallback['section_title']);
    }

    private function png(): File
    {
        $uuid = File::newUuid();

        return File::factory()->create(['uuid' => $uuid, 'profile' => 'image', 'mime' => 'image/png', 'path' => "{$uuid}.png", 'size' => 1000]);
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

    public function test_a_type_value_is_stored_without_a_node(): void
    {
        $this->write(['section_title' => 'Новини', 'meta_description' => 'About news']);

        $row = DB::table('content_values_string')->where('field_key', 'section_title')->sole();
        $this->assertSame('type', $row->entity);
        $this->assertNull($row->content_node_id);
        $this->assertNull($row->content_node_uuid);
        $this->assertSame('news', $row->content_type);
        $this->assertSame('default', $row->lang);
        $this->assertSame('About news', DB::table('content_values_text')->where('field_key', 'meta_description')->value('value'));
    }

    public function test_a_node_value_keeps_its_node_columns(): void
    {
        $node = $this->makeArticle(['title' => 'Hello']);

        $row = DB::table('content_values_string')->where('field_key', 'title')->sole();
        $this->assertSame('node', $row->entity);
        $this->assertSame($node->id, $row->content_node_id);
        $this->assertSame($node->uuid, $row->content_node_uuid);
    }

    public function test_translations_of_a_type_value_are_independent(): void
    {
        $this->enableLanguages('en');

        $this->write(['section_title' => 'Новини']);
        $this->write(['section_title' => 'News'], 'en');
        $this->write(['section_title' => 'World news'], 'en');

        $this->assertSame('Новини', $this->read(['section_title'])['section_title']);
        $this->assertSame('World news', $this->read(['section_title'], 'en')['section_title']);
        $this->assertSame(2, DB::table('content_values_string')->where('field_key', 'section_title')->count());
    }

    public function test_an_empty_value_clears_the_stored_one(): void
    {
        $this->write(['section_title' => 'Новини']);
        $this->write(['section_title' => '']);

        $this->assertNull($this->read(['section_title'])['section_title']);
        $this->assertSame(0, DB::table('content_values_string')->where('field_key', 'section_title')->count());
    }

    public function test_a_missing_value_is_null_and_the_base_language_is_not_substituted(): void
    {
        $this->enableLanguages('en');
        $this->write(['section_title' => 'Новини']);

        $this->assertSame('Новини', $this->read(['section_title'])['section_title']);
        $this->assertNull($this->read(['section_title'], 'en')['section_title']);
        $this->assertNull($this->read(['section_description'])['section_description']);
    }

    public function test_reading_without_keys_returns_every_field_the_type_holds(): void
    {
        $this->write(['meta_title' => 'SEO']);

        $this->assertSame(
            ['meta_title', 'meta_description', 'meta_image', 'section_title', 'section_description', 'section_cover'],
            array_keys($this->read()),
        );
        $this->assertSame('SEO', $this->read()['meta_title']);
    }

    public function test_page_types_hold_meta_values_only(): void
    {
        $page = ContentType::create(['key' => 'landing', 'name' => 'Landing', 'kind' => 'page']);

        $this->write(['meta_description' => 'About'], type: $page);

        $this->assertSame(['meta_title', 'meta_description', 'meta_image'], array_keys($this->read(type: $page)));
        $this->assertSame('About', $this->read(['meta_description'], type: $page)['meta_description']);
        $this->assertInvalid(fn () => $this->write(['section_title' => 'X'], type: $page), 'section_title');
    }

    public function test_block_types_hold_no_type_values(): void
    {
        $block = ContentType::create(['key' => 'hero', 'name' => 'Hero', 'kind' => 'block']);

        $this->assertInvalid(fn () => $this->write(['meta_title' => 'SEO'], type: $block), 'meta_title');
        $this->assertInvalid(fn () => $this->read(['meta_title'], type: $block), 'fields');
        $this->assertSame([], $this->read(type: $block));
    }

    public function test_a_type_does_not_hold_the_title_or_ordinary_fields(): void
    {
        ContentField::create(['content_type' => 'news', 'key' => 'visits', 'name' => 'Visits', 'type' => 'integer']);

        $this->assertInvalid(fn () => $this->write(['title' => 'X']), 'title');
        $this->assertInvalid(fn () => $this->write(['visits' => 5]), 'visits');
        $this->assertInvalid(fn () => $this->write(['missing' => 5]), 'missing');
        $this->assertInvalid(fn () => $this->read(['title']), 'fields');
        $this->assertSame(0, DB::table('content_values_string')->count());
    }

    public function test_a_node_does_not_hold_section_fields(): void
    {
        $node = app(ContentNodeCreator::class)->create($this->news, ['title' => 'Hello'], authorId: $this->authorId());

        $this->assertInvalid(fn () => app(ContentValueWriter::class)->write($node, ['section_title' => 'X']), 'section_title');
        $this->assertInvalid(fn () => app(ContentValueReader::class)->load([$node], ['section_title']), 'fields');
        $this->assertInvalid(fn () => app(ContentNodeCreator::class)->create($this->news, ['title' => 'B', 'section_cover' => 'x'], authorId: $this->authorId()), 'section_cover');
        $this->assertArrayNotHasKey('section_title', app(ContentValueReader::class)->load([$node])[$node->id]);
    }

    public function test_meta_values_of_a_node_and_of_its_type_are_independent(): void
    {
        $node = app(ContentNodeCreator::class)->create($this->news, ['title' => 'Hello', 'meta_title' => 'Node SEO'], authorId: $this->authorId());
        $this->write(['meta_title' => 'Type SEO']);
        app(ContentValueWriter::class)->write($node, ['meta_title' => 'Node SEO 2']);

        $this->assertSame('Type SEO', $this->read(['meta_title'])['meta_title']);
        $this->assertSame('Node SEO 2', app(ContentValueReader::class)->load([$node], ['meta_title'])[$node->id]['meta_title']);
        $this->assertSame(2, DB::table('content_values_string')->where('field_key', 'meta_title')->count());
    }

    public function test_type_values_of_one_type_do_not_leak_into_another(): void
    {
        $blog = ContentType::create(['key' => 'blog', 'name' => 'Blog']);
        $this->write(['section_title' => 'News']);

        $this->assertNull($this->read(['section_title'], type: $blog)['section_title']);
    }

    public function test_the_validation_of_the_field_type_applies(): void
    {
        $this->assertInvalid(fn () => $this->write(['meta_title' => str_repeat('a', 600)]), 'meta_title');
        $this->assertSame(0, DB::table('content_values_string')->count());
    }

    public function test_node_reading_does_not_run_more_queries_than_before(): void
    {
        $nodes = [];
        foreach (range(1, 5) as $i) {
            $nodes[] = app(ContentNodeCreator::class)->create($this->news, ['title' => "N{$i}"], "n-{$i}", authorId: $this->authorId());
        }
        $this->write(['meta_title' => 'Type SEO']);

        DB::enableQueryLog();
        app(ContentValueReader::class)->load($nodes, ['title', 'meta_title']);
        $withFive = count(DB::getQueryLog());
        DB::flushQueryLog();
        app(ContentValueReader::class)->load(array_slice($nodes, 0, 1), ['title', 'meta_title']);

        $this->assertSame($withFive, count(DB::getQueryLog()));
        // One query for the fields and one per value table (both fields are in the string table).
        $this->assertSame(2, $withFive);
    }

    public function test_a_file_of_a_type_value_is_bound_replaced_and_cleared(): void
    {
        $a = $this->png();
        $b = $this->png();

        $this->write(['section_cover' => $a->path]);
        $relation = MediaRelation::query()->sole();
        $this->assertSame([$a->uuid, 'content_type', $this->news->id, 'section_cover', 'default'], [$relation->file_uuid, $relation->owner_type, $relation->owner_id, $relation->role, $relation->lang]);

        $this->write(['section_cover' => $b->path]);
        $this->assertSame([$b->uuid], MediaRelation::query()->pluck('file_uuid')->all());

        $this->write(['section_cover' => null]);
        $this->assertSame(0, MediaRelation::query()->count());
        $this->assertTrue(File::query()->whereKey([$a->getKey(), $b->getKey()])->count() === 2);
    }

    public function test_a_file_relation_of_a_type_and_of_a_node_are_separate(): void
    {
        $file = $this->png();
        $node = app(ContentNodeCreator::class)->create($this->news, ['title' => 'Hello', 'meta_image' => $file->path], authorId: $this->authorId());
        $this->write(['meta_image' => $file->path]);

        $this->assertEqualsCanonicalizing(
            [['content_node', $node->id], ['content_type', $this->news->id]],
            MediaRelation::query()->get()->map(fn ($r) => [$r->owner_type, $r->owner_id])->all(),
        );
    }

    public function test_deleting_a_type_forgets_its_file_relations_and_keeps_the_files(): void
    {
        $file = $this->png();
        $other = ContentType::create(['key' => 'blog', 'name' => 'Blog']);
        $this->write(['section_cover' => $file->path, 'meta_image' => $file->path]);
        $this->write(['section_cover' => $file->path], type: $other);

        $this->news->delete();

        $this->assertSame([$other->id], MediaRelation::query()->pluck('owner_id')->all());
        $this->assertSame(0, DB::table('content_values_string')->where('content_type', 'news')->count());
        $this->assertTrue(File::query()->where('uuid', $file->uuid)->exists());
    }

    public function test_type_values_do_not_affect_filtering_sorting_and_searching_nodes(): void
    {
        ContentField::create(['content_type' => 'news', 'key' => 'visits', 'name' => 'Visits', 'type' => 'integer', 'is_required' => false]);
        foreach ([['Apple', 3], ['Banana', 1]] as $i => [$title, $visits]) {
            app(ContentNodeCreator::class)->create($this->news, ['title' => $title, 'visits' => $visits], "n-{$i}", authorId: $this->authorId());
        }
        // A type value in the same field as the nodes would use, and a raw type row for a node field.
        $this->write(['meta_title' => 'Apple Zebra']);
        DB::table('content_values_integer')->insert([
            'entity' => 'type', 'content_node_id' => null, 'content_node_uuid' => null,
            'content_type' => 'news', 'field_key' => 'visits', 'lang' => 'default', 'weight' => 0, 'value' => 99,
        ]);
        $query = app(ContentNodeQuery::class);
        $titles = fn (NodeQueryParams $p) => collect($query->paginate($this->news, $p)->items())->map(fn ($n) => $n->values['title'])->all();

        $this->assertSame(['Banana', 'Apple'], $titles(new NodeQueryParams(sort: ['field' => 'visits'])));
        $this->assertSame([], $titles(new NodeQueryParams(filters: [['field' => 'visits', 'operator' => 'gte', 'value' => 50]])));
        $this->assertSame([], $titles(new NodeQueryParams(filters: [['field' => 'meta_title', 'operator' => 'eq', 'value' => 'Apple Zebra']])));
        $this->assertSame([], $titles(new NodeQueryParams(search: 'Zebra', searchFields: ['title', 'meta_title'])));
        $this->assertSame(2, $query->paginate($this->news, new NodeQueryParams)->total());
    }

    public function test_a_field_with_type_values_cannot_be_deleted(): void
    {
        $field = ContentField::create(['content_type' => 'news', 'key' => 'visits', 'name' => 'Visits', 'type' => 'integer', 'is_required' => false]);
        DB::table('content_values_integer')->insert([
            'entity' => 'type', 'content_node_id' => null, 'content_node_uuid' => null,
            'content_type' => 'news', 'field_key' => 'visits', 'lang' => 'default', 'weight' => 0, 'value' => 1,
        ]);

        $this->expectException(ValidationException::class);

        try {
            $field->delete();
        } finally {
            $this->assertTrue(ContentField::whereKey($field->id)->exists());
        }
    }

    public function test_node_queries_and_list_settings_do_not_offer_type_fields(): void
    {
        $query = app(ContentNodeQuery::class);

        foreach ([
            new NodeQueryParams(fields: ['title', 'section_title']),
            new NodeQueryParams(filters: [['field' => 'section_title', 'operator' => 'eq', 'value' => 'x']]),
            new NodeQueryParams(sort: ['field' => 'section_cover']),
        ] as $params) {
            try {
                $query->paginate($this->news, $params);
                $this->fail('A type field must not be queryable on nodes.');
            } catch (ValidationException) {
                $this->addToAssertionCount(1);
            }
        }

        $this->assertInvalid(fn () => $this->news->update(['settings' => ['list' => ['visible' => ['section_title']]]]), 'settings');
        $this->news->update(['settings' => ['list' => ['visible' => ['title', 'meta_title']]]]);
    }
}
