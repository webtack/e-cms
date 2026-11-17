<?php

namespace Webtack\Ecms\Tests\Feature\Admin;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;
use Webtack\Ecms\Content\ContentNodeCreator;
use Webtack\Ecms\Models\ContentField;
use Webtack\Ecms\Models\ContentNode;
use Webtack\Ecms\Models\ContentType;
use Webtack\Ecms\Models\File;
use Webtack\Ecms\Models\MediaRelation;
use Webtack\Ecms\Models\Role;
use Webtack\Ecms\Tests\Fixtures\User;
use Webtack\Ecms\Tests\TestCase;

class ContentNodeFilesTest extends TestCase
{
    use RefreshDatabase;

    private ContentType $type;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        $this->type = ContentType::create(['key' => 'news', 'name' => 'News']);
        ContentField::create(['content_type' => 'news', 'key' => 'cover', 'name' => 'Cover', 'type' => 'image', 'weight' => 10, 'settings' => ['mimes' => ['image/png'], 'max_size' => 1024]]);
        ContentField::create(['content_type' => 'news', 'key' => 'manual', 'name' => 'Manual', 'type' => 'file', 'weight' => 20]);
        ContentField::create(['content_type' => 'news', 'key' => 'gallery', 'name' => 'Gallery', 'type' => 'image', 'is_multiple' => true, 'weight' => 30]);
    }

    private function admin(): static
    {
        return $this->actingAs(User::factory()->create());
    }

    private function png(string $name = 'cover.png'): File
    {
        $uuid = File::newUuid();

        return File::factory()->create(['uuid' => $uuid, 'profile' => 'image', 'mime' => 'image/png', 'path' => "{$uuid}.png", 'original_name' => $name, 'size' => 2048]);
    }

    private function pdf(string $name = 'manual.pdf'): File
    {
        $uuid = File::newUuid();

        return File::factory()->create(['uuid' => $uuid, 'profile' => 'document', 'path' => "{$uuid}.pdf", 'original_name' => $name]);
    }

    /** @param array<string, mixed> $values */
    private function node(array $values, string $title = 'A'): ContentNode
    {
        return app(ContentNodeCreator::class)->create($this->type, ['title' => $title, ...$values], authorId: $this->authorId());
    }

    public function test_the_form_fields_carry_the_effective_file_settings(): void
    {
        $this->admin()->get('/admin/content-types/news/nodes/create')
            ->assertInertia(fn (Assert $page) => $page
                ->where('fields', function ($fields) {
                    $fields = collect($fields)->keyBy('key');

                    return $fields['cover']['type'] === 'image'
                        && $fields['cover']['settings'] === ['mimes' => ['image/png'], 'max_size' => 1024]
                        && $fields['manual']['type'] === 'file'
                        && $fields['manual']['settings']['max_size'] === 30 * 1024
                        && $fields['manual']['settings']['mimes'] === array_keys(config('files.profiles.document.mimes'))
                        && $fields['gallery']['settings']['max_size'] === 30 * 1024;
                }));
    }

    public function test_the_edit_form_gets_the_names_and_urls_of_the_stored_files(): void
    {
        $cover = $this->png('Hello cover.png');
        $manual = $this->pdf('Guide.pdf');
        $node = $this->node(['cover' => $cover->path, 'manual' => $manual->path]);

        $this->admin()->get("/admin/content-types/news/nodes/{$node->uuid}/edit")
            ->assertInertia(fn (Assert $page) => $page
                ->where('values.cover', $cover->path)
                ->where('files', [
                    $cover->path => [
                        'uuid' => $cover->uuid, 'name' => 'Hello cover.png', 'description' => null, 'mime' => 'image/png', 'size' => 2048,
                        'url' => Storage::disk('public')->url($cover->path),
                        'preview' => image_style(Storage::disk('public')->url($cover->path), 'thumb'),
                    ],
                    $manual->path => [
                        'uuid' => $manual->uuid, 'name' => 'Guide.pdf', 'description' => null, 'mime' => 'application/pdf', 'size' => $manual->size,
                        'url' => Storage::disk('public')->url($manual->path),
                        'preview' => null,
                    ],
                ]));
    }

    public function test_a_file_missing_in_the_library_shows_its_stored_value(): void
    {
        $cover = $this->png();
        $node = $this->node(['cover' => $cover->path]);
        DB::table('media_relations')->delete();
        DB::table('media')->delete();

        $this->admin()->get("/admin/content-types/news/nodes/{$node->uuid}/edit")
            ->assertInertia(fn (Assert $page) => $page->where('files', [
                $cover->path => ['uuid' => $cover->uuid, 'name' => $cover->path, 'description' => null, 'mime' => '', 'size' => 0, 'url' => Storage::disk('public')->url($cover->path), 'preview' => null],
            ]));
    }

    public function test_files_are_loaded_with_a_single_query_regardless_of_their_number(): void
    {
        $files = collect(range(1, 6))->map(fn () => $this->png());
        $node = $this->node(['gallery' => $files->map->path->all()]);

        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->admin()->get("/admin/content-types/news/nodes/{$node->uuid}/edit")->assertOk();
        $queries = collect(DB::getQueryLog())->filter(fn (array $q) => str_contains($q['query'], 'from "media"'))->count();
        DB::disableQueryLog();

        $this->assertSame(1, $queries);
    }

    public function test_the_list_shows_file_names_and_the_query_count_does_not_grow(): void
    {
        ContentType::query()->where('key', 'news')->update(['settings' => ['list' => ['visible' => ['cover', 'manual', 'gallery']]]]);
        // Warm-up: the first request of a process also reads the cached app settings; the counted ones must not.
        $this->admin()->get('/admin/content-types/news/nodes')->assertOk();

        $count = function (int $nodes): int {
            ContentNode::query()->delete();
            foreach (range(1, $nodes) as $i) {
                $this->node(['cover' => $this->png("cover {$i}.png")->path, 'gallery' => [$this->png("g{$i}.png")->path]], "Node {$i}");
            }

            DB::flushQueryLog();
            DB::enableQueryLog();
            $this->admin()->get('/admin/content-types/news/nodes')->assertOk()
                ->assertInertia(fn (Assert $page) => $page->where('table.rows.0.cover', fn ($cover) => str_starts_with($cover['name'], 'cover ')));
            $total = count(DB::getQueryLog());
            DB::disableQueryLog();

            return $total;
        };

        $this->assertSame($count(2), $count(6));
    }

    public function test_image_and_file_cells_carry_url_name_and_extension(): void
    {
        ContentType::query()->where('key', 'news')->update(['settings' => ['list' => ['visible' => ['cover', 'manual', 'gallery']]]]);
        $cover = $this->png('Cover.png');
        $manual = $this->pdf('Spec.pdf');
        $other = $this->png('Other.png');
        $this->node(['cover' => $cover->path, 'manual' => $manual->path, 'gallery' => [$cover->path, $other->path]]);

        $this->admin()->get('/admin/content-types/news/nodes')
            ->assertInertia(fn (Assert $page) => $page
                ->where('columns', fn ($columns) => collect($columns)->pluck('type', 'key')->only(['cover', 'manual', 'gallery'])->all() === ['cover' => 'image', 'manual' => 'file', 'gallery' => 'image'])
                ->where('table.rows.0.cover', ['url' => "/storage/{$cover->path}", 'name' => 'Cover.png', 'preview' => image_style("/storage/{$cover->path}", 'thumb')])
                ->where('table.rows.0.manual', ['url' => "/storage/{$manual->path}", 'name' => 'Spec.pdf', 'ext' => 'pdf', 'preview' => null])
                ->where('table.rows.0.gallery', [
                    ['url' => "/storage/{$cover->path}", 'name' => 'Cover.png', 'preview' => image_style("/storage/{$cover->path}", 'thumb')],
                    ['url' => "/storage/{$other->path}", 'name' => 'Other.png', 'preview' => image_style("/storage/{$other->path}", 'thumb')],
                ]));
    }

    public function test_a_file_missing_from_the_media_library_stays_a_plain_string(): void
    {
        ContentType::query()->where('key', 'news')->update(['settings' => ['list' => ['visible' => ['manual']]]]);
        $manual = $this->pdf('Gone.pdf');
        $this->node(['manual' => $manual->path]);
        // The value keeps pointing at a file that is gone from the library.
        MediaRelation::query()->where('file_uuid', $manual->uuid)->delete();
        $manual->delete();

        $this->admin()->get('/admin/content-types/news/nodes')
            ->assertInertia(fn (Assert $page) => $page->where('table.rows.0.manual', $manual->path));
    }

    public function test_the_columns_of_file_fields_are_not_sortable(): void
    {
        ContentType::query()->where('key', 'news')->update(['settings' => ['list' => ['visible' => ['cover', 'manual']]]]);

        $this->admin()->get('/admin/content-types/news/nodes')
            ->assertInertia(fn (Assert $page) => $page
                ->where('columns.1.key', 'cover')->where('columns.1.sortable', false)
                ->where('columns.2.key', 'manual')->where('columns.2.sortable', false));
    }

    public function test_saving_a_node_binds_replaces_and_clears_files(): void
    {
        [$a, $b] = [$this->png('a.png'), $this->png('b.png')];
        $node = $this->node([]);
        $url = "/admin/content-types/news/nodes/{$node->uuid}";
        $related = fn () => MediaRelation::query()->where('owner_id', $node->id)->pluck('file_uuid')->all();

        $this->admin()->put($url, ['published_at' => '2026-01-01T00:00', 'slug' => $node->slug, 'values' => ['title' => 'A', 'cover' => $a->path]])->assertRedirect();
        $this->assertSame([$a->uuid], $related());

        $this->admin()->put($url, ['published_at' => '2026-01-01T00:00', 'slug' => $node->slug, 'values' => ['title' => 'A', 'cover' => $b->path]])->assertRedirect();
        $this->assertSame([$b->uuid], $related());

        $this->admin()->put($url, ['published_at' => '2026-01-01T00:00', 'slug' => $node->slug, 'values' => ['title' => 'A', 'cover' => '']])->assertRedirect();
        $this->assertSame([], $related());
        $this->assertDatabaseHas('media', ['uuid' => $a->uuid]);
        $this->assertDatabaseHas('media', ['uuid' => $b->uuid]);
    }

    public function test_a_file_outside_the_field_settings_is_rejected_by_the_server(): void
    {
        $jpeg = File::factory()->create(['uuid' => $uuid = File::newUuid(), 'profile' => 'image', 'mime' => 'image/jpeg', 'path' => "{$uuid}.jpg"]);
        $node = $this->node([]);

        $this->admin()->put("/admin/content-types/news/nodes/{$node->uuid}", ['published_at' => '2026-01-01T00:00', 'slug' => $node->slug, 'values' => ['title' => 'A', 'cover' => $jpeg->path]])
            ->assertSessionHasErrors('values.cover');

        $this->assertSame(0, MediaRelation::query()->count());
    }

    public function test_deleting_a_node_releases_its_files(): void
    {
        $cover = $this->png();
        $node = $this->node(['cover' => $cover->path]);

        $this->admin()->delete("/admin/content-types/news/nodes/{$node->uuid}")->assertRedirect();

        $this->assertSame(0, MediaRelation::query()->count());
        $this->assertDatabaseHas('media', ['uuid' => $cover->uuid]);
    }

    public function test_updating_a_node_saves_the_name_and_description_of_its_files(): void
    {
        $cover = $this->png('IMG_0042.png');
        $node = $this->node(['cover' => $cover->path]);

        $this->admin()->put("/admin/content-types/news/nodes/{$node->uuid}", [
            'published_at' => '2026-01-01T00:00', 'slug' => $node->slug,
            'values' => ['title' => 'A', 'cover' => $cover->path],
            'file_meta' => [$cover->uuid => ['name' => '../Cover', 'description' => '  Front cover  ']],
        ])->assertRedirect();

        $cover->refresh();
        $this->assertSame('Cover', $cover->original_name);
        $this->assertSame('Front cover', $cover->description);
        $this->assertSame("{$cover->uuid}.png", $cover->path);

        $this->admin()->put("/admin/content-types/news/nodes/{$node->uuid}", [
            'published_at' => '2026-01-01T00:00', 'slug' => $node->slug,
            'values' => ['title' => 'A', 'cover' => $cover->path],
            'file_meta' => [$cover->uuid => ['name' => 'Cover', 'description' => '']],
        ])->assertRedirect();

        $this->assertNull($cover->refresh()->description);
    }

    public function test_creating_a_node_saves_the_name_and_description_of_its_files(): void
    {
        $cover = $this->png('IMG_0042.png');

        $this->admin()->post('/admin/content-types/news/nodes', [
            'values' => ['title' => 'A', 'cover' => $cover->path],
            'file_meta' => [$cover->uuid => ['name' => 'Cover', 'description' => 'Front cover']],
        ])->assertRedirect();

        $this->assertSame('Cover', $cover->refresh()->original_name);
        $this->assertSame('Front cover', $cover->description);
    }

    public function test_the_metadata_of_a_file_outside_the_node_values_is_ignored(): void
    {
        $cover = $this->png();
        $other = $this->png('Other.png');
        $node = $this->node(['cover' => $cover->path]);

        $this->admin()->put("/admin/content-types/news/nodes/{$node->uuid}", [
            'published_at' => '2026-01-01T00:00', 'slug' => $node->slug,
            'values' => ['title' => 'A', 'cover' => $cover->path],
            'file_meta' => [$other->uuid => ['name' => 'Hacked', 'description' => 'x']],
        ])->assertRedirect();

        $this->assertSame('Other.png', $other->refresh()->original_name);
        $this->assertNull($other->description);
    }

    public function test_an_empty_name_or_a_long_description_is_rejected_on_create_and_update(): void
    {
        $cover = $this->png();
        $node = $this->node(['cover' => $cover->path]);
        $values = ['title' => 'A', 'cover' => $cover->path];

        $this->admin()->put("/admin/content-types/news/nodes/{$node->uuid}", ['published_at' => '2026-01-01T00:00', 'slug' => $node->slug, 'values' => $values, 'file_meta' => [$cover->uuid => ['name' => '', 'description' => str_repeat('a', 2001)]]])
            ->assertSessionHasErrors(["file_meta.{$cover->uuid}.name", "file_meta.{$cover->uuid}.description"]);

        $this->admin()->post('/admin/content-types/news/nodes', ['values' => $values, 'file_meta' => [$cover->uuid => ['name' => '']]])
            ->assertSessionHasErrors(["file_meta.{$cover->uuid}.name"]);

        $this->admin()->post('/admin/content-types/news/nodes', ['values' => $values, 'file_meta' => ['not-a-uuid' => ['name' => 'x']]])
            ->assertSessionHasErrors(['file_meta']);
    }

    public function test_the_metadata_is_rolled_back_when_the_values_are_invalid(): void
    {
        $cover = $this->png('Keep.png');
        $node = $this->node(['cover' => $cover->path]);

        $this->admin()->put("/admin/content-types/news/nodes/{$node->uuid}", [
            'published_at' => '2026-01-01T00:00', 'slug' => $node->slug,
            'values' => ['title' => '', 'cover' => $cover->path],
            'file_meta' => [$cover->uuid => ['name' => 'Renamed', 'description' => 'x']],
        ])->assertSessionHasErrors();

        $this->assertSame('Keep.png', $cover->refresh()->original_name);
        $this->assertNull($cover->description);
    }

    public function test_the_edit_and_create_forms_carry_the_field_description(): void
    {
        ContentField::query()->where('key', 'title')->update(['description' => 'Up to 60 characters']);

        $this->admin()->get('/admin/content-types/news/nodes/create')
            ->assertInertia(fn (Assert $page) => $page->where('fields.0.description', 'Up to 60 characters')->where('fields.1.description', null));
    }

    private function editor(string $level = 'own', array $extra = []): User
    {
        return User::factory()->withRole(Role::factory()->withPermissions(['node:news' => $level, ...$extra])->create())->create();
    }

    private function foreignPng(string $name = 'secret.png'): File
    {
        $file = $this->png($name);
        $file->update(['user_id' => User::factory()->create()->id]);

        return $file;
    }

    /** @param array<string, mixed> $values */
    private function save(User $user, ContentNode $node, array $values, array $meta = []): TestResponse
    {
        return $this->actingAs($user)->put("/admin/content-types/news/nodes/{$node->uuid}", [
            'published_at' => '2026-01-01T00:00', 'slug' => $node->slug,
            'values' => ['title' => 'A', ...$values], 'file_meta' => $meta,
        ]);
    }

    public function test_create_with_another_users_file_is_refused_and_nothing_is_stored(): void
    {
        $foreign = $this->foreignPng();

        $this->actingAs($this->editor())->post('/admin/content-types/news/nodes', [
            'values' => ['title' => 'A', 'cover' => $foreign->path],
            'file_meta' => [$foreign->uuid => ['name' => 'Hacked', 'description' => 'x']],
        ])->assertSessionHasErrors('values.cover');

        $this->assertSame(0, ContentNode::query()->count());
        $this->assertSame(0, MediaRelation::query()->count());
        $this->assertSame('secret.png', $foreign->refresh()->original_name);
        $this->assertNull($foreign->description);
    }

    public function test_update_with_another_users_file_is_refused_and_nothing_is_stored(): void
    {
        $user = $this->editor();
        $node = app(ContentNodeCreator::class)->create($this->type, ['title' => 'A', 'cover' => $this->png()->path], authorId: $user->id);
        $foreign = $this->foreignPng();

        $this->save($user, $node, ['cover' => $foreign->path, 'title' => 'Changed'], [$foreign->uuid => ['name' => 'Hacked', 'description' => 'x']])
            ->assertSessionHasErrors('values.cover');

        $this->assertSame('secret.png', $foreign->refresh()->original_name);
        $this->assertSame(0, MediaRelation::query()->where('file_uuid', $foreign->uuid)->count());
        $this->assertNotSame('Changed', DB::table('content_values_string')->where('content_node_id', $node->id)->where('field_key', 'title')->value('value'));
    }

    public function test_an_own_upload_is_accepted(): void
    {
        $user = $this->editor();
        $mine = $this->png('mine.png');
        $mine->update(['user_id' => $user->id]);

        $this->actingAs($user)->post('/admin/content-types/news/nodes', ['values' => ['title' => 'A', 'cover' => $mine->path]])
            ->assertSessionHasNoErrors();

        $this->assertSame(1, MediaRelation::query()->where('file_uuid', $mine->uuid)->count());
    }

    public function test_an_any_editor_keeps_and_renames_a_colleagues_file_already_in_the_node(): void
    {
        $colleague = $this->foreignPng('theirs.png');
        $node = $this->node(['cover' => $colleague->path]);

        $this->save($this->editor('any'), $node, ['cover' => $colleague->path], [$colleague->uuid => ['name' => 'Renamed', 'description' => 'd']])
            ->assertSessionHasNoErrors();

        $this->assertSame('Renamed', $colleague->refresh()->original_name);
        $this->assertSame(1, MediaRelation::query()->where('file_uuid', $colleague->uuid)->count());
    }

    public function test_media_any_and_own_decide_about_foreign_files(): void
    {
        $foreign = $this->foreignPng();

        $this->actingAs($this->editor('own', ['media' => 'any']))->post('/admin/content-types/news/nodes', ['values' => ['title' => 'A', 'cover' => $foreign->path]])
            ->assertSessionHasNoErrors();

        $this->actingAs($this->editor('own', ['media' => 'own']))->post('/admin/content-types/news/nodes', ['values' => ['title' => 'B', 'cover' => $foreign->path]])
            ->assertSessionHasErrors('values.cover');
    }

    public function test_an_administrator_may_attach_any_file(): void
    {
        $foreign = $this->foreignPng();

        $this->admin()->post('/admin/content-types/news/nodes', ['values' => ['title' => 'A', 'cover' => $foreign->path]])
            ->assertSessionHasNoErrors();

        $this->assertSame(1, MediaRelation::query()->where('file_uuid', $foreign->uuid)->count());
    }

    public function test_the_metadata_of_a_file_outside_the_node_is_ignored_for_an_editor(): void
    {
        $node = $this->node(['cover' => $this->png()->path]);
        $other = $this->foreignPng('Other.png');

        $this->save($this->editor('any'), $node, [], [$other->uuid => ['name' => 'Hacked', 'description' => 'x']])->assertSessionHasNoErrors();

        $this->assertSame('Other.png', $other->refresh()->original_name);
    }

    public function test_a_youtube_screenshot_and_a_rich_text_image_of_another_user_are_refused(): void
    {
        ContentField::create(['content_type' => 'news', 'key' => 'clip', 'name' => 'Clip', 'type' => 'youtube', 'weight' => 40]);
        ContentField::create(['content_type' => 'news', 'key' => 'body', 'name' => 'Body', 'type' => 'richtext', 'weight' => 50]);
        $user = $this->editor();
        $screen = $this->foreignPng('screen.png');
        $inline = $this->foreignPng('inline.png');

        $this->actingAs($user)->post('/admin/content-types/news/nodes', ['values' => ['title' => 'A', 'clip' => ['url' => 'https://youtu.be/dQw4w9WgXcQ', 'screen' => $screen->path]]])
            ->assertSessionHasErrors('values.clip');

        $this->actingAs($user)->post('/admin/content-types/news/nodes', ['values' => ['title' => 'B', 'body' => '<p><img src="/storage/'.$inline->path.'"></p>']])
            ->assertSessionHasErrors('values.body');

        $this->assertSame(0, ContentNode::query()->count());
    }
}
