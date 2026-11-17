<?php

namespace Webtack\Ecms\Tests\Feature\Files;

use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Webtack\Ecms\Files\FileRelations;
use Webtack\Ecms\Files\FileStorage;
use Webtack\Ecms\Files\FileTypes;
use Webtack\Ecms\Files\UploadRules;
use Webtack\Ecms\Models\File;
use Webtack\Ecms\Tests\Fixtures\User;
use Webtack\Ecms\Tests\TestCase;
use ZipArchive;

/**
 * Real bytes are written to temp files (not `UploadedFile::fake()`), because the service detects
 * the type from the content and a fake would be typed by its name.
 */
class FileStorageTest extends TestCase
{
    use RefreshDatabase;

    /** @var list<string> */
    private array $tempFiles = [];

    private FileStorage $storage;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        $this->storage = app(FileStorage::class);
    }

    protected function tearDown(): void
    {
        foreach ($this->tempFiles as $path) {
            @unlink($path);
        }

        parent::tearDown();
    }

    public function test_it_is_a_singleton(): void
    {
        $this->assertSame($this->storage, app(FileStorage::class));
    }

    // --- Image scaling ---

    public function test_a_large_image_is_scaled_down_and_the_row_describes_the_stored_file(): void
    {
        config(['files.profiles.image.max_width' => 400, 'files.profiles.image.max_height' => 400]);
        $upload = $this->png(1200, 900);
        $originalSize = filesize($upload->getRealPath());

        $file = $this->storage->store($upload, 'image');
        $stored = Storage::disk('public')->get($file->path);

        $this->assertSame([400, 300], [$file->width, $file->height]);
        $this->assertSame([400, 300], array_slice(getimagesizefromstring($stored), 0, 2));
        $this->assertSame(strlen($stored), $file->size);
        $this->assertSame(hash('sha256', $stored), $file->hash);
        $this->assertNotSame($originalSize, $file->size);
        $this->assertSame('image/png', $file->mime);
        $this->assertStringEndsWith('.png', $file->path);
    }

    public function test_an_image_within_the_limits_is_stored_byte_for_byte(): void
    {
        config(['files.profiles.image.max_width' => 400, 'files.profiles.image.max_height' => 400]);
        $upload = $this->png(200, 100);
        $original = file_get_contents($upload->getRealPath());

        $file = $this->storage->store($upload, 'image');

        $this->assertSame($original, Storage::disk('public')->get($file->path));
        $this->assertSame(hash('sha256', $original), $file->hash);
        $this->assertSame(strlen($original), $file->size);
        $this->assertSame([200, 100], [$file->width, $file->height]);
    }

    public function test_field_rules_narrow_the_pixel_limits_of_the_profile(): void
    {
        config(['files.profiles.image.max_width' => 1000, 'files.profiles.image.max_height' => 1000]);
        $rules = new UploadRules('image', ['image/png'], 5120, 300, null);

        $file = $this->storage->store($this->png(900, 600), 'image', null, $rules);

        $this->assertSame([300, 200], [$file->width, $file->height]);
    }

    public function test_the_size_limit_applies_to_the_original_before_scaling(): void
    {
        config([
            'files.profiles.image.max_size' => 10,
            'files.profiles.image.max_width' => 100,
            'files.profiles.image.max_height' => 100,
        ]);

        $this->assertRejected($this->noisyJpeg(800, 800), 'image');
    }

    public function test_an_image_with_too_many_pixels_is_rejected_before_decoding(): void
    {
        config(['files.image.max_pixels' => 1000]);

        $this->assertRejected($this->png(100, 100), 'image');
    }

    public function test_an_image_within_the_pixel_limit_is_accepted(): void
    {
        config(['files.image.max_pixels' => 10_000]);

        $this->assertSame(100, $this->storage->store($this->png(100, 100), 'image')->width);
    }

    public function test_a_corrupt_image_is_rejected_and_nothing_is_stored(): void
    {
        config(['files.profiles.image.max_width' => 100, 'files.profiles.image.max_height' => 100]);
        $bytes = file_get_contents($this->noisyJpeg(800, 800)->getRealPath());
        $upload = $this->upload(substr($bytes, 0, 400).random_bytes(5000), 'broken.jpg');

        $this->assertRejected($upload, 'image');
    }

    public function test_the_processing_error_has_a_message_under_the_file_key(): void
    {
        config(['files.profiles.image.max_width' => 100, 'files.profiles.image.max_height' => 100]);
        $bytes = file_get_contents($this->noisyJpeg(800, 800)->getRealPath());

        try {
            $this->storage->store($this->upload(substr($bytes, 0, 400).random_bytes(5000), 'broken.jpg'), 'image');
            $this->fail('A corrupt image must be rejected.');
        } catch (ValidationException $e) {
            $this->assertSame(__('The image could not be processed.'), $e->errors()['file'][0]);
        }
    }

    // --- Record keeping ---

    public function test_a_document_is_recorded_with_its_metadata(): void
    {
        $upload = $this->upload('%PDF-1.4 minimal', 'report.pdf');

        $file = $this->storage->store($upload, 'document');

        $this->assertSame('report.pdf', $file->original_name);
        $this->assertSame('application/pdf', $file->mime);
        $this->assertSame(strlen('%PDF-1.4 minimal'), $file->size);
        $this->assertSame(hash('sha256', '%PDF-1.4 minimal'), $file->hash);
        $this->assertSame('document', $file->profile);
        $this->assertSame('public', $file->disk);
        $this->assertNull($file->width);
        $this->assertNull($file->height);
        $this->assertSame(1, preg_match('/^[0-9a-f]{32}$/', $file->uuid));
        $this->assertNull($file->user_id);
    }

    public function test_an_image_records_its_dimensions(): void
    {
        $file = $this->storage->store($this->png(640, 480), 'image');

        $this->assertSame('image/png', $file->mime);
        $this->assertSame(640, $file->width);
        $this->assertSame(480, $file->height);
    }

    public function test_identical_content_makes_two_rows_with_one_hash(): void
    {
        $first = $this->storage->store($this->upload('%PDF-1.4 same', 'a.pdf'), 'document');
        $second = $this->storage->store($this->upload('%PDF-1.4 same', 'b.pdf'), 'document');

        $this->assertNotSame($first->id, $second->id);
        $this->assertSame($first->hash, $second->hash);
        $this->assertNotSame($first->path, $second->path);
    }

    // --- Profiles ---

    public function test_the_file_lands_in_the_root_of_the_profile_disk(): void
    {
        $file = $this->storage->store($this->png(), 'image');

        $this->assertSame($file->uuid.'.png', $file->path);
        Storage::disk('public')->assertExists($file->path);
    }

    public function test_the_uploader_is_recorded(): void
    {
        $user = User::factory()->create();

        $this->assertSame($user->id, $this->storage->store($this->png(), 'image', $user)->user_id);
        $this->assertTrue($this->storage->store($this->png(), 'image', $user)->uploader->is($user));
    }

    public function test_the_disk_comes_from_the_profile_config(): void
    {
        Storage::fake('local');
        config(['files.profiles.image.disk' => 'local']);

        $file = $this->storage->store($this->png(), 'image');

        $this->assertSame('local', $file->disk);
        Storage::disk('local')->assertExists($file->path);
        Storage::disk('public')->assertMissing($file->path);
    }

    public function test_an_unknown_profile_stores_nothing(): void
    {
        try {
            $this->storage->store($this->png(), 'nope');
            $this->fail('An unknown profile must throw.');
        } catch (\InvalidArgumentException) {
            $this->assertSame(0, File::query()->count());
            $this->assertSame([], Storage::disk('public')->allFiles());
        }
    }

    // --- Validation by profile ---

    public function test_a_genuine_jpeg_is_accepted(): void
    {
        $file = $this->storage->store($this->jpeg(), 'image');

        $this->assertSame('image/jpeg', $file->mime);
        $this->assertStringEndsWith('.jpg', $file->path);
    }

    public function test_a_pdf_is_rejected_by_the_image_profile(): void
    {
        $this->assertRejected($this->upload('%PDF-1.4 x', 'doc.pdf'), 'image');
    }

    public function test_text_named_like_a_jpeg_is_rejected(): void
    {
        $this->assertRejected($this->upload('just some text', 'photo.jpg'), 'image');
    }

    public function test_a_file_over_the_profile_limit_is_rejected(): void
    {
        config(['files.profiles.document.max_size' => 1]);

        $this->assertRejected($this->upload('%PDF-1.4 '.str_repeat('x', 2000), 'big.pdf'), 'document');
    }

    public function test_a_file_exactly_at_the_limit_is_accepted(): void
    {
        config(['files.profiles.document.max_size' => 1]);

        $file = $this->storage->store($this->upload('%PDF-1.4 '.str_repeat('x', 1024 - 9), 'edge.pdf'), 'document');

        $this->assertSame(1024, $file->size);
    }

    // --- Limits of the default profiles ---

    public function test_an_image_of_25_megabytes_fits_the_default_image_profile(): void
    {
        $file = $this->storage->store($this->paddedPng(25 * 1024 * 1024), 'image');

        $this->assertSame(25 * 1024 * 1024, $file->size);
    }

    public function test_an_image_over_30_megabytes_is_rejected_by_the_default_image_profile(): void
    {
        $this->assertRejected($this->paddedPng(31 * 1024 * 1024), 'image');
    }

    public function test_a_word_97_document_is_accepted_by_the_document_profile(): void
    {
        $upload = new UploadedFile($this->copyFixture('word97.doc'), 'plan.doc', null, null, true);

        $file = $this->storage->store($upload, 'document');

        $this->assertSame('application/msword', $file->mime);
        $this->assertStringEndsWith('.doc', $file->path);
        $this->assertSame('doc', FileTypes::label($file->mime));
    }

    public function test_a_word_97_document_is_rejected_by_the_image_profile(): void
    {
        $this->assertRejected(new UploadedFile($this->copyFixture('word97.doc'), 'plan.doc', null, null, true), 'image');
    }

    // --- OOXML ---

    public function test_a_real_docx_and_xlsx_are_accepted(): void
    {
        $docx = $this->storage->store($this->ooxml('docx'), 'document');
        $xlsx = $this->storage->store($this->ooxml('xlsx'), 'document');

        $this->assertSame('application/vnd.openxmlformats-officedocument.wordprocessingml.document', $docx->mime);
        $this->assertStringEndsWith('.docx', $docx->path);
        $this->assertSame('application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', $xlsx->mime);
        $this->assertStringEndsWith('.xlsx', $xlsx->path);
    }

    public function test_a_plain_zip_named_docx_is_rejected(): void
    {
        $path = $this->tempPath();
        $zip = new ZipArchive;
        $zip->open($path, ZipArchive::CREATE);
        $zip->addFromString('a.txt', 'hi');
        $zip->close();

        $this->assertRejected(new UploadedFile($path, 'fake.docx', null, null, true), 'document');
    }

    public function test_text_named_docx_is_rejected(): void
    {
        $this->assertRejected($this->upload('plain text', 'fake.docx'), 'document');
    }

    public function test_a_docx_is_rejected_by_the_image_profile(): void
    {
        $this->assertRejected($this->ooxml('docx'), 'image');
    }

    // --- Safety ---

    public function test_an_executable_is_rejected_even_when_the_profile_allows_everything(): void
    {
        config(['files.profiles.open' => [
            'disk' => 'public',
            'mimes' => ['text/x-php' => 'php', 'text/plain' => 'txt', 'application/x-sh' => 'sh'],
            'max_size' => 1024,
        ]]);

        $this->assertRejected($this->upload('<?php echo 1;', 'shell.php'), 'open');
        $this->assertRejected($this->upload("#!/bin/sh\necho 1\n", 'run.sh'), 'open');
    }

    public function test_a_valid_image_named_php_is_rejected(): void
    {
        $this->assertRejected($this->png(10, 10, 'shell.php'), 'image');
    }

    public function test_svg_is_rejected(): void
    {
        $svg = '<?xml version="1.0"?><svg xmlns="http://www.w3.org/2000/svg" width="1" height="1"><script>alert(1)</script></svg>';

        $this->assertRejected($this->upload($svg, 'logo.svg'), 'image');

        config(['files.profiles.image.mimes' => ['image/svg+xml' => 'svg']]);
        $this->assertRejected($this->upload($svg, 'logo.svg'), 'image');
    }

    public function test_the_name_on_disk_is_generated_and_the_original_name_is_metadata(): void
    {
        $file = $this->storage->store($this->png(10, 10, '../../evil name.png'), 'image');

        $this->assertSame(1, preg_match('#^[0-9a-f]{32}\.png$#', $file->path));
        $this->assertSame($file->uuid.'.png', basename($file->path));
        $this->assertSame('evil name.png', $file->original_name);
        Storage::disk('public')->assertExists($file->path);
    }

    // --- Atomicity ---

    public function test_a_failed_insert_removes_the_written_file(): void
    {
        File::creating(fn () => throw new RuntimeException('insert failed'));

        try {
            $this->storage->store($this->png(), 'image');
            $this->fail('The insert failure must propagate.');
        } catch (RuntimeException $e) {
            $this->assertSame('insert failed', $e->getMessage());
            $this->assertSame([], Storage::disk('public')->allFiles());
        }
    }

    // --- url() ---

    public function test_url_is_built_from_the_disk_of_the_row(): void
    {
        $file = $this->storage->store($this->png(), 'image');

        $this->assertSame(Storage::disk('public')->url($file->path), $this->storage->url($file));
        $this->assertStringContainsString($file->path, $this->storage->url($file));
    }

    public function test_old_files_keep_their_disk_after_the_profile_config_changes(): void
    {
        $old = $this->storage->store($this->png(), 'image');
        config(['files.profiles.image.disk' => 'local']);

        $this->assertSame('public', $old->fresh()->disk);
        $this->assertSame(Storage::disk('public')->url($old->path), $this->storage->url($old->fresh()));
    }

    // --- delete() ---

    public function test_delete_removes_the_row_and_the_file(): void
    {
        $file = $this->storage->store($this->png(), 'image');

        $this->storage->delete($file);

        $this->assertDatabaseMissing('media', ['id' => $file->id]);
        Storage::disk('public')->assertMissing($file->path);
    }

    public function test_deleting_a_file_missing_on_disk_is_not_an_error(): void
    {
        $file = $this->storage->store($this->png(), 'image');
        Storage::disk('public')->delete($file->path);

        $this->storage->delete($file);

        $this->assertDatabaseMissing('media', ['id' => $file->id]);
    }

    // --- Image styles ---

    public function test_an_uploaded_image_gets_the_upload_presets(): void
    {
        $file = $this->storage->store($this->png(800, 400), 'image');

        $path = 'styles/thumb/'.$file->uuid.'.webp';
        Storage::disk('public')->assertExists($path);
        $info = getimagesizefromstring(Storage::disk('public')->get($path));
        $this->assertSame([200, 200, 'image/webp'], [$info[0], $info[1], $info['mime']]);
    }

    public function test_the_preset_follows_the_style_format(): void
    {
        config(['files.styles.format' => 'original']);

        $file = $this->storage->store($this->png(800, 400), 'image');

        Storage::disk('public')->assertExists('styles/thumb/'.$file->uuid.'.png');
        Storage::disk('public')->assertMissing('styles/thumb/'.$file->uuid.'.webp');
    }

    public function test_a_preset_without_the_upload_flag_is_not_created(): void
    {
        config(['files.styles.presets' => [
            'thumb' => ['width' => 200, 'height' => 200, 'upload' => true],
            'card' => ['width' => 400, 'height' => 300],
        ]]);

        $file = $this->storage->store($this->png(800, 400), 'image');

        Storage::disk('public')->assertExists('styles/thumb/'.$file->uuid.'.webp');
        Storage::disk('public')->assertMissing('styles/card/'.$file->uuid.'.webp');
    }

    public function test_a_document_gets_no_styles(): void
    {
        $this->storage->store($this->upload('%PDF-1.4 minimal', 'report.pdf'), 'document');

        $this->assertSame([], Storage::disk('public')->directories('styles'));
    }

    public function test_a_failed_style_does_not_reject_the_upload(): void
    {
        // An unknown format makes every style fail; the upload itself must still succeed.
        config(['files.styles.format' => 'avif']);

        $file = $this->storage->store($this->png(), 'image');

        $this->assertDatabaseHas('media', ['id' => $file->id]);
        Storage::disk('public')->assertExists($file->path);
        $this->assertSame([], Storage::disk('public')->directories('styles'));
    }

    public function test_deleting_a_file_removes_its_styles(): void
    {
        $file = $this->storage->store($this->png(800, 400), 'image');
        $other = $this->storage->store($this->png(800, 400), 'image');
        Storage::disk('public')->put('styles/300x250/'.$file->uuid.'.webp', 'x');
        Storage::disk('public')->put('styles/x300/'.$file->uuid.'.gif', 'x');

        $this->storage->delete($file);

        Storage::disk('public')->assertMissing($file->path);
        Storage::disk('public')->assertMissing('styles/thumb/'.$file->uuid.'.webp');
        Storage::disk('public')->assertMissing('styles/300x250/'.$file->uuid.'.webp');
        Storage::disk('public')->assertMissing('styles/x300/'.$file->uuid.'.gif');
        Storage::disk('public')->assertExists('styles/thumb/'.$other->uuid.'.webp');
    }

    public function test_deleting_a_file_without_styles_is_not_an_error(): void
    {
        $file = $this->storage->store($this->png(), 'image');
        Storage::disk('public')->deleteDirectory('styles');

        $this->storage->delete($file);

        $this->assertDatabaseMissing('media', ['id' => $file->id]);
    }

    public function test_a_refused_delete_keeps_the_styles(): void
    {
        $file = $this->storage->store($this->png(), 'image');
        (new FileRelations)->sync('content_node', 1, 'cover', 'default', [$file->uuid]);

        try {
            $this->storage->delete($file);
            $this->fail('A used file must not be deletable.');
        } catch (QueryException) {
            Storage::disk('public')->assertExists($file->path);
            Storage::disk('public')->assertExists('styles/thumb/'.$file->uuid.'.webp');
        }
    }

    // --- Protection of used files ---

    public function test_a_used_file_cannot_be_deleted_and_stays_on_disk(): void
    {
        $file = $this->storage->store($this->png(), 'image');
        (new FileRelations)->sync('content_node', 1, 'cover', 'default', [$file->uuid]);

        try {
            $this->storage->delete($file);
            $this->fail('A used file must not be deletable.');
        } catch (QueryException) {
            $this->assertDatabaseHas('media', ['id' => $file->id]);
            Storage::disk('public')->assertExists($file->path);
        }
    }

    public function test_replacing_a_value_keeps_the_old_row_and_file(): void
    {
        $old = $this->storage->store($this->png(), 'image');
        $new = $this->storage->store($this->png(), 'image');
        $relations = new FileRelations;
        $relations->sync('content_node', 1, 'cover', 'default', [$old->uuid]);

        $relations->sync('content_node', 1, 'cover', 'default', [$new->uuid]);

        $this->assertDatabaseHas('media', ['id' => $old->id]);
        Storage::disk('public')->assertExists($old->path);
    }

    // --- Helpers ---

    private function assertRejected(UploadedFile $upload, string $profile): void
    {
        try {
            $this->storage->store($upload, $profile);
            $this->fail('The upload must be rejected.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('file', $e->errors());
            $this->assertSame(0, File::query()->count());
            $this->assertSame([], Storage::disk('public')->allFiles());
        }
    }

    private function tempPath(): string
    {
        return $this->tempFiles[] = tempnam(sys_get_temp_dir(), 'fs');
    }

    private function upload(string $content, string $name): UploadedFile
    {
        $path = $this->tempPath();
        file_put_contents($path, $content);

        return new UploadedFile($path, $name, null, null, true);
    }

    /** A valid PNG followed by zero bytes, so the file is large while the picture stays tiny. */
    private function paddedPng(int $bytes): UploadedFile
    {
        $image = imagecreatetruecolor(4, 4);
        ob_start();
        imagepng($image);
        $png = ob_get_clean();

        return $this->upload($png.str_repeat("\0", $bytes - strlen($png)), 'big.png');
    }

    /** A copy of a file of tests/Fixtures in a temporary path that tearDown() removes. */
    private function copyFixture(string $name): string
    {
        $path = $this->tempPath();
        copy($this->packagePath("tests/Fixtures/{$name}"), $path);

        return $path;
    }

    private function png(int $width = 4, int $height = 4, string $name = 'pic.png'): UploadedFile
    {
        $image = imagecreatetruecolor($width, $height);
        ob_start();
        imagepng($image);

        return $this->upload(ob_get_clean(), $name);
    }

    /** A JPEG full of noise: large in bytes, so size limits are meaningful. */
    private function noisyJpeg(int $width, int $height): UploadedFile
    {
        $image = imagecreatetruecolor($width, $height);
        for ($i = 0; $i < 20000; $i++) {
            imagesetpixel($image, random_int(0, $width - 1), random_int(0, $height - 1), random_int(0, 0xFFFFFF));
        }
        ob_start();
        imagejpeg($image, null, 95);

        return $this->upload(ob_get_clean(), 'noisy.jpg');
    }

    private function jpeg(): UploadedFile
    {
        $image = imagecreatetruecolor(4, 4);
        ob_start();
        imagejpeg($image);

        return $this->upload(ob_get_clean(), 'pic.jpg');
    }

    /** A minimal OOXML package with `[Content_Types].xml` first, like Word and Excel write it. */
    private function ooxml(string $extension): UploadedFile
    {
        [$part, $type, $body] = $extension === 'docx'
            ? ['/word/document.xml', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml', '<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:body/></w:document>']
            : ['/xl/workbook.xml', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml', '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"/>'];

        $path = $this->tempPath();
        $zip = new ZipArchive;
        $zip->open($path, ZipArchive::OVERWRITE | ZipArchive::CREATE);
        $zip->addFromString('[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="'.$part.'" ContentType="'.$type.'"/></Types>');
        $zip->addFromString('_rels/.rels', '<?xml version="1.0" encoding="UTF-8"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="'.ltrim($part, '/').'"/></Relationships>');
        $zip->addFromString(ltrim($part, '/'), $body);
        $zip->close();

        return new UploadedFile($path, "file.{$extension}", null, null, true);
    }
}
