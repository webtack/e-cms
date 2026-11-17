<?php

namespace Webtack\Ecms\Tests\Feature\Admin;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Webtack\Ecms\Content\ContentValueReader;
use Webtack\Ecms\Content\ContentValueWriter;
use Webtack\Ecms\Content\ValueOwner;
use Webtack\Ecms\Files\FileDescriptions;
use Webtack\Ecms\Models\ContentField;
use Webtack\Ecms\Models\ContentNode;
use Webtack\Ecms\Models\ContentType;
use Webtack\Ecms\Models\File;
use Webtack\Ecms\Models\MediaRelation;
use Webtack\Ecms\Models\Role;
use Webtack\Ecms\Tests\Feature\Content\Concerns\BuildsArticleType;
use Webtack\Ecms\Tests\Fixtures\User;
use Webtack\Ecms\Tests\TestCase;

/**
 * File descriptions in the translation mode of a node and of the type tabs (`file_meta.<uuid>.description`).
 */
class ContentNodeFileDescriptionTranslationTest extends TestCase
{
    use BuildsArticleType, RefreshDatabase;

    private ContentNode $node;

    private ContentNode $other;

    private File $cover;

    private File $photo;

    protected function setUp(): void
    {
        parent::setUp();

        $this->enableLanguages('en', 'pl');
        $this->articleType();
        // `cover` is not translatable, `photo` is.
        ContentField::create(['content_type' => 'article', 'key' => 'cover', 'name' => 'Cover', 'type' => 'image']);
        ContentField::create(['content_type' => 'article', 'key' => 'photo', 'name' => 'Photo', 'type' => 'image', 'is_translatable' => true]);

        $this->cover = $this->png('Лицьова обкладинка');
        $this->photo = $this->png('Фото');
        $this->node = $this->makeArticle(['title' => 'Привіт', 'cover' => $this->cover->path, 'photo' => $this->photo->path]);
        $this->other = $this->makeArticle(['title' => 'Інший', 'cover' => $this->cover->path], 'other');
    }

    private function png(?string $description): File
    {
        $uuid = File::newUuid();

        return File::factory()->create([
            'uuid' => $uuid, 'profile' => 'image', 'mime' => 'image/png', 'path' => "{$uuid}.png",
            'original_name' => "{$uuid}.png", 'description' => $description, 'size' => 1000,
        ]);
    }

    private function admin(): static
    {
        return $this->actingAs(User::factory()->create());
    }

    private function url(string $suffix = '', ?ContentNode $node = null): string
    {
        return '/admin/content-types/article/nodes/'.($node ?? $this->node)->uuid.$suffix;
    }

    private function descriptions(): FileDescriptions
    {
        return app(FileDescriptions::class);
    }

    public function test_the_translation_mode_prefills_descriptions_of_translatable_and_non_translatable_files(): void
    {
        $this->admin()->get($this->url('/edit?lang=en'))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('files', fn ($files) => collect($files)[$this->cover->path]['description'] === 'Лицьова обкладинка')
            ->where('files', fn ($files) => collect($files)[$this->photo->path]['description'] === 'Фото')
            ->where('values.cover', $this->cover->path));

        $this->descriptions()->save($this->cover->uuid, 'en', 'Front cover');

        $this->admin()->get($this->url('/edit?lang=en'))->assertInertia(fn (Assert $page) => $page
            ->where('files', fn ($files) => collect($files)[$this->cover->path]['description'] === 'Front cover'));
        $this->admin()->get($this->url('/edit?lang=pl'))->assertInertia(fn (Assert $page) => $page
            ->where('files', fn ($files) => collect($files)[$this->cover->path]['description'] === 'Лицьова обкладинка'));
        $this->admin()->get($this->url('/edit'))->assertInertia(fn (Assert $page) => $page
            ->where('files', fn ($files) => collect($files)[$this->cover->path]['description'] === 'Лицьова обкладинка'));
    }

    public function test_a_description_of_a_non_translatable_image_is_saved_without_touching_the_base_or_the_name(): void
    {
        $name = $this->cover->original_name;

        $this->admin()->put($this->url('/translations/en'), [
            'file_meta' => [$this->cover->uuid => ['name' => 'Cover', 'description' => 'Front cover']],
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertSame('Front cover', $this->descriptions()->forFile($this->cover->uuid, 'en'));
        $this->assertSame('Лицьова обкладинка', $this->cover->fresh()->description);
        $this->assertSame($name, $this->cover->fresh()->original_name);
        $this->assertSame('Cover', $this->descriptions()->namesForFiles([$this->cover->uuid], 'en')[$this->cover->uuid]);
        $this->assertNull($this->descriptions()->forFile($this->cover->uuid, 'pl'));
    }

    public function test_a_translated_name_is_saved_prefilled_and_never_changes_the_original(): void
    {
        $original = $this->cover->original_name;

        $this->admin()->get($this->url('/edit?lang=en'))->assertInertia(fn (Assert $page) => $page
            ->where('files', fn ($files) => collect($files)[$this->cover->path]['name'] === $original));

        $this->admin()->put($this->url('/translations/en'), [
            'file_meta' => [$this->cover->uuid => ['name' => 'Cover', 'description' => 'Front cover']],
        ])->assertSessionHasNoErrors();

        $this->assertSame($original, $this->cover->fresh()->original_name);
        $this->assertSame(['en' => ['name' => 'Cover', 'description' => 'Front cover']], $this->descriptions()->translations($this->cover->uuid));
        $this->admin()->get($this->url('/edit?lang=en', $this->other))->assertInertia(fn (Assert $page) => $page
            ->where('files', fn ($files) => collect($files)[$this->cover->path]['name'] === 'Cover'));
        $this->admin()->get($this->url('/edit'))->assertInertia(fn (Assert $page) => $page
            ->where('files', fn ($files) => collect($files)[$this->cover->path]['name'] === $original));

        $this->admin()->put($this->url('/translations/en'), ['file_meta' => [$this->cover->uuid => ['name' => $original]]])->assertSessionHasNoErrors();
        $this->assertNull($this->descriptions()->namesForFiles([$this->cover->uuid], 'en')[$this->cover->uuid] ?? null);
    }

    public function test_the_same_file_in_another_node_shows_the_same_translation(): void
    {
        $this->admin()->put($this->url('/translations/en'), ['file_meta' => [$this->cover->uuid => ['description' => 'Front cover']]]);

        $this->admin()->get($this->url('/edit?lang=en', $this->other))->assertInertia(fn (Assert $page) => $page
            ->where('files', fn ($files) => collect($files)[$this->cover->path]['description'] === 'Front cover'));
    }

    public function test_equal_to_the_base_or_emptied_removes_the_translation(): void
    {
        $this->descriptions()->save($this->cover->uuid, 'en', 'Front cover');

        $this->admin()->put($this->url('/translations/en'), ['file_meta' => [$this->cover->uuid => ['description' => 'Лицьова обкладинка']]]);
        $this->assertNull($this->descriptions()->forFile($this->cover->uuid, 'en'));

        $this->descriptions()->save($this->cover->uuid, 'en', 'Front cover');
        $this->admin()->put($this->url('/translations/en'), ['file_meta' => [$this->cover->uuid => ['description' => '']]]);
        $this->assertNull($this->descriptions()->forFile($this->cover->uuid, 'en'));
    }

    public function test_values_and_descriptions_are_saved_together(): void
    {
        $this->admin()->put($this->url('/translations/en'), [
            'values' => ['title' => 'Hello'],
            'file_meta' => [$this->photo->uuid => ['description' => 'Photo']],
        ])->assertSessionHasNoErrors();

        $this->assertSame('Hello', app(ContentValueReader::class)->load([$this->node->fresh()], ['title'], 'en')[$this->node->id]['title']);
        $this->assertSame('Photo', $this->descriptions()->forFile($this->photo->uuid, 'en'));
    }

    public function test_a_translated_file_of_the_language_belongs_to_the_node(): void
    {
        $translated = $this->png('Інше фото');
        app(ContentValueWriter::class)->write(ValueOwner::forNode($this->node), ['photo' => $translated->path], 'en');

        $this->admin()->put($this->url('/translations/en'), ['file_meta' => [$translated->uuid => ['description' => 'Other photo']]])->assertSessionHasNoErrors();

        $this->assertSame('Other photo', $this->descriptions()->forFile($translated->uuid, 'en'));
    }

    public function test_a_translation_of_a_file_field_with_a_foreign_file_is_refused(): void
    {
        $user = User::factory()->withRole(Role::factory()->withPermissions(['node:article' => 'any'])->create())->create();
        $foreign = $this->png('Чужий');
        $foreign->update(['user_id' => User::factory()->create()->id]);

        $this->actingAs($user)->put($this->url('/translations/en'), [
            'values' => ['photo' => $foreign->path],
            'file_meta' => [$foreign->uuid => ['description' => 'Hacked']],
        ])->assertSessionHasErrors('values.photo');

        $this->assertSame(0, MediaRelation::query()->where('file_uuid', $foreign->uuid)->count());
        $this->assertSame([], $this->descriptions()->all($foreign->uuid));
    }

    public function test_a_foreign_uuid_is_rejected_and_nothing_is_stored(): void
    {
        $foreign = $this->png('Чужий');

        $this->admin()->put($this->url('/translations/en'), [
            'values' => ['title' => 'Hello'],
            'file_meta' => [$this->cover->uuid => ['description' => 'Front cover'], $foreign->uuid => ['description' => 'Hacked']],
        ])->assertSessionHasErrors("file_meta.{$foreign->uuid}.description");

        $this->assertSame([], $this->descriptions()->all($foreign->uuid));
        $this->assertSame([], $this->descriptions()->all($this->cover->uuid));
        $this->assertNull(app(ContentValueReader::class)->load([$this->node->fresh()], ['title'], 'en')[$this->node->id]['title']);
    }

    public function test_a_malformed_file_meta_is_a_validation_error(): void
    {
        $this->admin()->put($this->url('/translations/en'), ['file_meta' => ['not-a-uuid' => ['description' => 'X']]])->assertSessionHasErrors('file_meta');
        $this->admin()->put($this->url('/translations/en'), ['file_meta' => [$this->cover->uuid => ['description' => str_repeat('x', 2001)]]])->assertSessionHasErrors("file_meta.{$this->cover->uuid}.description");
    }

    public function test_the_base_form_ignores_translated_descriptions(): void
    {
        $this->descriptions()->save($this->cover->uuid, 'en', 'Front cover');

        $this->admin()->put($this->url(), ['slug' => $this->node->slug, 'published_at' => now()->format('Y-m-d\TH:i'), 'values' => ['title' => 'Привіт', 'cover' => $this->cover->path], 'file_meta' => [$this->cover->uuid => ['name' => 'New', 'description' => 'Base new']]])->assertSessionHasNoErrors();

        $this->assertSame('Base new', $this->cover->fresh()->description);
        $this->assertSame('Front cover', $this->descriptions()->forFile($this->cover->uuid, 'en'));
    }

    public function test_the_type_tabs_translate_descriptions_of_their_own_images(): void
    {
        $news = ContentType::create(['key' => 'news', 'name' => 'News']);
        $meta = $this->png('Мета');
        $cover = $this->png('Обкладинка');
        app(ContentValueWriter::class)->write(ValueOwner::forType($news), ['meta_image' => $meta->path, 'section_cover' => $cover->path]);

        $this->admin()->get('/admin/content-types/news/seo?lang=en')->assertInertia(fn (Assert $page) => $page
            ->where('typeFiles', fn ($files) => collect($files)[$meta->path]['description'] === 'Мета'));
        $this->admin()->get('/admin/content-types/news/display?lang=en')->assertInertia(fn (Assert $page) => $page
            ->where('typeFiles', fn ($files) => collect($files)[$cover->path]['description'] === 'Обкладинка'));

        $this->admin()->put('/admin/content-types/news/values/translations/en', [
            'panel' => 'seo',
            'file_meta' => [$meta->uuid => ['name' => 'Meta', 'description' => 'Meta image']],
        ])->assertSessionHasNoErrors();

        $this->assertSame('Meta image', $this->descriptions()->forFile($meta->uuid, 'en'));
        $this->assertSame('Мета', $meta->fresh()->description);
        $this->assertSame($meta->original_name, $meta->fresh()->original_name);
        $this->assertSame('Meta', app(FileDescriptions::class)->namesForFiles([$meta->uuid], 'en')[$meta->uuid]);

        $this->admin()->get('/admin/content-types/news/seo?lang=en')->assertInertia(fn (Assert $page) => $page
            ->where('typeFiles', fn ($files) => collect($files)[$meta->path]['description'] === 'Meta image'));
        $this->admin()->get('/admin/content-types/news/seo')->assertInertia(fn (Assert $page) => $page
            ->where('typeFiles', fn ($files) => collect($files)[$meta->path]['description'] === 'Мета'));
    }

    public function test_a_type_tab_rejects_the_file_of_the_other_tab_and_foreign_files(): void
    {
        $news = ContentType::create(['key' => 'news', 'name' => 'News']);
        $cover = $this->png('Обкладинка');
        $foreign = $this->png('Чужий');
        app(ContentValueWriter::class)->write(ValueOwner::forType($news), ['section_cover' => $cover->path]);

        foreach ([$cover, $foreign] as $file) {
            $this->admin()->put('/admin/content-types/news/values/translations/en', [
                'panel' => 'seo',
                'file_meta' => [$file->uuid => ['description' => 'X']],
            ])->assertSessionHasErrors("file_meta.{$file->uuid}.description");
        }

        $this->assertSame([], $this->descriptions()->all($cover->uuid));
        $this->assertSame([], $this->descriptions()->all($foreign->uuid));
    }

    public function test_a_clear_keeps_the_shared_file_descriptions(): void
    {
        $this->descriptions()->save($this->cover->uuid, 'en', 'Front cover');
        app(ContentValueWriter::class)->write(ValueOwner::forNode($this->node), ['title' => 'Hello'], 'en');

        $this->admin()->delete($this->url('/translations/en'))->assertRedirect();

        $this->assertSame('Front cover', $this->descriptions()->forFile($this->cover->uuid, 'en'));
    }
}
