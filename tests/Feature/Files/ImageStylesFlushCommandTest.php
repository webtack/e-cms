<?php

namespace Webtack\Ecms\Tests\Feature\Files;

use Illuminate\Support\Facades\Storage;
use Webtack\Ecms\Tests\TestCase;

class ImageStylesFlushCommandTest extends TestCase
{
    private const UUID = '0190a1b27c3e7f0a8b1c2d3e4f5a6b7c';

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        $disk = Storage::disk('public');
        $disk->put(self::UUID.'.jpg', 'original');
        $disk->put('styles/thumb/'.self::UUID.'.webp', 'a');
        $disk->put('styles/300x250/'.self::UUID.'.webp', 'b');
    }

    public function test_every_style_is_deleted_and_originals_stay(): void
    {
        $this->artisan('image-styles:flush')->assertSuccessful();

        $this->assertSame([], Storage::disk('public')->allFiles('styles'));
        Storage::disk('public')->assertExists(self::UUID.'.jpg');
    }

    public function test_one_style_is_deleted(): void
    {
        $this->artisan('image-styles:flush', ['style' => 'thumb'])->assertSuccessful();

        Storage::disk('public')->assertMissing('styles/thumb/'.self::UUID.'.webp');
        Storage::disk('public')->assertExists('styles/300x250/'.self::UUID.'.webp');
        Storage::disk('public')->assertExists(self::UUID.'.jpg');
    }

    public function test_a_size_is_flushed_in_its_normalized_form(): void
    {
        $this->artisan('image-styles:flush', ['style' => '300X250'])->assertSuccessful();

        Storage::disk('public')->assertMissing('styles/300x250/'.self::UUID.'.webp');
        Storage::disk('public')->assertExists('styles/thumb/'.self::UUID.'.webp');
    }

    public function test_an_invalid_style_is_rejected_and_nothing_is_deleted(): void
    {
        foreach (['../', '..', 'abc', '300х250'] as $style) {
            $this->artisan('image-styles:flush', ['style' => $style])->assertFailed();
        }

        Storage::disk('public')->assertExists('styles/thumb/'.self::UUID.'.webp');
        Storage::disk('public')->assertExists(self::UUID.'.jpg');
    }

    public function test_a_style_is_created_again_after_the_flush(): void
    {
        $image = imagecreatetruecolor(800, 400);
        ob_start();
        imagejpeg($image);
        Storage::disk('public')->put(self::UUID.'.jpg', (string) ob_get_clean());

        $this->artisan('image-styles:flush')->assertSuccessful();
        $this->get('/storage/styles/thumb/'.self::UUID.'.webp')->assertOk();

        $info = getimagesizefromstring(Storage::disk('public')->get('styles/thumb/'.self::UUID.'.webp'));
        $this->assertSame([200, 200], [$info[0], $info[1]]);
    }

    public function test_the_new_preset_size_is_used_after_the_flush(): void
    {
        $image = imagecreatetruecolor(800, 400);
        ob_start();
        imagejpeg($image);
        Storage::disk('public')->put(self::UUID.'.jpg', (string) ob_get_clean());
        config(['files.styles.presets.thumb.width' => 100, 'files.styles.presets.thumb.height' => 100]);

        $this->artisan('image-styles:flush', ['style' => 'thumb'])->assertSuccessful();
        $this->get('/storage/styles/thumb/'.self::UUID.'.webp')->assertOk();

        $info = getimagesizefromstring(Storage::disk('public')->get('styles/thumb/'.self::UUID.'.webp'));
        $this->assertSame([100, 100], [$info[0], $info[1]]);
    }
}
