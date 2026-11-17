<?php

namespace Webtack\Ecms\Tests\Feature\Content;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Webtack\Ecms\Content\ContentNodeCreator;
use Webtack\Ecms\Content\ContentNodeQuery;
use Webtack\Ecms\Content\ContentValueReader;
use Webtack\Ecms\Content\ContentValueWriter;
use Webtack\Ecms\Content\FieldTypeRegistry;
use Webtack\Ecms\Content\NodeQueryParams;
use Webtack\Ecms\Files\FileStorage;
use Webtack\Ecms\Models\ContentField;
use Webtack\Ecms\Models\ContentNode;
use Webtack\Ecms\Models\ContentType;
use Webtack\Ecms\Models\File;
use Webtack\Ecms\Models\MediaRelation;
use Webtack\Ecms\Tests\Feature\Content\Concerns\BuildsArticleType;
use Webtack\Ecms\Tests\TestCase;

class FileFieldTypesTest extends TestCase
{
    use BuildsArticleType, RefreshDatabase;

    private ContentType $type;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        $this->type = $this->articleType();
        $this->field('cover', 'image');
        $this->field('attachment', 'file');
        $this->field('gallery', 'image', ['is_multiple' => true]);
    }

    /** @param array<string, mixed> $extra */
    private function field(string $key, string $type, array $extra = [], ?array $settings = null): ContentField
    {
        return ContentField::create(['content_type' => 'article', 'key' => $key, 'name' => ucfirst($key), 'type' => $type, 'settings' => $settings, ...$extra]);
    }

    private function png(int $size = 1000): File
    {
        return $this->file('image', 'image/png', 'png', $size);
    }

    private function jpeg(int $size = 1000): File
    {
        return $this->file('image', 'image/jpeg', 'jpg', $size);
    }

    private function pdf(): File
    {
        return $this->file('document', 'application/pdf', 'pdf', 1000);
    }

    private function file(string $profile, string $mime, string $ext, int $size): File
    {
        $uuid = File::newUuid();

        return File::factory()->create(['uuid' => $uuid, 'profile' => $profile, 'mime' => $mime, 'path' => "{$uuid}.{$ext}", 'size' => $size]);
    }

    private function value(File $file): string
    {
        return $file->path;
    }

    /** @param array<string, mixed> $values */
    private function write(ContentNode $node, array $values): void
    {
        app(ContentValueWriter::class)->write($node, $values);
    }

    private function read(ContentNode $node, string $key): mixed
    {
        return app(ContentValueReader::class)->load([$node], [$key])[$node->id][$key];
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

    /** @return list<string> */
    private function related(ContentNode $node, string $role): array
    {
        return MediaRelation::query()->where('owner_id', $node->id)->where('role', $role)->pluck('file_uuid')->all();
    }

    // --- Values ---

    public function test_both_types_share_the_string_table(): void
    {
        $this->assertSame('content_values_string', app(FieldTypeRegistry::class)->get('image')->table());
        $this->assertSame('content_values_string', app(FieldTypeRegistry::class)->get('file')->table());
    }

    public function test_an_image_and_a_file_are_stored_and_read_back_as_the_same_string(): void
    {
        $image = $this->png();
        $document = $this->pdf();
        $node = $this->makeArticle();

        $this->write($node, ['cover' => $this->value($image), 'attachment' => $this->value($document)]);

        $this->assertSame($image->uuid.'.png', $this->read($node, 'cover'));
        $this->assertSame($document->uuid.'.pdf', $this->read($node, 'attachment'));
        $this->assertSame($image->uuid.'.png', DB::table('content_values_string')->where('field_key', 'cover')->value('value'));
    }

    public function test_a_multiple_field_keeps_the_order(): void
    {
        [$a, $b, $c] = [$this->png(), $this->jpeg(), $this->png()];
        $node = $this->makeArticle();

        $this->write($node, ['gallery' => [$this->value($b), $this->value($a), $this->value($c)]]);

        $this->assertSame([$b->path, $a->path, $c->path], $this->read($node, 'gallery'));
    }

    public function test_null_clears_the_value_and_the_file_stays(): void
    {
        $image = $this->png();
        $node = $this->makeArticle();
        $this->write($node, ['cover' => $this->value($image)]);

        $this->write($node, ['cover' => null]);

        $this->assertNull($this->read($node, 'cover'));
        $this->assertDatabaseHas('media', ['uuid' => $image->uuid]);
    }

    public function test_filtering_by_the_stored_value_finds_the_node(): void
    {
        $image = $this->png();
        $with = $this->makeArticle(['title' => 'With'], 'with');
        $this->makeArticle(['title' => 'Without'], 'without');
        $this->write($with, ['cover' => $this->value($image)]);

        $page = app(ContentNodeQuery::class)->paginate($this->type, new NodeQueryParams(
            filters: [['field' => 'cover', 'operator' => 'eq', 'value' => $this->value($image)]],
        ));

        $this->assertSame(['With'], collect($page->items())->map(fn (ContentNode $n) => $n->values['title'])->all());
    }

    public function test_a_filter_with_an_arbitrary_string_is_rejected(): void
    {
        $this->assertInvalid(fn () => app(ContentNodeQuery::class)->paginate($this->type, new NodeQueryParams(
            filters: [['field' => 'cover', 'operator' => 'eq', 'value' => '../x']],
        )), 'filters.0.value');
    }

    // --- Validation ---

    public function test_arbitrary_strings_are_rejected(): void
    {
        $node = $this->makeArticle();

        foreach (['../secret.png', 'logo.png', str_repeat('a', 32), 'x/'.File::newUuid().'.png', strtoupper(File::newUuid()).'.png'] as $value) {
            $this->assertInvalid(fn () => $this->write($node, ['cover' => $value]), 'cover');
        }
    }

    public function test_a_missing_file_is_rejected(): void
    {
        $this->assertInvalid(fn () => $this->write($this->makeArticle(), ['cover' => File::newUuid().'.png']), 'cover');
    }

    public function test_a_document_in_an_image_field_and_an_image_in_a_file_field_are_rejected(): void
    {
        $node = $this->makeArticle();

        $this->assertInvalid(fn () => $this->write($node, ['cover' => $this->value($this->pdf())]), 'cover');
        $this->assertInvalid(fn () => $this->write($node, ['attachment' => $this->value($this->png())]), 'attachment');
    }

    public function test_a_wrong_extension_is_rejected(): void
    {
        $image = $this->png();

        $this->assertInvalid(fn () => $this->write($this->makeArticle(), ['cover' => $image->uuid.'.jpg']), 'cover');
    }

    public function test_the_type_and_size_must_be_within_the_field_settings(): void
    {
        $this->field('avatar', 'image', settings: ['mimes' => ['image/png'], 'max_size' => 1]);
        $node = $this->makeArticle();

        $this->assertInvalid(fn () => $this->write($node, ['avatar' => $this->value($this->jpeg())]), 'avatar');
        $this->assertInvalid(fn () => $this->write($node, ['avatar' => $this->value($this->png(2 * 1024))]), 'avatar');

        $this->write($node, ['avatar' => $this->value($this->png(1024))]);
        $this->assertNotNull($this->read($node, 'avatar'));
    }

    public function test_one_file_can_pass_one_field_and_fail_another(): void
    {
        $this->field('only_png', 'image', settings: ['mimes' => ['image/png']]);
        $this->field('only_jpeg', 'image', settings: ['mimes' => ['image/jpeg']]);
        $png = $this->png();
        $node = $this->makeArticle();

        $this->write($node, ['only_png' => $this->value($png)]);

        $this->assertInvalid(fn () => $this->write($node, ['only_jpeg' => $this->value($png)]), 'only_jpeg');
    }

    public function test_narrowing_the_settings_keeps_the_stored_values(): void
    {
        $jpeg = $this->jpeg();
        $node = $this->makeArticle();
        $this->write($node, ['cover' => $this->value($jpeg)]);

        $field = ContentField::query()->where('key', 'cover')->firstOrFail();
        $field->update(['settings' => ['mimes' => ['image/png']]]);

        $this->assertSame($this->value($jpeg), $this->read($node, 'cover'));
        $this->assertInvalid(fn () => $this->write($node, ['cover' => $this->value($jpeg)]), 'cover');
        $this->assertSame([$jpeg->uuid], $this->related($node, 'cover'));
    }

    // --- URL and path ---

    public function test_the_url_of_a_value_needs_no_query(): void
    {
        $value = $this->value($this->png());

        DB::enableQueryLog();
        $url = app(FileStorage::class)->urlForValue('image', $value);
        $queries = DB::getQueryLog();
        DB::disableQueryLog();

        $this->assertSame([], $queries);
        $this->assertSame(Storage::disk('public')->url($value), $url);
    }

    public function test_the_path_of_every_stored_file_is_its_uuid_and_extension_in_the_disk_root(): void
    {
        $storage = app(FileStorage::class);
        $png = imagecreatetruecolor(4, 4);
        ob_start();
        imagepng($png);
        $pngBytes = ob_get_clean();

        $image = $storage->store($this->upload($pngBytes, 'a.png'), 'image');
        $document = $storage->store($this->upload('%PDF-1.4 x', 'a.pdf'), 'document');

        foreach ([$image, $document] as $file) {
            $this->assertSame($file->uuid.'.'.pathinfo($file->path, PATHINFO_EXTENSION), $file->path);
            $this->assertStringNotContainsString('/', $file->path);
            $this->assertSame($storage->url($file), $storage->urlForValue($file->profile, $file->path));
        }
    }

    private function upload(string $content, string $name): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'ff');
        file_put_contents($path, $content);

        return new UploadedFile($path, $name, null, null, true);
    }

    // --- Relations (writer hook) ---

    public function test_saving_a_value_binds_the_file_to_the_node_under_the_field_key(): void
    {
        $image = $this->png();
        $node = $this->makeArticle();

        $this->write($node, ['cover' => $this->value($image)]);

        $this->assertDatabaseHas('media_relations', [
            'file_uuid' => $image->uuid, 'owner_type' => 'content_node', 'owner_id' => $node->id, 'role' => 'cover', 'lang' => 'default',
        ]);
    }

    public function test_replacing_a_file_moves_the_relation_and_keeps_the_old_file(): void
    {
        [$a, $b] = [$this->png(), $this->png()];
        $node = $this->makeArticle();
        $this->write($node, ['cover' => $this->value($a)]);

        $this->write($node, ['cover' => $this->value($b)]);

        $this->assertSame([$b->uuid], $this->related($node, 'cover'));
        $this->assertDatabaseHas('media', ['uuid' => $a->uuid]);
    }

    public function test_clearing_a_field_removes_the_relation(): void
    {
        $image = $this->png();
        $node = $this->makeArticle();
        $this->write($node, ['cover' => $this->value($image)]);

        $this->write($node, ['cover' => null]);

        $this->assertSame([], $this->related($node, 'cover'));
        $this->assertDatabaseHas('media', ['uuid' => $image->uuid]);
    }

    public function test_a_multiple_field_has_a_relation_per_file(): void
    {
        $files = [$this->png(), $this->png(), $this->jpeg()];
        $node = $this->makeArticle();

        $this->write($node, ['gallery' => array_map($this->value(...), $files)]);

        $this->assertEqualsCanonicalizing(array_map(fn (File $f) => $f->uuid, $files), $this->related($node, 'gallery'));
    }

    public function test_a_failed_validation_changes_no_relations(): void
    {
        $image = $this->png();
        $node = $this->makeArticle();
        $this->write($node, ['cover' => $this->value($image)]);

        $this->assertInvalid(fn () => $this->write($node, ['cover' => $this->value($this->png()), 'attachment' => '../x']), 'attachment');

        $this->assertSame([$image->uuid], $this->related($node, 'cover'));
    }

    public function test_other_value_types_do_not_touch_the_relations(): void
    {
        $image = $this->png();
        $node = $this->makeArticle();
        $this->write($node, ['cover' => $this->value($image)]);

        $this->write($node, ['visits' => 5, 'title' => 'Changed']);

        $this->assertSame([$image->uuid], $this->related($node, 'cover'));
    }

    // --- Cleanup ---

    public function test_deleting_a_node_removes_its_relations_and_keeps_the_files(): void
    {
        $image = $this->png();
        $node = $this->makeArticle();
        $other = $this->makeArticle(['title' => 'Other'], 'other');
        $this->write($node, ['cover' => $this->value($image)]);
        $this->write($other, ['cover' => $this->value($image)]);

        $node->delete();

        $this->assertSame(0, MediaRelation::query()->where('owner_id', $node->id)->count());
        $this->assertSame(1, $image->relations()->count());
        $this->assertDatabaseHas('media', ['uuid' => $image->uuid]);
    }

    public function test_deleting_a_field_removes_its_relations_only(): void
    {
        $image = $this->png();
        $node = $this->makeArticle();
        $this->write($node, ['cover' => $this->value($image), 'gallery' => [$this->value($image)]]);

        // A field with values is kept, so its values go first (raw, leaving the relations behind).
        DB::table('content_values_string')->where('field_key', 'gallery')->delete();
        ContentField::query()->where('key', 'gallery')->firstOrFail()->delete();

        $this->assertSame([], $this->related($node, 'gallery'));
        $this->assertSame([$image->uuid], $this->related($node, 'cover'));
        $this->assertDatabaseHas('media', ['uuid' => $image->uuid]);
    }

    public function test_deleting_a_field_does_not_touch_a_same_named_field_of_another_type(): void
    {
        $image = $this->png();
        ContentType::create(['key' => 'page_a', 'name' => 'A']);
        ContentField::create(['content_type' => 'page_a', 'key' => 'cover', 'name' => 'Cover', 'type' => 'image']);
        $other = app(ContentNodeCreator::class)->create(ContentType::findByKey('page_a'), ['title' => 'A'], authorId: $this->authorId());
        $node = $this->makeArticle();
        $this->write($node, ['cover' => $this->value($image)]);
        $this->write($other, ['cover' => $this->value($image)]);

        DB::table('content_values_string')->where('content_type', 'page_a')->where('field_key', 'cover')->delete();
        ContentField::query()->where('content_type', 'page_a')->where('key', 'cover')->firstOrFail()->delete();

        $this->assertSame([$image->uuid], $this->related($node, 'cover'));
        $this->assertSame([], $this->related($other, 'cover'));
    }

    // --- Type change ---

    public function test_the_type_of_a_field_with_values_cannot_change_between_string_image_and_file(): void
    {
        $image = $this->png();
        $node = $this->makeArticle();
        $this->write($node, ['cover' => $this->value($image)]);
        $this->field('label', 'string');
        $this->write($node, ['label' => 'text']);

        $pairs = [['cover', 'string'], ['cover', 'file'], ['label', 'image'], ['label', 'file']];
        foreach ($pairs as [$key, $to]) {
            $this->assertInvalid(
                fn () => ContentField::query()->where('key', $key)->firstOrFail()->update(['type' => $to]),
                'type',
            );
        }

        $this->assertSame($this->value($image), $this->read($node, 'cover'));
    }

    public function test_the_type_of_a_field_without_values_cannot_change_either(): void
    {
        $field = ContentField::query()->where('key', 'attachment')->firstOrFail();

        foreach (['image', 'string'] as $to) {
            $this->assertInvalid(fn () => $field->fresh()->update(['type' => $to]), 'type');
        }

        $this->assertSame('file', $field->fresh()->type);
    }
}
