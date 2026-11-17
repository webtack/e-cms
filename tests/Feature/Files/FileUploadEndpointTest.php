<?php

namespace Webtack\Ecms\Tests\Feature\Files;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Webtack\Ecms\Models\ContentField;
use Webtack\Ecms\Models\ContentType;
use Webtack\Ecms\Models\File;
use Webtack\Ecms\Tests\Fixtures\User;
use Webtack\Ecms\Tests\TestCase;

class FileUploadEndpointTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
    }

    private function png(string $name = 'logo.png'): UploadedFile
    {
        return UploadedFile::fake()->image($name, 640, 480);
    }

    public function test_the_route_has_its_name(): void
    {
        $this->assertSame('/admin/files', route('admin.files.store', absolute: false));
    }

    public function test_an_admin_uploads_a_file_and_gets_its_metadata(): void
    {
        $user = User::factory()->create();
        $response = $this->actingAs($user)
            ->postJson('/admin/files', ['file' => $this->png(), 'profile' => 'image']);

        $file = File::query()->firstOrFail();

        $response->assertCreated()->assertExactJson([
            'uuid' => $file->uuid,
            'profile' => 'image',
            'name' => 'logo.png',
            'mime' => 'image/png',
            'size' => $file->size,
            'width' => 640,
            'height' => 480,
            'value' => $file->uuid.'.png',
            'url' => Storage::disk('public')->url($file->path),
            'preview' => Storage::disk('public')->url("styles/thumb/{$file->uuid}.webp"),
        ]);
        Storage::disk('public')->assertExists($file->path);
        $this->assertSame($user->id, $file->user_id);
    }

    public function test_a_guest_cannot_upload(): void
    {
        $this->postJson('/admin/files', ['file' => $this->png(), 'profile' => 'image'])->assertUnauthorized();

        $this->assertSame(0, File::query()->count());
        $this->assertSame([], Storage::disk('public')->allFiles());
    }

    public function test_an_unverified_user_cannot_upload(): void
    {
        $this->actingAs(User::factory()->unverified()->create())
            ->postJson('/admin/files', ['file' => $this->png(), 'profile' => 'image'])
            ->assertForbidden();

        $this->assertSame(0, File::query()->count());
    }

    public function test_the_file_is_required(): void
    {
        $this->actingAs(User::factory()->create())
            ->postJson('/admin/files', ['profile' => 'image'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('file');

        $this->assertSame(0, File::query()->count());
    }

    public function test_the_profile_is_required(): void
    {
        $this->actingAs(User::factory()->create())
            ->postJson('/admin/files', ['file' => $this->png()])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('profile');

        $this->assertSame(0, File::query()->count());
    }

    public function test_an_unknown_profile_is_rejected(): void
    {
        $this->actingAs(User::factory()->create())
            ->postJson('/admin/files', ['file' => $this->png(), 'profile' => 'nope'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('profile');

        $this->assertSame([], Storage::disk('public')->allFiles());
    }

    public function test_a_type_outside_the_profile_is_rejected(): void
    {
        $pdf = tempnam(sys_get_temp_dir(), 'up');
        file_put_contents($pdf, '%PDF-1.4 x');

        $this->actingAs(User::factory()->create())
            ->postJson('/admin/files', ['file' => new UploadedFile($pdf, 'doc.pdf', null, null, true), 'profile' => 'image'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('file');

        unlink($pdf);
        $this->assertSame(0, File::query()->count());
        $this->assertSame([], Storage::disk('public')->allFiles());
    }

    public function test_an_oversized_file_is_rejected(): void
    {
        config(['files.profiles.image.max_size' => 1]);

        $this->actingAs(User::factory()->create())
            ->postJson('/admin/files', ['file' => UploadedFile::fake()->image('big.png', 800, 800)->size(10), 'profile' => 'image'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('file');

        $this->assertSame(0, File::query()->count());
    }

    // --- Upload for a field ---

    private function field(string $type, ?array $settings = null, string $key = 'cover'): void
    {
        ContentType::create(['key' => 'article', 'name' => 'Article']);
        ContentField::create(['content_type' => 'article', 'key' => $key, 'name' => 'Cover', 'type' => $type, 'settings' => $settings]);
    }

    private function jpeg(): UploadedFile
    {
        return UploadedFile::fake()->image('photo.jpg', 20, 20);
    }

    private function postField(array $data): TestResponse
    {
        return $this->actingAs(User::factory()->create())->postJson('/admin/files', $data);
    }

    public function test_an_upload_for_a_field_uses_the_profile_of_its_type(): void
    {
        $this->field('image', ['mimes' => ['image/png'], 'max_size' => 1024]);

        $this->postField(['file' => $this->png(), 'content_type' => 'article', 'field_key' => 'cover'])
            ->assertCreated()
            ->assertJsonPath('profile', 'image');

        $this->assertSame('image', File::query()->firstOrFail()->profile);
    }

    public function test_an_upload_for_a_file_field_uses_the_document_profile(): void
    {
        $this->field('file');
        $pdf = UploadedFile::fake()->createWithContent('doc.pdf', '%PDF-1.4 x');

        $this->postField(['file' => $pdf, 'content_type' => 'article', 'field_key' => 'cover'])
            ->assertCreated()
            ->assertJsonPath('profile', 'document')
            ->assertJsonPath('preview', null);
    }

    public function test_a_type_outside_the_field_settings_is_rejected_and_nothing_is_stored(): void
    {
        $this->field('image', ['mimes' => ['image/png']]);

        $this->postField(['file' => $this->jpeg(), 'content_type' => 'article', 'field_key' => 'cover'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('file');

        $this->assertSame(0, File::query()->count());
        $this->assertSame([], Storage::disk('public')->allFiles());
    }

    public function test_a_size_above_the_field_limit_is_rejected_even_within_the_profile(): void
    {
        $this->field('image', ['max_size' => 1]);

        $this->postField(['file' => UploadedFile::fake()->image('big.png', 800, 800)->size(10), 'content_type' => 'article', 'field_key' => 'cover'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('file');

        $this->assertSame(0, File::query()->count());
    }

    public function test_a_profile_together_with_a_field_is_rejected(): void
    {
        $this->field('image');

        $this->postField(['file' => $this->png(), 'profile' => 'image', 'content_type' => 'article', 'field_key' => 'cover'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['profile', 'field_key']);

        $this->assertSame(0, File::query()->count());
    }

    public function test_an_unknown_field_or_a_field_of_another_type_is_rejected(): void
    {
        $this->field('string');

        $this->postField(['file' => $this->png(), 'content_type' => 'article', 'field_key' => 'nope'])
            ->assertUnprocessable()->assertJsonValidationErrors('field_key');
        $this->postField(['file' => $this->png(), 'content_type' => 'nope', 'field_key' => 'cover'])
            ->assertUnprocessable()->assertJsonValidationErrors('field_key');
        $this->postField(['file' => $this->png(), 'content_type' => 'article', 'field_key' => 'cover'])
            ->assertUnprocessable()->assertJsonValidationErrors('field_key');

        $this->assertSame(0, File::query()->count());
    }

    public function test_a_field_key_needs_its_content_type(): void
    {
        $this->field('image');

        $this->postField(['file' => $this->png(), 'field_key' => 'cover'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('content_type');
    }

    public function test_an_upload_for_a_richtext_field_uses_the_image_profile(): void
    {
        $this->field('richtext', key: 'body');

        $response = $this->postField(['file' => $this->png(), 'content_type' => 'article', 'field_key' => 'body'])
            ->assertCreated()
            ->assertJsonPath('profile', 'image');

        $this->assertStringContainsString('/storage/'.File::query()->firstOrFail()->uuid.'.png', $response->json('url'));
    }

    public function test_a_document_is_rejected_for_a_richtext_field(): void
    {
        $this->field('richtext', key: 'body');

        $this->postField(['file' => UploadedFile::fake()->create('a.pdf', 10, 'application/pdf'), 'content_type' => 'article', 'field_key' => 'body'])
            ->assertUnprocessable()->assertJsonValidationErrors('file');

        $this->assertSame(0, File::query()->count());
    }

    // --- Scaling of images ---

    public function test_an_upload_by_profile_is_scaled_to_the_profile_limits(): void
    {
        config(['files.profiles.image.max_width' => 320, 'files.profiles.image.max_height' => 320]);

        $response = $this->postField(['file' => $this->png(), 'profile' => 'image'])->assertCreated();

        $file = File::query()->firstOrFail();
        $stored = Storage::disk('public')->get($file->path);

        $response->assertJsonPath('width', 320)->assertJsonPath('height', 240)->assertJsonPath('size', strlen($stored));
        $this->assertSame([320, 240], array_slice(getimagesizefromstring($stored), 0, 2));
        $this->assertSame(hash('sha256', $stored), $file->hash);
    }

    public function test_an_upload_by_field_is_scaled_to_the_field_limits(): void
    {
        config(['files.profiles.image.max_width' => 1920, 'files.profiles.image.max_height' => 1920]);
        $this->field('image', ['max_width' => 200, 'max_height' => 200]);

        $response = $this->postField(['file' => $this->png(), 'content_type' => 'article', 'field_key' => 'cover'])
            ->assertCreated();

        $file = File::query()->firstOrFail();

        $response->assertJsonPath('width', 200)->assertJsonPath('height', 150)
            ->assertJsonPath('size', Storage::disk('public')->size($file->path));
    }

    public function test_a_field_cannot_widen_the_profile_limits(): void
    {
        config(['files.profiles.image.max_width' => 320, 'files.profiles.image.max_height' => 320]);
        $this->field('image', ['max_width' => 4000]);

        $this->postField(['file' => $this->png(), 'content_type' => 'article', 'field_key' => 'cover'])
            ->assertCreated()
            ->assertJsonPath('width', 320)
            ->assertJsonPath('height', 240);
    }

    public function test_an_image_with_too_many_pixels_is_rejected_by_the_endpoint(): void
    {
        config(['files.image.max_pixels' => 1000]);

        $this->postField(['file' => $this->png(), 'profile' => 'image'])
            ->assertUnprocessable()->assertJsonValidationErrors('file');

        $this->assertSame(0, File::query()->count());
    }
}
