<?php

namespace Webtack\Ecms\Tests\Feature\Content;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Webtack\Ecms\Content\ContentNodeQuery;
use Webtack\Ecms\Content\ContentValueReader;
use Webtack\Ecms\Content\ContentValueWriter;
use Webtack\Ecms\Content\FieldTypes\YoutubeType;
use Webtack\Ecms\Content\NodeQueryParams;
use Webtack\Ecms\Models\ContentField;
use Webtack\Ecms\Models\ContentNode;
use Webtack\Ecms\Models\File;
use Webtack\Ecms\Models\MediaRelation;
use Webtack\Ecms\Tests\Feature\Content\Concerns\BuildsArticleType;
use Webtack\Ecms\Tests\TestCase;

class YoutubeFieldTypeTest extends TestCase
{
    use BuildsArticleType, RefreshDatabase;

    private const ID = 'dQw4w9WgXcQ';

    private const WATCH = 'https://www.youtube.com/watch?v=dQw4w9WgXcQ';

    protected function setUp(): void
    {
        parent::setUp();

        $this->articleType();
        $this->field('video');
        $this->field('videos', ['is_multiple' => true]);
    }

    /** @param array<string, mixed> $extra */
    private function field(string $key, array $extra = []): void
    {
        ContentField::create(['content_type' => 'article', 'key' => $key, 'name' => ucfirst($key), 'type' => 'youtube', ...$extra]);
    }

    private function screen(string $profile = 'image'): File
    {
        $uuid = File::newUuid();

        return File::factory()->create(['uuid' => $uuid, 'profile' => $profile, 'mime' => 'image/jpeg', 'path' => "{$uuid}.jpg", 'size' => 1000]);
    }

    private function write(ContentNode $node, mixed $value, string $key = 'video'): void
    {
        app(ContentValueWriter::class)->write($node, [$key => $value]);
    }

    private function read(ContentNode $node, string $key = 'video'): mixed
    {
        return app(ContentValueReader::class)->load([$node], [$key])[$node->id][$key];
    }

    private function assertInvalid(callable $call): void
    {
        try {
            $call();
            $this->fail('A ValidationException was expected.');
        } catch (ValidationException) {
            $this->addToAssertionCount(1);
        }
    }

    /** @return list<string> */
    private function related(ContentNode $node): array
    {
        return MediaRelation::query()->where('owner_id', $node->id)->where('role', 'video')->pluck('file_uuid')->all();
    }

    public function test_a_video_is_stored_and_read_back(): void
    {
        $screen = $this->screen();
        $node = $this->makeArticle();

        $this->write($node, ['url' => self::WATCH, 'title' => 'Clip', 'description' => 'About', 'screen' => $screen->path]);

        $this->assertSame([
            'url' => self::WATCH, 'video_id' => self::ID, 'title' => 'Clip', 'description' => 'About', 'screen' => $screen->path,
        ], $this->read($node));
        $this->assertSame(self::WATCH, DB::table('content_values_string')->where('field_key', 'video')->value('value'));
    }

    public function test_the_entered_link_is_kept_in_every_accepted_form(): void
    {
        foreach ([
            'https://youtu.be/dQw4w9WgXcQ?t=10',
            'https://www.youtube.com/embed/dQw4w9WgXcQ',
            'https://www.youtube.com/live/dQw4w9WgXcQ',
            'https://m.youtube.com/watch?v=dQw4w9WgXcQ',
            'https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ',
            'https://www.youtube.com/watch?v=dQw4w9WgXcQ&t=42s',
        ] as $link) {
            $node = $this->makeArticle();
            $this->write($node, $link);

            $this->assertSame($link, $this->read($node)['url'], $link);
            $this->assertSame(self::ID, $this->read($node)['video_id'], $link);
        }
    }

    public function test_a_shorts_link_becomes_a_watch_link_and_keeps_other_parameters(): void
    {
        $node = $this->makeArticle();

        $this->write($node, 'https://www.youtube.com/shorts/dQw4w9WgXcQ');
        $this->assertSame(self::WATCH, $this->read($node)['url']);

        $this->write($node, 'https://www.youtube.com/shorts/dQw4w9WgXcQ?feature=share');
        $this->assertSame(self::WATCH.'&feature=share', $this->read($node)['url']);
    }

    public function test_a_bare_id_becomes_a_watch_link(): void
    {
        $node = $this->makeArticle();

        $this->write($node, self::ID);

        $this->assertSame(self::WATCH, $this->read($node)['url']);
    }

    public function test_the_video_id_comes_from_the_link_not_from_the_input(): void
    {
        $node = $this->makeArticle();

        $this->write($node, ['url' => 'https://youtu.be/dQw4w9WgXcQ?t=10', 'video_id' => 'AAAAAAAAAAA']);

        $this->assertSame(self::ID, $this->read($node)['video_id']);
    }

    public function test_only_a_link_leaves_the_rest_empty(): void
    {
        $node = $this->makeArticle();

        $this->write($node, self::WATCH);

        $this->assertSame(['url' => self::WATCH, 'video_id' => self::ID, 'title' => null, 'description' => null, 'screen' => null], $this->read($node));
    }

    public function test_a_link_over_512_characters_is_rejected(): void
    {
        $node = $this->makeArticle();

        $this->assertInvalid(fn () => $this->write($node, self::WATCH.'&x='.str_repeat('a', 520)));
        $this->assertNull($this->read($node));
    }

    public function test_texts_that_are_not_a_video_link_are_rejected(): void
    {
        $node = $this->makeArticle();

        foreach ([
            'https://www.youtube.com/@channel',
            'https://www.youtube.com/channel/UCxxxxxxxxxxxxxxxxxxxxxx',
            'https://www.youtube.com/playlist?list=PLxxxxxxxxxxxxxxxx',
            'https://www.youtube.com/watch',
            'https://www.youtube.com.evil.example/watch?v=dQw4w9WgXcQ',
            'https://example.com/watch?v=dQw4w9WgXcQ',
            'just text',
        ] as $text) {
            $this->assertInvalid(fn () => $this->write($node, $text));
        }

        $this->assertNull($this->read($node));
    }

    public function test_a_screen_must_be_an_existing_file_of_the_image_profile(): void
    {
        $node = $this->makeArticle();

        $this->assertInvalid(fn () => $this->write($node, ['url' => self::WATCH, 'screen' => File::newUuid().'.jpg']));
        $this->assertInvalid(fn () => $this->write($node, ['url' => self::WATCH, 'screen' => $this->screen('document')->path]));
        $this->assertNull($this->read($node));
    }

    public function test_saving_does_not_use_the_network(): void
    {
        Http::fake();
        $node = $this->makeArticle();

        $this->write($node, ['url' => self::WATCH, 'title' => 'Clip']);

        Http::assertNothingSent();
    }

    public function test_a_required_field_needs_a_link(): void
    {
        $node = $this->makeArticle();
        $this->field('required_video', ['is_required' => true]);

        $this->assertInvalid(fn () => $this->write($node, ['url' => '', 'title' => 'Only a title'], 'required_video'));
        $this->assertInvalid(fn () => $this->write($node, null, 'required_video'));
    }

    public function test_a_multiple_field_keeps_order_and_data_of_each_video(): void
    {
        $node = $this->makeArticle();

        $this->write($node, [
            ['url' => 'https://youtu.be/aaaaaaaaaaa', 'title' => 'A'],
            ['url' => 'https://youtu.be/bbbbbbbbbbb'],
        ], 'videos');

        $videos = $this->read($node, 'videos');
        $this->assertSame(['aaaaaaaaaaa', 'bbbbbbbbbbb'], array_column($videos, 'video_id'));
        $this->assertSame(['A', null], array_column($videos, 'title'));
    }

    public function test_contains_finds_a_video_by_id_in_any_form(): void
    {
        $short = $this->makeArticle(['title' => 'Short']);
        $long = $this->makeArticle(['title' => 'Long']);
        $other = $this->makeArticle(['title' => 'Other']);
        $this->write($short, 'https://youtu.be/dQw4w9WgXcQ?t=10');
        $this->write($long, self::WATCH);
        $this->write($other, 'https://youtu.be/aaaaaaaaaaa');

        $page = app(ContentNodeQuery::class)->paginate(
            $short->contentType,
            new NodeQueryParams(fields: ['title'], filters: [['field' => 'video', 'operator' => 'contains', 'value' => self::ID]]),
        );

        $this->assertEqualsCanonicalizing(['Short', 'Long'], collect($page->items())->map(fn ($item) => $item->values['title'] ?? $item['values']['title'] ?? null)->all());
    }

    public function test_equality_is_not_supported(): void
    {
        $node = $this->makeArticle();

        $this->assertInvalid(fn () => app(ContentNodeQuery::class)->paginate(
            $node->contentType,
            new NodeQueryParams(filters: [['field' => 'video', 'operator' => 'eq', 'value' => self::WATCH]]),
        ));
    }

    public function test_no_new_table_is_needed(): void
    {
        $this->assertFalse(Schema::hasTable('content_values_youtube'));
        $this->assertSame('content_values_string', app(YoutubeType::class)->table());
    }

    public function test_the_screen_is_tracked_in_media_relations(): void
    {
        [$first, $second] = [$this->screen(), $this->screen()];
        $node = $this->makeArticle();

        $this->write($node, ['url' => self::WATCH, 'screen' => $first->path]);
        $this->assertSame([$first->uuid], $this->related($node));

        $this->write($node, ['url' => self::WATCH, 'screen' => $second->path]);
        $this->assertSame([$second->uuid], $this->related($node));
        $this->assertNotNull(File::findByUuid($first->uuid));

        $this->write($node, ['url' => self::WATCH]);
        $this->assertSame([], $this->related($node));

        $this->write($node, ['url' => self::WATCH, 'screen' => $first->path]);
        $this->write($node, null);
        $this->assertSame([], $this->related($node));
        $this->assertNotNull(File::findByUuid($first->uuid));
    }

    public function test_every_screen_of_a_multiple_field_is_tracked(): void
    {
        [$first, $second] = [$this->screen(), $this->screen()];
        $node = $this->makeArticle();

        $this->write($node, [
            ['url' => 'https://youtu.be/aaaaaaaaaaa', 'screen' => $first->path],
            ['url' => 'https://youtu.be/bbbbbbbbbbb', 'screen' => $second->path],
        ], 'videos');

        $this->assertEqualsCanonicalizing([$first->uuid, $second->uuid], MediaRelation::query()->where('owner_id', $node->id)->where('role', 'videos')->pluck('file_uuid')->all());
    }
}
