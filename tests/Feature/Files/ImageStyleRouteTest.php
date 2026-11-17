<?php

namespace Webtack\Ecms\Tests\Feature\Files;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Mockery;
use Webtack\Ecms\Files\ImageStyles;
use Webtack\Ecms\Tests\TestCase;

class ImageStyleRouteTest extends TestCase
{
    private const UUID = '0190a1b27c3e7f0a8b1c2d3e4f5a6b7c';

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
    }

    /**
     * Put a generated image on the fake disk as the original `{uuid}.{ext}`.
     */
    private function original(string $type = 'jpg', int $width = 1200, int $height = 600, string $uuid = self::UUID): string
    {
        $image = imagecreatetruecolor($width, $height);
        imagefill($image, 0, 0, imagecolorallocate($image, 200, 30, 30));

        ob_start();
        match ($type) {
            'jpg' => imagejpeg($image),
            'png' => imagepng($image),
            'gif' => imagegif($image),
        };
        $name = $uuid.'.'.$type;
        Storage::disk('public')->put($name, (string) ob_get_clean());

        return $name;
    }

    private function signed(string $style, string $file): string
    {
        return "/storage/styles/{$style}/{$file}?itok=".ImageStyles::signature($style, $file);
    }

    public function test_the_first_request_creates_the_file_and_serves_it(): void
    {
        $this->original();

        $response = $this->get('/storage/styles/thumb/'.self::UUID.'.webp');

        $response->assertOk();
        $this->assertSame('image/webp', $response->headers->get('Content-Type'));
        Storage::disk('public')->assertExists('styles/thumb/'.self::UUID.'.webp');
        $info = getimagesizefromstring(Storage::disk('public')->get('styles/thumb/'.self::UUID.'.webp'));
        $this->assertSame([200, 200], [$info[0], $info[1]]);
    }

    public function test_the_response_sets_no_cookies(): void
    {
        $this->original();

        $response = $this->get('/storage/styles/thumb/'.self::UUID.'.webp');

        $this->assertEmpty($response->headers->getCookies());
    }

    public function test_a_repeated_request_does_not_recreate_the_file(): void
    {
        $this->original();
        $url = '/storage/styles/thumb/'.self::UUID.'.webp';
        $this->get($url)->assertOk();
        $path = 'styles/thumb/'.self::UUID.'.webp';
        Storage::disk('public')->put($path, 'marker');

        $this->get($url)->assertOk();

        $this->assertSame('marker', Storage::disk('public')->get($path));
    }

    public function test_a_signed_arbitrary_size_is_created(): void
    {
        $this->original();
        $file = self::UUID.'.webp';

        $this->get($this->signed('300x250', $file))->assertOk();

        $info = getimagesizefromstring(Storage::disk('public')->get('styles/300x250/'.$file));
        $this->assertSame([300, 250], [$info[0], $info[1]]);
    }

    public function test_the_url_from_the_helper_works(): void
    {
        $this->original('png');
        $url = ImageStyles::url('/storage/'.self::UUID.'.png', '300x');

        $this->get($url)->assertOk();
        $this->assertSame([300, 150], array_slice(getimagesizefromstring(Storage::disk('public')->get('styles/300x/'.self::UUID.'.webp')), 0, 2));
    }

    public function test_a_removed_style_directory_is_created_again(): void
    {
        $this->original();
        $url = '/storage/styles/thumb/'.self::UUID.'.webp';
        $this->get($url)->assertOk();
        Storage::disk('public')->deleteDirectory('styles/thumb');
        Storage::disk('public')->assertMissing('styles/thumb/'.self::UUID.'.webp');

        $this->get($url)->assertOk();

        Storage::disk('public')->assertExists('styles/thumb/'.self::UUID.'.webp');
    }

    public function test_a_removed_style_file_is_created_again(): void
    {
        $this->original();
        $url = '/storage/styles/thumb/'.self::UUID.'.webp';
        $this->get($url)->assertOk();
        Storage::disk('public')->delete('styles/thumb/'.self::UUID.'.webp');

        $this->get($url)->assertOk();

        Storage::disk('public')->assertExists('styles/thumb/'.self::UUID.'.webp');
    }

    public function test_a_failed_write_is_404_and_logged(): void
    {
        $this->original();
        $disk = Mockery::mock(Storage::disk('public'))->makePartial();
        $disk->shouldReceive('put')->andReturn(false);
        Storage::set('public', $disk);
        Log::spy();

        $this->get('/storage/styles/thumb/'.self::UUID.'.webp')->assertNotFound();

        Log::shouldHaveReceived('warning')->once()->withArgs(
            fn (string $message, array $context) => $context === ['disk' => 'public', 'path' => self::UUID.'.jpg', 'style' => 'thumb']
        );
        $this->assertSame([], array_filter(Storage::disk('public')->allFiles('styles'), fn ($f) => str_ends_with($f, '.tmp')));
    }

    public function test_a_failed_move_is_404_logged_and_leaves_no_temporary_file(): void
    {
        $this->original();
        $disk = Mockery::mock(Storage::disk('public'))->makePartial();
        $disk->shouldReceive('move')->andReturn(false);
        Storage::set('public', $disk);
        Log::spy();

        $this->get('/storage/styles/thumb/'.self::UUID.'.webp')->assertNotFound();

        Log::shouldHaveReceived('warning')->once();
        $this->assertSame([], Storage::disk('public')->allFiles('styles'));
    }

    public function test_a_missing_original_is_404(): void
    {
        $this->get('/storage/styles/thumb/'.self::UUID.'.webp')->assertNotFound();

        $this->assertSame([], Storage::disk('public')->allFiles('styles'));
    }

    public function test_a_file_name_that_is_not_a_stored_file_is_404(): void
    {
        $this->original();

        foreach (['photo.webp', self::UUID.'.webp.php', strtoupper(self::UUID).'.webp'] as $file) {
            $this->get('/storage/styles/thumb/'.$file)->assertNotFound();
        }
        $this->assertSame([], Storage::disk('public')->allFiles('styles'));
    }

    public function test_a_path_traversal_in_the_file_name_is_refused_and_writes_nothing(): void
    {
        $this->original();

        // The encoded slashes never reach this route (another storage route answers), so only "refused" is checked.
        $this->assertGreaterThanOrEqual(400, $this->get('/storage/styles/thumb/..%2F..%2F'.self::UUID.'.webp')->getStatusCode());
        $this->assertSame([], Storage::disk('public')->allFiles('styles'));
    }

    public function test_a_document_is_404(): void
    {
        Storage::disk('public')->put(self::UUID.'.pdf', '%PDF-1.4');

        $this->get('/storage/styles/thumb/'.self::UUID.'.pdf')->assertNotFound();
        $this->get('/storage/styles/thumb/'.self::UUID.'.webp')->assertNotFound();
    }

    public function test_a_non_image_with_an_image_extension_is_404(): void
    {
        Storage::disk('public')->put(self::UUID.'.jpg', 'not an image');

        $this->get('/storage/styles/thumb/'.self::UUID.'.webp')->assertNotFound();
        $this->assertSame([], Storage::disk('public')->allFiles('styles'));
    }

    public function test_an_arbitrary_size_without_a_signature_is_404(): void
    {
        $this->original();

        $this->get('/storage/styles/300x250/'.self::UUID.'.webp')->assertNotFound();
        $this->assertSame([], Storage::disk('public')->allFiles('styles'));
    }

    public function test_a_signature_of_another_size_is_404(): void
    {
        $this->original();
        $file = self::UUID.'.webp';

        $this->get('/storage/styles/300x250/'.$file.'?itok='.ImageStyles::signature('400x400', $file))->assertNotFound();
        $this->get('/storage/styles/300x250/'.$file.'?itok[]=x')->assertNotFound();
        $this->assertSame([], Storage::disk('public')->allFiles('styles'));
    }

    public function test_a_size_above_the_limit_is_404_even_when_signed(): void
    {
        $this->original();
        $file = self::UUID.'.webp';

        $this->get($this->signed('99999x99999', $file))->assertNotFound();
        $this->assertSame([], Storage::disk('public')->allFiles('styles'));
    }

    public function test_a_not_normalized_size_is_404(): void
    {
        $this->original();
        $file = self::UUID.'.webp';

        $this->get($this->signed('300X250', $file))->assertNotFound();
    }

    public function test_a_preset_needs_no_signature(): void
    {
        $this->original();

        $this->get('/storage/styles/thumb/'.self::UUID.'.webp')->assertOk();
    }

    public function test_an_unknown_style_is_404(): void
    {
        $this->original();

        $this->get('/storage/styles/abc/'.self::UUID.'.webp')->assertNotFound();
    }

    public function test_an_animated_gif_is_copied_unchanged(): void
    {
        $gif = base64_decode('R0lGODlhAQABAIAAAP///wAAACH5BAAKAAAALAAAAAABAAEAAAICRAEAIfkEAAoAAAAsAAAAAAEAAQAAAgJEAQA7');
        Storage::disk('public')->put(self::UUID.'.gif', $gif);

        $this->get('/storage/styles/thumb/'.self::UUID.'.gif')->assertOk();

        $this->assertSame($gif, Storage::disk('public')->get('styles/thumb/'.self::UUID.'.gif'));
    }

    public function test_a_jpeg_original_is_served_with_the_webp_extension(): void
    {
        $this->original('jpg');

        $this->get('/storage/styles/thumb/'.self::UUID.'.webp')->assertOk();
        $this->get('/storage/styles/thumb/'.self::UUID.'.jpg')->assertNotFound();
    }

    public function test_a_mismatching_extension_is_404_in_the_original_format_mode(): void
    {
        config(['files.styles.format' => 'original']);
        $this->original('jpg');

        $this->get('/storage/styles/thumb/'.self::UUID.'.webp')->assertNotFound();
        $this->get('/storage/styles/thumb/'.self::UUID.'.jpg')->assertOk();
    }

    public function test_a_gif_extension_for_a_jpeg_original_is_404(): void
    {
        $this->original('jpg');

        $this->get('/storage/styles/thumb/'.self::UUID.'.gif')->assertNotFound();
    }
}
