<?php

namespace Webtack\Ecms\Tests\Feature\Admin;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Webtack\Ecms\Content\ContentNodeCreator;
use Webtack\Ecms\Content\ContentValueReader;
use Webtack\Ecms\Models\ContentField;
use Webtack\Ecms\Models\ContentNode;
use Webtack\Ecms\Models\ContentType;
use Webtack\Ecms\Models\File;
use Webtack\Ecms\Tests\Fixtures\User;
use Webtack\Ecms\Tests\TestCase;

/**
 * The `youtube` field in the node form and the node table.
 */
class ContentNodeYoutubeTest extends TestCase
{
    use RefreshDatabase;

    private const WATCH = 'https://www.youtube.com/watch?v=dQw4w9WgXcQ';

    private ContentType $type;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        $this->type = ContentType::create(['key' => 'news', 'name' => 'News']);
        ContentField::create(['content_type' => 'news', 'key' => 'video', 'name' => 'Video', 'type' => 'youtube', 'weight' => 10]);
        ContentField::create(['content_type' => 'news', 'key' => 'videos', 'name' => 'Videos', 'type' => 'youtube', 'is_multiple' => true, 'weight' => 20]);
        $this->type->update(['settings' => ['list' => ['visible' => ['video', 'videos']]]]);
    }

    private function admin(): static
    {
        return $this->actingAs(User::factory()->create());
    }

    private function screen(string $name = 'shot.jpg'): File
    {
        $uuid = File::newUuid();

        return File::factory()->create(['uuid' => $uuid, 'profile' => 'image', 'mime' => 'image/jpeg', 'path' => "{$uuid}.jpg", 'original_name' => $name, 'size' => 2048]);
    }

    /** @param array<string, mixed> $values */
    private function node(array $values, string $title = 'A'): ContentNode
    {
        return app(ContentNodeCreator::class)->create($this->type, ['title' => $title, ...$values], authorId: $this->authorId());
    }

    public function test_the_edit_form_gets_the_composed_value_and_the_screen_file(): void
    {
        $screen = $this->screen('Preview.jpg');
        $second = $this->screen('Second.jpg');
        $node = $this->node([
            'video' => ['url' => self::WATCH, 'title' => 'Clip', 'screen' => $screen->path],
            'videos' => [['url' => 'https://youtu.be/aaaaaaaaaaa', 'screen' => $second->path], ['url' => 'https://youtu.be/bbbbbbbbbbb']],
        ]);

        $this->admin()->get("/admin/content-types/news/nodes/{$node->uuid}/edit")
            ->assertInertia(fn (Assert $page) => $page
                ->where('values.video', ['url' => self::WATCH, 'video_id' => 'dQw4w9WgXcQ', 'title' => 'Clip', 'description' => null, 'screen' => $screen->path])
                ->where('values.videos.0.video_id', 'aaaaaaaaaaa')
                ->where('values.videos.1.screen', null)
                ->where('files', [
                    $screen->path => [
                        'uuid' => $screen->uuid, 'name' => 'Preview.jpg', 'description' => null, 'mime' => 'image/jpeg', 'size' => 2048,
                        'url' => Storage::disk('public')->url($screen->path),
                        'preview' => image_style(Storage::disk('public')->url($screen->path), 'thumb'),
                    ],
                    $second->path => [
                        'uuid' => $second->uuid, 'name' => 'Second.jpg', 'description' => null, 'mime' => 'image/jpeg', 'size' => 2048,
                        'url' => Storage::disk('public')->url($second->path),
                        'preview' => image_style(Storage::disk('public')->url($second->path), 'thumb'),
                    ],
                ]));
    }

    public function test_the_form_field_carries_the_upload_limits_of_the_image_profile(): void
    {
        $this->admin()->get('/admin/content-types/news/nodes/create')
            ->assertInertia(fn (Assert $page) => $page
                ->where('fields', function ($fields) {
                    $field = collect($fields)->firstWhere('type', 'youtube');

                    return $field['settings']['max_size'] === 30 * 1024
                        && $field['settings']['mimes'] === array_keys(config('files.profiles.image.mimes'));
                }));
    }

    public function test_the_form_saves_the_composed_value(): void
    {
        $screen = $this->screen();
        $node = $this->node([]);

        $this->admin()->put("/admin/content-types/news/nodes/{$node->uuid}", [
            'published_at' => '2026-01-01T00:00', 'slug' => $node->slug,
            'values' => ['title' => 'A', 'video' => ['url' => 'https://youtu.be/dQw4w9WgXcQ?t=5', 'title' => 'Edited', 'description' => 'About', 'screen' => $screen->path]],
        ])->assertRedirect();

        $this->assertSame(
            ['url' => 'https://youtu.be/dQw4w9WgXcQ?t=5', 'video_id' => 'dQw4w9WgXcQ', 'title' => 'Edited', 'description' => 'About', 'screen' => $screen->path],
            app(ContentValueReader::class)->load([$node], ['video'])[$node->id]['video'],
        );
    }

    public function test_a_bad_link_is_an_error_at_the_field(): void
    {
        $node = $this->node([]);

        $this->admin()->put("/admin/content-types/news/nodes/{$node->uuid}", [
            'published_at' => '2026-01-01T00:00', 'slug' => $node->slug,
            'values' => ['title' => 'A', 'video' => ['url' => 'https://www.youtube.com/@channel']],
        ])->assertSessionHasErrors('values.video');
    }

    public function test_the_table_shows_the_title_or_the_link_and_the_column_is_not_sortable(): void
    {
        $this->node([
            'video' => ['url' => self::WATCH, 'title' => 'Clip'],
            'videos' => [['url' => 'https://youtu.be/aaaaaaaaaaa', 'title' => 'First'], ['url' => 'https://youtu.be/bbbbbbbbbbb']],
        ]);

        $this->admin()->get('/admin/content-types/news/nodes')
            ->assertInertia(fn (Assert $page) => $page
                ->where('table.rows.0.video', 'Clip')
                ->where('table.rows.0.videos', ['First', 'https://youtu.be/bbbbbbbbbbb'])
                ->where('columns.1.sortable', false));
    }
}
