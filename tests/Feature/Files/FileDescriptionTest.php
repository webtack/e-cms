<?php

namespace Webtack\Ecms\Tests\Feature\Files;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Webtack\Ecms\Content\ContentLanguages;
use Webtack\Ecms\Files\FileDescriptions;
use Webtack\Ecms\Files\FileStorage;
use Webtack\Ecms\Models\File;
use Webtack\Ecms\Models\FileTranslation;
use Webtack\Ecms\Tests\Feature\Content\Concerns\BuildsArticleType;
use Webtack\Ecms\Tests\TestCase;

/**
 * Translated file descriptions kept in `media_translations`.
 */
class FileDescriptionTest extends TestCase
{
    use BuildsArticleType, RefreshDatabase;

    private function descriptions(): FileDescriptions
    {
        return app(FileDescriptions::class);
    }

    private function file(?string $description = 'Лицьова обкладинка'): File
    {
        return File::factory()->create(['description' => $description, 'original_name' => 'cover.pdf']);
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->enableLanguages('en', 'pl');
    }

    public function test_a_translation_is_stored_next_to_the_base_description(): void
    {
        $file = $this->file();

        $this->descriptions()->save($file->uuid, 'en', ' Front cover ');

        $this->assertSame('Front cover', $this->descriptions()->forFile($file->uuid, 'en'));
        $this->assertNull($this->descriptions()->forFile($file->uuid, 'pl'));
        $this->assertSame('Лицьова обкладинка', $file->fresh()->description);
        $this->assertSame('cover.pdf', $file->fresh()->original_name);
    }

    public function test_a_description_equal_to_the_base_is_not_stored_and_removes_the_old_one(): void
    {
        $file = $this->file();
        $this->descriptions()->save($file->uuid, 'en', 'Front cover');

        $this->descriptions()->save($file->uuid, 'en', ' Лицьова обкладинка ');

        $this->assertSame(0, FileTranslation::query()->count());
    }

    public function test_an_emptied_description_is_removed(): void
    {
        $file = $this->file();
        $this->descriptions()->save($file->uuid, 'en', 'Front cover');

        $this->descriptions()->save($file->uuid, 'en', '  ');

        $this->assertSame(0, FileTranslation::query()->count());
    }

    public function test_a_translated_name_is_display_only_and_follows_the_same_rules(): void
    {
        $file = $this->file();

        $this->descriptions()->save($file->uuid, 'en', null, ' Cover ');
        $this->assertSame([$file->uuid => 'Cover'], $this->descriptions()->namesForFiles([$file->uuid], 'en'));
        $this->assertSame(['en' => ['name' => 'Cover', 'description' => null]], $this->descriptions()->translations($file->uuid));
        $this->assertSame('cover.pdf', $file->fresh()->original_name);

        // A description added later keeps the name; a null name leaves it alone.
        $this->descriptions()->save($file->uuid, 'en', 'Front cover');
        $this->assertSame(['en' => ['name' => 'Cover', 'description' => 'Front cover']], $this->descriptions()->translations($file->uuid));

        // Equal to the original name or emptied removes the name; the description stays.
        $this->descriptions()->saveMany([$file->uuid => ['name' => 'cover.pdf']], 'en');
        $this->assertSame(['en' => ['name' => null, 'description' => 'Front cover']], $this->descriptions()->translations($file->uuid));

        // A row without a name and a description is deleted.
        $this->descriptions()->saveMany([$file->uuid => ['description' => '']], 'en');
        $this->assertSame(0, FileTranslation::query()->count());
    }

    public function test_a_file_without_a_base_description_can_have_a_translation(): void
    {
        $file = $this->file(null);

        $this->descriptions()->save($file->uuid, 'en', 'Cover');

        $this->assertSame('Cover', $this->descriptions()->forFile($file->uuid, 'en'));
    }

    public function test_several_files_and_languages_are_read_apart(): void
    {
        [$a, $b] = [$this->file(), $this->file()];
        $this->descriptions()->saveMany([$a->uuid => 'A', $b->uuid => 'B', str_repeat('0', 32) => 'Ghost'], 'en');
        $this->descriptions()->save($a->uuid, 'pl', 'A pl');

        $this->assertSame([$a->uuid => 'A', $b->uuid => 'B'], $this->descriptions()->forFiles([$a->uuid, $b->uuid], 'en'));
        $this->assertSame(['en' => 'A', 'pl' => 'A pl'], $this->descriptions()->all($a->uuid));
        $this->assertSame([], $this->descriptions()->forFiles([], 'en'));
        $this->assertSame(3, FileTranslation::query()->count());
    }

    public function test_clear_removes_one_language_only(): void
    {
        $file = $this->file();
        $this->descriptions()->save($file->uuid, 'en', 'A');
        $this->descriptions()->save($file->uuid, 'pl', 'B');

        $this->descriptions()->clear([$file->uuid], 'en');

        $this->assertSame(['pl' => 'B'], $this->descriptions()->all($file->uuid));
    }

    public function test_the_base_inactive_and_unknown_languages_are_rejected(): void
    {
        $file = $this->file();

        foreach (['default', 'de'] as $lang) {
            try {
                $this->descriptions()->save($file->uuid, $lang, 'X');
                $this->fail("Language {$lang} must be rejected.");
            } catch (ValidationException $e) {
                $this->assertArrayHasKey('lang', $e->errors());
            }
        }

        $this->assertSame(0, FileTranslation::query()->count());
    }

    public function test_deleting_the_file_deletes_its_translations(): void
    {
        Storage::fake('public');
        $file = $this->file();
        $this->descriptions()->save($file->uuid, 'en', 'A');

        app(FileStorage::class)->delete($file);

        $this->assertSame(0, FileTranslation::query()->count());
    }

    public function test_a_language_with_a_translated_description_cannot_be_deleted(): void
    {
        $file = $this->file();
        $languages = app(ContentLanguages::class);
        $this->assertFalse($languages->isInUse('en'));

        $this->descriptions()->save($file->uuid, 'en', 'A');

        $this->assertTrue($languages->isInUse('en'));
        $this->assertFalse($languages->isInUse('pl'));
    }

    public function test_resolve_falls_back_to_the_base_values_per_field(): void
    {
        $file = $this->file('Лицьова обкладинка');
        $this->descriptions()->save($file->uuid, 'en', 'Front cover');

        $english = $this->descriptions()->resolve([$file->uuid], 'en');
        $polish = $this->descriptions()->resolve([$file->uuid], 'pl');

        $this->assertSame(['name' => 'cover.pdf', 'description' => 'Front cover'], $english[$file->uuid]);
        $this->assertSame(['name' => 'cover.pdf', 'description' => 'Лицьова обкладинка'], $polish[$file->uuid]);
    }
}
