<?php

namespace Webtack\Ecms\Tests\Feature\Menus;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Webtack\Ecms\Files\FileRelations;
use Webtack\Ecms\Menus\MenuItemWriter;
use Webtack\Ecms\Models\File;
use Webtack\Ecms\Models\MediaRelation;
use Webtack\Ecms\Models\Menu;
use Webtack\Ecms\Models\MenuItem;
use Webtack\Ecms\Tests\Feature\Content\Concerns\BuildsArticleType;
use Webtack\Ecms\Tests\TestCase;

class MenuItemWriterTest extends TestCase
{
    use BuildsArticleType, RefreshDatabase;

    private Menu $menu;

    protected function setUp(): void
    {
        parent::setUp();

        $this->menu = Menu::factory()->create();
    }

    private function writer(): MenuItemWriter
    {
        return app(MenuItemWriter::class);
    }

    private function image(string $profile = 'image'): File
    {
        $uuid = File::newUuid();

        return File::factory()->create(['uuid' => $uuid, 'profile' => $profile, 'mime' => 'image/png', 'path' => "{$uuid}.png", 'size' => 1000]);
    }

    /** @return array<string, array<int, string>> */
    private function errorsOf(callable $action): array
    {
        try {
            $action();
        } catch (ValidationException $e) {
            return $e->errors();
        }

        $this->fail('No validation error was thrown.');
    }

    public function test_an_item_is_created_with_translations(): void
    {
        $this->enableLanguages('en');

        $item = $this->writer()->create($this->menu, [
            'url' => '/about',
            'translations' => ['default' => ['title' => 'Про нас', 'description' => 'Опис'], 'en' => ['title' => 'About us']],
        ]);

        $this->assertSame('/about', $item->fresh()->url);
        $this->assertSame(['Про нас', 'About us'], $item->translations()->orderBy('id')->pluck('title')->all());
        $this->assertSame('Опис', $item->translations()->where('lang', 'default')->value('description'));
    }

    public function test_the_default_title_is_required(): void
    {
        $this->assertArrayHasKey('translations.default.title', $this->errorsOf(
            fn () => $this->writer()->create($this->menu, ['translations' => ['default' => ['title' => ' ']]]),
        ));
        $this->assertArrayHasKey('translations.default.title', $this->errorsOf(
            fn () => $this->writer()->create($this->menu, []),
        ));
        $this->assertSame(0, MenuItem::query()->count());
    }

    public function test_an_inactive_language_is_rejected(): void
    {
        $this->assertArrayHasKey('lang', $this->errorsOf(
            fn () => $this->writer()->create($this->menu, ['translations' => ['default' => ['title' => 'A'], 'de' => ['title' => 'B']]]),
        ));
    }

    public function test_clearing_a_translation_removes_it(): void
    {
        $this->enableLanguages('en');
        $item = $this->writer()->create($this->menu, ['translations' => ['default' => ['title' => 'A'], 'en' => ['title' => 'B', 'description' => 'D']]]);

        $this->writer()->update($item, ['translations' => ['en' => ['title' => '', 'description' => '']]]);

        $this->assertSame(['default'], $item->translations()->pluck('lang')->all());
    }

    public function test_an_item_without_an_address_is_a_heading(): void
    {
        $item = $this->writer()->create($this->menu, ['url' => '', 'translations' => ['default' => ['title' => 'Group']]]);

        $this->assertNull($item->fresh()->url);
    }

    public function test_an_invalid_url_is_rejected_and_nothing_is_written(): void
    {
        $this->assertArrayHasKey('url', $this->errorsOf(
            fn () => $this->writer()->create($this->menu, ['url' => 'javascript:alert(1)', 'translations' => ['default' => ['title' => 'A']]]),
        ));
        $this->assertSame(0, MenuItem::query()->count());
    }

    public function test_dates_are_stored_to_the_minute_and_blank_clears_them(): void
    {
        $item = $this->writer()->create($this->menu, [
            'published_at' => '2026-03-01 10:15:42', 'unpublished_at' => '2026-03-02T10:15',
            'translations' => ['default' => ['title' => 'A']],
        ]);

        $this->assertSame('2026-03-01 10:15:00', $item->fresh()->published_at->format('Y-m-d H:i:s'));
        $this->assertSame('2026-03-02 10:15:00', $item->fresh()->unpublished_at->format('Y-m-d H:i:s'));

        $this->writer()->update($item, ['published_at' => '', 'unpublished_at' => null]);

        $this->assertNull($item->fresh()->published_at);
        $this->assertNull($item->fresh()->unpublished_at);
    }

    public function test_a_contradictory_schedule_is_rejected(): void
    {
        $this->assertArrayHasKey('unpublished_at', $this->errorsOf(fn () => $this->writer()->create($this->menu, [
            'published_at' => '2026-03-02 10:00', 'unpublished_at' => '2026-03-01 10:00',
            'translations' => ['default' => ['title' => 'A']],
        ])));
    }

    public function test_disabling_keeps_the_dates(): void
    {
        $item = $this->writer()->create($this->menu, [
            'published_at' => '2026-03-01 10:00', 'unpublished_at' => '2026-03-02 10:00',
            'translations' => ['default' => ['title' => 'A']],
        ]);

        $this->writer()->update($item, ['is_active' => false]);

        $fresh = $item->fresh();
        $this->assertFalse($fresh->is_active);
        $this->assertSame('2026-03-01 10:00', $fresh->published_at->format('Y-m-d H:i'));
        $this->assertSame('2026-03-02 10:00', $fresh->unpublished_at->format('Y-m-d H:i'));
    }

    public function test_a_translation_may_hold_only_a_description(): void
    {
        $this->enableLanguages('en');
        $e = $this->writer()->create($this->menu, ['translations' => ['default' => ['title' => 'Base'], 'en' => ['description' => 'Only text']]]);

        $row = $e->translations()->where('lang', 'en')->firstOrFail();
        $this->assertNull($row->title);
        $this->assertSame('Only text', $row->description);

        $this->writer()->update($e, ['translations' => ['en' => ['description' => '']]]);
        $this->assertSame(0, $e->translations()->where('lang', 'en')->count());
    }

    public function test_saving_one_language_drops_values_equal_to_the_base_and_leaves_other_languages(): void
    {
        $this->enableLanguages('en', 'pl');
        $e = $this->writer()->create($this->menu, ['translations' => [
            'default' => ['title' => 'Base', 'description' => 'Base text'], 'pl' => ['title' => 'Polski'],
        ]]);

        $this->writer()->saveTranslation($e, 'en', ['title' => 'Base', 'description' => 'English text']);

        $en = $e->translations()->where('lang', 'en')->firstOrFail();
        $this->assertNull($en->title);
        $this->assertSame('English text', $en->description);
        $this->assertSame('Polski', $e->translations()->where('lang', 'pl')->value('title'));
        $this->assertSame('Base', $e->translations()->where('lang', 'default')->value('title'));
        $this->assertSame('Base text', $e->translations()->where('lang', 'default')->value('description'));
    }

    public function test_saving_nothing_new_deletes_the_translation(): void
    {
        $this->enableLanguages('en');
        $e = $this->writer()->create($this->menu, ['translations' => ['default' => ['title' => 'Base', 'description' => 'Text'], 'en' => ['title' => 'English']]]);

        $this->writer()->saveTranslation($e, 'en', ['title' => 'Base', 'description' => 'Text']);

        $this->assertSame(0, $e->translations()->where('lang', 'en')->count());
        $this->assertSame(1, $e->translations()->count());
    }

    public function test_saving_one_language_rejects_the_base_and_unavailable_languages(): void
    {
        $this->enableLanguages('en');
        $e = $this->writer()->create($this->menu, ['translations' => ['default' => ['title' => 'Base']]]);

        $this->assertArrayHasKey('lang', $this->errorsOf(fn () => $this->writer()->saveTranslation($e, 'default', ['title' => 'X'])));
        $this->assertArrayHasKey('lang', $this->errorsOf(fn () => $this->writer()->saveTranslation($e, 'zz', ['title' => 'X'])));
        $this->assertArrayHasKey('lang', $this->errorsOf(fn () => $this->writer()->clearTranslation($e, 'default')));
        $this->assertSame(1, $e->translations()->count());
    }

    public function test_clearing_one_language_keeps_the_rest(): void
    {
        $this->enableLanguages('en', 'pl');
        $e = $this->writer()->create($this->menu, ['translations' => ['default' => ['title' => 'Base'], 'en' => ['title' => 'English'], 'pl' => ['title' => 'Polski']]]);

        $this->writer()->clearTranslation($e, 'en');
        $this->writer()->clearTranslation($e, 'en');

        $this->assertSame(['default', 'pl'], $e->translations()->orderBy('lang')->pluck('lang')->all());
    }

    public function test_the_image_is_bound_replaced_and_cleared(): void
    {
        $first = $this->image();
        $second = $this->image();
        $item = $this->writer()->create($this->menu, ['image' => $first->path, 'translations' => ['default' => ['title' => 'A']]]);

        $this->assertSame($first->path, $item->fresh()->image);
        $this->assertSame([MenuItem::MEDIA_OWNER], MediaRelation::query()->where('file_uuid', $first->uuid)->pluck('owner_type')->all());

        $this->writer()->update($item, ['image' => $second->path]);
        $this->assertSame([$first->uuid => 0, $second->uuid => 1], app(FileRelations::class)->usageCounts([$first->uuid, $second->uuid]));

        $this->writer()->update($item, ['image' => '']);
        $this->assertNull($item->fresh()->image);
        $this->assertSame([$second->uuid => 0], app(FileRelations::class)->usageCounts([$second->uuid]));
    }

    public function test_a_non_image_or_missing_file_is_rejected(): void
    {
        $document = $this->image('document');

        $this->assertArrayHasKey('image', $this->errorsOf(
            fn () => $this->writer()->create($this->menu, ['image' => $document->path, 'translations' => ['default' => ['title' => 'A']]]),
        ));
        $this->assertArrayHasKey('image', $this->errorsOf(
            fn () => $this->writer()->create($this->menu, ['image' => str_repeat('a', 32).'.png', 'translations' => ['default' => ['title' => 'A']]]),
        ));
        $this->assertSame(0, MenuItem::query()->count());
    }

    public function test_deleting_an_item_with_an_image_frees_the_file(): void
    {
        $image = $this->image();
        $item = $this->writer()->create($this->menu, ['image' => $image->path, 'translations' => ['default' => ['title' => 'A']]]);

        $item->delete();

        $this->assertSame([$image->uuid => 0], app(FileRelations::class)->usageCounts([$image->uuid]));
        $this->assertNotNull(File::findByUuid($image->uuid));
        $this->assertSame(0, $item->translations()->count());
    }

    public function test_a_failed_update_does_not_apply_translations(): void
    {
        $item = $this->writer()->create($this->menu, ['translations' => ['default' => ['title' => 'Old']]]);

        $this->errorsOf(fn () => $this->writer()->update($item, ['url' => '//bad', 'translations' => ['default' => ['title' => 'New']]]));

        $this->assertSame('Old', $item->translations()->value('title'));
    }
}
