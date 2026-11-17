<?php

namespace Webtack\Ecms\Tests\Feature\Admin;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Validation\ValidationException;
use Webtack\Ecms\Content\ContentTypeCloner;
use Webtack\Ecms\Content\ThemeTemplateCopyException;
use Webtack\Ecms\Models\ContentField;
use Webtack\Ecms\Models\ContentNode;
use Webtack\Ecms\Models\ContentType;
use Webtack\Ecms\Models\Role;
use Webtack\Ecms\Tests\Fixtures\User;
use Webtack\Ecms\Tests\TestCase;

class ContentTypeCloneTest extends TestCase
{
    use RefreshDatabase;

    /** Temporary theme root of the test, so the clone never touches the real `themes/` directory. */
    private string $themes;

    protected function setUp(): void
    {
        parent::setUp();

        $this->themes = sys_get_temp_dir().'/ecms-themes-'.uniqid();
        File::ensureDirectoryExists("{$this->themes}/alpha/views");
        File::ensureDirectoryExists("{$this->themes}/beta/views");
        config(['theme.path' => $this->themes]);
        $this->beforeApplicationDestroyed(fn () => File::deleteDirectory($this->themes));
    }

    private function admin(): static
    {
        return $this->actingAs(User::factory()->create());
    }

    private function source(): ContentType
    {
        ContentType::create(['key' => 'common', 'name' => 'Common', 'slug' => 'common']);
        $news = ContentType::create(['key' => 'news', 'name' => 'News', 'slug' => 'common/news', 'parent_type' => 'common', 'description' => 'The feed']);
        ContentField::factory()->create([
            'content_type' => 'news', 'key' => 'body', 'name' => 'Body', 'description' => 'Text', 'type' => 'text',
            'is_required' => true, 'is_translatable' => false, 'hide_label' => true, 'weight' => 20,
        ]);
        ContentField::factory()->create(['content_type' => 'news', 'key' => 'cover', 'name' => 'Cover', 'type' => 'image', 'weight' => 10]);
        $news->fields()->where('key', 'title')->first()->update(['name' => 'Headline', 'description' => 'Short', 'weight' => 5, 'hide_label' => true]);
        $news->settings = [
            'list' => ['visible' => ['title', 'body'], 'sort' => ['field' => 'body', 'direction' => 'desc']],
            'sitemap' => ['xml' => ['type' => true, 'changefreq' => 'weekly', 'priority' => '0.5'], 'web' => ['nodes' => true]],
        ];
        $news->save();

        return $news->refresh();
    }

    public function test_the_route_has_its_name(): void
    {
        $this->assertSame('/admin/content-types/news/clone', route('admin.content-types.clone', 'news', false));
        $this->assertSame('/admin/page-types/home/clone', route('admin.page-types.clone', 'home', false));
    }

    public function test_the_cloner_copies_fields_settings_and_nothing_else(): void
    {
        $news = $this->source();
        ContentNode::factory()->create(['content_type' => 'news']);

        $new = app(ContentTypeCloner::class)->clone($news, ['name' => 'Press', 'key' => 'press', 'slug' => 'common/press', 'parent_type' => 'common']);

        $this->assertSame('content', $new->kind);
        $this->assertNull($new->description);
        $this->assertSame($news->settings, $new->settings);
        $this->assertSame($news->settings['sitemap'], $new->settings['sitemap']);
        $this->assertSame(0, $new->nodes()->count());

        $copy = $new->fields->map(fn (ContentField $f) => $f->only(['key', 'name', 'description', 'type', 'is_multiple', 'max_items', 'is_required', 'is_translatable', 'hide_label', 'weight', 'settings', 'is_system']));
        $original = $news->fields->map(fn (ContentField $f) => $f->only(['key', 'name', 'description', 'type', 'is_multiple', 'max_items', 'is_required', 'is_translatable', 'hide_label', 'weight', 'settings', 'is_system']));
        $this->assertEquals($original->all(), $copy->all());
        $this->assertSame('Headline', $new->fields->firstWhere('key', 'title')->name);
        $this->assertSame(1, $news->nodes()->count());
        $this->assertSame('The feed', $news->fresh()->description);
    }

    public function test_translated_labels_are_copied_with_the_fields(): void
    {
        $news = $this->source();
        ContentField::query()->where('content_type', 'news')->where('key', 'body')->firstOrFail()->setLabel('en', 'Text');
        $news->fields()->where('key', 'title')->firstOrFail()->setLabel('pt-BR', 'Título');

        $new = app(ContentTypeCloner::class)->clone($news, ['name' => 'Press', 'key' => 'press', 'slug' => null, 'parent_type' => null]);

        $this->assertSame('Text', $new->fields()->where('key', 'body')->firstOrFail()->label('en'));
        $this->assertSame('Título', $new->fields()->where('key', 'title')->firstOrFail()->label('pt-BR'));
    }

    public function test_the_parent_can_be_cleared(): void
    {
        $new = app(ContentTypeCloner::class)->clone($this->source(), ['name' => 'Press', 'key' => 'press', 'slug' => null, 'parent_type' => null]);

        $this->assertNull($new->parent_type);
    }

    public function test_a_failure_rolls_everything_back(): void
    {
        $news = $this->source();
        // Stale settings that reference a field which does not exist (old data).
        $news->settings = ['list' => ['visible' => ['gone']]];
        $news->saveQuietly();

        try {
            app(ContentTypeCloner::class)->clone($news, ['name' => 'Press', 'key' => 'press', 'slug' => null, 'parent_type' => null]);
            $this->fail('The clone should fail.');
        } catch (ValidationException) {
        }

        $this->assertNull(ContentType::findByKey('press'));
        $this->assertSame(0, ContentField::query()->where('content_type', 'press')->count());
    }

    public function test_the_request_clones_and_redirects_to_the_fields_page(): void
    {
        $this->source();

        $this->admin()->post('/admin/content-types/news/clone', ['name' => 'Press', 'key' => 'press', 'slug' => 'common/press', 'parent_type' => 'common'])
            ->assertRedirect('/admin/content-types/press/fields')
            ->assertSessionHas('success');

        $this->assertSame(
            ContentType::findByKey('news')->fields->pluck('key')->all(),
            ContentType::findByKey('press')->fields->pluck('key')->all(),
        );
    }

    public function test_the_request_validates_like_the_create_form(): void
    {
        $this->source();
        ContentType::create(['key' => 'other', 'name' => 'Other', 'slug' => 'other']);

        $this->admin()->post('/admin/content-types/news/clone', ['name' => '', 'key' => 'other', 'slug' => 'other'])
            ->assertSessionHasErrors(['name', 'key', 'slug']);
        $this->admin()->post('/admin/content-types/news/clone', ['name' => 'X', 'key' => ContentType::RESERVED_KEYS[0]])
            ->assertSessionHasErrors(['key' => 'This key is reserved.']);
        $this->admin()->post('/admin/content-types/news/clone', ['name' => 'X', 'key' => 'x', 'parent_type' => 'nope'])
            ->assertSessionHasErrors('parent_type');
        $this->admin()->post('/admin/content-types/news/clone', ['name' => 'X', 'key' => 'x', 'slug' => 'wrong/x', 'parent_type' => 'common'])
            ->assertSessionHasErrors('slug');

        $this->assertNull(ContentType::findByKey('x'));
    }

    public function test_page_and_block_types_are_cloned_in_their_sections(): void
    {
        ContentType::create(['key' => 'home', 'name' => 'Home', 'kind' => 'page']);
        ContentType::create(['key' => 'hero', 'name' => 'Hero', 'kind' => 'block']);

        $this->admin()->post('/admin/page-types/home/clone', ['name' => 'Landing', 'key' => 'landing', 'slug' => 'ignored'])
            ->assertRedirect('/admin/page-types/landing/fields');
        $this->admin()->post('/admin/block-types/hero/clone', ['name' => 'Banner', 'key' => 'banner'])
            ->assertRedirect('/admin/block-types/banner/fields');

        $this->assertSame('page', ContentType::findByKey('landing')->kind);
        $this->assertNull(ContentType::findByKey('landing')->slug);
        $this->assertSame('block', ContentType::findByKey('banner')->kind);
    }

    public function test_a_type_of_another_section_answers_404(): void
    {
        ContentType::create(['key' => 'home', 'name' => 'Home', 'kind' => 'page']);

        $this->admin()->post('/admin/content-types/home/clone', ['name' => 'X', 'key' => 'x'])->assertNotFound();
    }

    public function test_it_needs_the_types_permission(): void
    {
        $this->source();
        $user = User::factory()->withRole(Role::factory()->withPermissions(['node:news' => 'any'])->create())->create();

        $this->actingAs($user)->post('/admin/content-types/news/clone', ['name' => 'X', 'key' => 'x'])->assertForbidden();
    }

    public function test_the_index_passes_parent_options_to_managers_only(): void
    {
        $this->source();

        $this->admin()->get('/admin/content-types')
            ->assertInertia(fn ($page) => $page->has('parentOptions', 2));
    }

    /** The temporary theme root with the themes `alpha` and `beta`. */
    private function themeRoot(): string
    {
        return $this->themes;
    }

    private function writeFile(string $path, string $content = 'x'): void
    {
        File::ensureDirectoryExists(dirname($path));
        File::put($path, $content);
    }

    public function test_the_type_directory_is_copied_in_every_theme_without_node_bound_files(): void
    {
        $root = $this->themeRoot();
        $news = $this->source();
        $this->writeFile("{$root}/alpha/views/news/list.blade.php", "{{-- news --}}\r\n<div class=\"news\">\0</div>");
        $this->writeFile("{$root}/alpha/views/news/details.blade.php", 'details');
        $this->writeFile("{$root}/alpha/views/news/details--7.blade.php", 'bound');
        $this->writeFile("{$root}/alpha/views/news/sub/field-body.blade.php", 'sub');
        $this->writeFile("{$root}/beta/views/news/preview.blade.php", 'beta');

        app(ContentTypeCloner::class)->clone($news, ['name' => 'Press', 'key' => 'press', 'slug' => 'common/press', 'parent_type' => 'common']);

        $this->assertSame(File::get("{$root}/alpha/views/news/list.blade.php"), File::get("{$root}/alpha/views/press/list.blade.php"));
        $this->assertFileExists("{$root}/alpha/views/press/details.blade.php");
        $this->assertFileExists("{$root}/alpha/views/press/sub/field-body.blade.php");
        $this->assertFileDoesNotExist("{$root}/alpha/views/press/details--7.blade.php");
        $this->assertFileExists("{$root}/beta/views/press/preview.blade.php");
        $this->assertFileExists("{$root}/alpha/views/news/details--7.blade.php");
    }

    public function test_the_block_template_is_copied_without_slug_bound_files(): void
    {
        $root = $this->themeRoot();
        ContentType::create(['key' => 'contacts', 'name' => 'Contacts', 'kind' => 'block']);
        $this->writeFile("{$root}/alpha/views/blocks/block-contacts.blade.php", '<p>contacts</p>');
        $this->writeFile("{$root}/alpha/views/blocks/block-contacts--footer.blade.php", 'footer');

        app(ContentTypeCloner::class)->clone(ContentType::findByKey('contacts'), ['name' => 'Office', 'key' => 'office']);

        $this->assertSame('<p>contacts</p>', File::get("{$root}/alpha/views/blocks/block-office.blade.php"));
        $this->assertFileDoesNotExist("{$root}/alpha/views/blocks/block-office--footer.blade.php");
        $this->assertFileDoesNotExist("{$root}/beta/views/blocks/block-office.blade.php");
    }

    public function test_nothing_is_created_when_no_theme_has_templates(): void
    {
        $root = $this->themeRoot();

        app(ContentTypeCloner::class)->clone($this->source(), ['name' => 'Press', 'key' => 'press', 'slug' => 'common/press']);

        $this->assertSame([], File::allFiles($root));
        $this->assertNotNull(ContentType::findByKey('press'));
    }

    public function test_an_existing_target_fails_the_clone_and_removes_files_of_other_themes(): void
    {
        $root = $this->themeRoot();
        $news = $this->source();
        $this->writeFile("{$root}/alpha/views/news/list.blade.php");
        $this->writeFile("{$root}/beta/views/news/list.blade.php");
        $this->writeFile("{$root}/beta/views/press/own.blade.php", 'keep');

        try {
            app(ContentTypeCloner::class)->clone($news, ['name' => 'Press', 'key' => 'press', 'slug' => 'common/press']);
            $this->fail('The clone should fail.');
        } catch (ThemeTemplateCopyException $e) {
            $this->assertSame('beta', $e->theme);
            $this->assertSame('views/press', $e->path);
        }

        $this->assertNull(ContentType::findByKey('press'));
        $this->assertDirectoryDoesNotExist("{$root}/alpha/views/press");
        $this->assertSame('keep', File::get("{$root}/beta/views/press/own.blade.php"));
        $this->assertFileDoesNotExist("{$root}/beta/views/press/list.blade.php");
    }

    public function test_a_file_that_cannot_be_written_fails_the_clone_and_leaves_no_files(): void
    {
        $root = $this->themeRoot();
        $news = $this->source();
        $this->writeFile("{$root}/alpha/views/news/a.blade.php");
        $this->writeFile("{$root}/alpha/views/news/b.blade.php");

        $calls = 0;
        File::partialMock()->shouldReceive('copy')->andReturnUsing(function (string $from, string $to) use (&$calls) {
            return ++$calls === 1 ? copy($from, $to) : false;
        });

        try {
            app(ContentTypeCloner::class)->clone($news, ['name' => 'Press', 'key' => 'press', 'slug' => 'common/press']);
            $this->fail('The clone should fail.');
        } catch (ThemeTemplateCopyException $e) {
            $this->assertSame('alpha', $e->theme);
        }

        $this->assertNull(ContentType::findByKey('press'));
        $this->assertDirectoryDoesNotExist("{$root}/alpha/views/press");
        $this->assertFileExists("{$root}/alpha/views/news/a.blade.php");
    }

    public function test_the_request_reports_a_template_failure_and_keeps_the_input(): void
    {
        $root = $this->themeRoot();
        $this->source();
        $this->writeFile("{$root}/alpha/views/news/list.blade.php");
        $this->writeFile("{$root}/alpha/views/press/list.blade.php");

        $this->admin()->from('/admin/content-types')
            ->post('/admin/content-types/news/clone', ['name' => 'Press', 'key' => 'press', 'slug' => 'common/press', 'parent_type' => 'common'])
            ->assertRedirect('/admin/content-types')
            ->assertSessionHas('error')
            ->assertSessionHasInput('key', 'press');

        $this->assertNull(ContentType::findByKey('press'));
    }
}
