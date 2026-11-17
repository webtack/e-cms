<?php

namespace Webtack\Ecms\Tests\Unit\Files;

use Imagick;
use ImagickPixel;
use PHPUnit\Framework\Attributes\DataProvider;
use Webtack\Ecms\Files\ImageResizer;
use Webtack\Ecms\Tests\TestCase;

class ImageResizerTest extends TestCase
{
    /** @var list<string> */
    private array $temp = [];

    protected function tearDown(): void
    {
        foreach ($this->temp as $path) {
            @unlink($path);
        }

        parent::tearDown();
    }

    /**
     * Write a generated image to a temporary file and return its path.
     */
    private function image(string $type, int $width, int $height, bool $transparent = false): string
    {
        $image = imagecreatetruecolor($width, $height);
        imagesavealpha($image, true);
        imagealphablending($image, false);
        imagefill($image, 0, 0, imagecolorallocatealpha($image, 200, 30, 30, $transparent ? 127 : 0));
        // Left half stays red, right half blue: lets orientation tests tell the sides apart.
        imagefilledrectangle($image, intdiv($width, 2), 0, $width - 1, $height - 1, imagecolorallocatealpha($image, 30, 30, 200, $transparent ? 127 : 0));

        $path = tempnam(sys_get_temp_dir(), 'img').'.'.$type;
        match ($type) {
            'jpg' => imagejpeg($image, $path, 90),
            'png' => imagepng($image, $path),
            'webp' => imagewebp($image, $path, 90),
            'gif' => imagegif($image, $path),
        };

        return $this->temp[] = $path;
    }

    private function resizeFile(string $path, string $mime, ?int $w, ?int $h): ?array
    {
        return (new ImageResizer)->resize($path, $mime, $w, $h);
    }

    public function test_a_wide_image_is_scaled_by_its_width(): void
    {
        $result = $this->resizeFile($this->image('jpg', 2000, 1500), 'image/jpeg', 500, 500);

        $this->assertSame([500, 375], [$result['width'], $result['height']]);
        $this->assertSame([500, 375], array_slice(getimagesizefromstring($result['contents']), 0, 2));
    }

    public function test_a_tall_image_is_scaled_by_its_height(): void
    {
        $result = $this->resizeFile($this->image('jpg', 1500, 2000), 'image/jpeg', 500, 500);

        $this->assertSame([375, 500], [$result['width'], $result['height']]);
    }

    public function test_the_side_that_exceeds_its_limit_most_defines_the_scale(): void
    {
        $result = $this->resizeFile($this->image('png', 1600, 1200), 'image/png', 800, 400);

        $this->assertSame([533, 400], [$result['width'], $result['height']]);
    }

    public function test_a_single_limit_is_enough(): void
    {
        $result = $this->resizeFile($this->image('png', 1000, 1500), 'image/png', 500, null);

        $this->assertSame([500, 750], [$result['width'], $result['height']]);

        $result = $this->resizeFile($this->image('png', 1500, 1000), 'image/png', null, 500);

        $this->assertSame([750, 500], [$result['width'], $result['height']]);
    }

    public function test_an_image_within_the_limits_is_left_alone(): void
    {
        $this->assertNull($this->resizeFile($this->image('jpg', 640, 480), 'image/jpeg', 1000, 1000));
    }

    public function test_an_image_is_never_enlarged(): void
    {
        $this->assertNull($this->resizeFile($this->image('png', 100, 100), 'image/png', 5000, 5000));
    }

    public function test_without_limits_nothing_happens(): void
    {
        $this->assertNull($this->resizeFile($this->image('jpg', 1000, 750), 'image/jpeg', null, null));
    }

    public function test_an_unsupported_type_is_left_alone(): void
    {
        $this->assertNull($this->resizeFile($this->image('png', 400, 400), 'image/svg+xml', 100, 100));
    }

    public function test_png_keeps_its_format_and_transparency(): void
    {
        $result = $this->resizeFile($this->image('png', 2000, 2000, transparent: true), 'image/png', 500, 500);

        $this->assertSame(IMAGETYPE_PNG, getimagesizefromstring($result['contents'])[2]);

        $decoded = imagecreatefromstring($result['contents']);
        $alpha = (imagecolorat($decoded, 10, 10) >> 24) & 0x7F;

        $this->assertGreaterThan(100, $alpha, 'The transparent background must stay transparent.');
    }

    public function test_webp_keeps_its_format(): void
    {
        $result = $this->resizeFile($this->image('webp', 2000, 1000), 'image/webp', 500, 500);

        $this->assertSame(IMAGETYPE_WEBP, getimagesizefromstring($result['contents'])[2]);
        $this->assertSame([500, 250], [$result['width'], $result['height']]);
    }

    public function test_a_still_gif_is_scaled(): void
    {
        $result = $this->resizeFile($this->image('gif', 400, 400), 'image/gif', 100, 100);

        $this->assertSame(IMAGETYPE_GIF, getimagesizefromstring($result['contents'])[2]);
        $this->assertSame([100, 100], [$result['width'], $result['height']]);
    }

    public function test_an_animated_gif_is_left_alone(): void
    {
        $this->requiresImagick();
        $path = $this->animated('gif');

        $this->assertTrue((new ImageResizer)->isAnimated($path, 'image/gif'));
        $this->assertNull($this->resizeFile($path, 'image/gif', 100, 100));
    }

    public function test_an_animated_webp_is_left_alone(): void
    {
        $this->requiresImagick();

        if (! in_array('WEBP', Imagick::queryFormats('WEBP'), true)) {
            $this->markTestSkipped('Imagick has no WebP support.');
        }

        $path = $this->animated('webp');

        $this->assertTrue((new ImageResizer)->isAnimated($path, 'image/webp'));
        $this->assertNull($this->resizeFile($path, 'image/webp', 100, 100));
    }

    public function test_a_still_webp_and_gif_are_not_animated(): void
    {
        $resizer = new ImageResizer;

        $this->assertFalse($resizer->isAnimated($this->image('webp', 50, 50), 'image/webp'));
        $this->assertFalse($resizer->isAnimated($this->image('gif', 50, 50), 'image/gif'));
    }

    public function test_exif_orientation_is_applied(): void
    {
        // Stored 2000x1500 landscape pixels, marked "rotate 90° clockwise": a viewer shows 1500x2000.
        $path = $this->withOrientation($this->image('jpg', 2000, 1500), 6);

        $result = $this->resizeFile($path, 'image/jpeg', 500, 500);

        $this->assertSame([375, 500], [$result['width'], $result['height']]);
        $this->assertSame([375, 500], array_slice(getimagesizefromstring($result['contents']), 0, 2));
    }

    public function test_metadata_is_not_kept(): void
    {
        $path = $this->withOrientation($this->image('jpg', 2000, 2000), 6);

        $result = $this->resizeFile($path, 'image/jpeg', 500, 500);

        $this->assertStringNotContainsString('Exif', $result['contents']);
    }

    public function test_the_configured_quality_changes_the_jpeg_size(): void
    {
        $path = $this->noisy(1600, 1600);

        config(['files.image.quality.jpeg' => 95]);
        $high = strlen($this->resizeFile($path, 'image/jpeg', 800, 800)['contents']);

        config(['files.image.quality.jpeg' => 20]);
        $low = strlen($this->resizeFile($path, 'image/jpeg', 800, 800)['contents']);

        $this->assertLessThan($high, $low);
    }

    public function test_the_gd_driver_can_be_configured(): void
    {
        config(['files.image.driver' => 'gd']);

        $result = $this->resizeFile($this->image('jpg', 2000, 1000), 'image/jpeg', 500, 500);

        $this->assertSame([500, 250], [$result['width'], $result['height']]);
    }

    public function test_a_corrupt_image_throws(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'bad').'.jpg';
        $this->temp[] = $path;
        // A valid JPEG header followed by random bytes: getimagesize reads the size, decoding fails.
        file_put_contents($path, substr(file_get_contents($this->noisy(1000, 1000)), 0, 400).random_bytes(5000));

        $this->expectException(\Throwable::class);
        $this->resizeFile($path, 'image/jpeg', 500, 500);
    }

    /**
     * @dataProvider sizes
     */
    #[DataProvider('sizes')]
    public function test_target_size(int $w, int $h, ?int $maxW, ?int $maxH, ?array $expected): void
    {
        $this->assertSame($expected, ImageResizer::targetSize($w, $h, $maxW, $maxH));
    }

    /**
     * @return array<string, array{int, int, ?int, ?int, ?array}>
     */
    public static function sizes(): array
    {
        return [
            'width limits' => [4000, 3000, 1000, 1000, [1000, 750]],
            'height limits' => [3000, 4000, 1000, 1000, [750, 1000]],
            'different limits' => [1600, 1200, 800, 400, [533, 400]],
            'only width' => [2000, 3000, 500, null, [500, 750]],
            'only height' => [3000, 2000, null, 500, [750, 500]],
            'fits' => [640, 480, 1000, 1000, null],
            'exactly on the limit' => [1000, 1000, 1000, 1000, null],
            'no limits' => [4000, 3000, null, null, null],
            'thin strip keeps one pixel' => [10000, 10, 100, null, [100, 1]],
        ];
    }

    private function requiresImagick(): void
    {
        if (! extension_loaded('imagick')) {
            $this->markTestSkipped('The imagick extension is required to build animations.');
        }
    }

    /**
     * Build a two-frame animation in the given format with Imagick.
     */
    private function animated(string $format): string
    {
        $animation = new Imagick;
        $animation->setFormat($format);

        foreach (['red', 'blue'] as $colour) {
            $frame = new Imagick;
            $frame->newImage(400, 400, new ImagickPixel($colour));
            $frame->setImageFormat($format);
            $frame->setImageDelay(10);
            $animation->addImage($frame);
        }

        $path = tempnam(sys_get_temp_dir(), 'anim').'.'.$format;
        $animation->writeImages($path, true);

        return $this->temp[] = $path;
    }

    /**
     * Insert an EXIF APP1 segment with the given orientation right after the JPEG start marker.
     */
    private function withOrientation(string $path, int $orientation): string
    {
        $tiff = "MM\x00\x2A\x00\x00\x00\x08"        // big-endian header, first IFD at offset 8
            ."\x00\x01"                              // one entry
            ."\x01\x12\x00\x03\x00\x00\x00\x01"      // tag 0x0112 (Orientation), SHORT, count 1
            .pack('n', $orientation)."\x00\x00"      // value
            ."\x00\x00\x00\x00";                     // no next IFD
        $segment = "Exif\x00\x00".$tiff;
        $app1 = "\xFF\xE1".pack('n', strlen($segment) + 2).$segment;

        $bytes = file_get_contents($path);
        file_put_contents($path, substr($bytes, 0, 2).$app1.substr($bytes, 2));

        return $path;
    }

    // --- fit(): image styles ---

    /**
     * Decode the result of fit() and return `[width, height, mime]`.
     */
    private function decoded(array $result): array
    {
        $info = getimagesizefromstring($result['contents']);

        return [$info[0], $info[1], $info['mime']];
    }

    /**
     * RGBA of a pixel of an encoded result.
     *
     * @return array{r: int, g: int, b: int, a: int} `a` is 0 (opaque) .. 127 (transparent)
     */
    private function pixel(array $result, int $x, int $y): array
    {
        $image = imagecreatefromstring($result['contents']);
        $color = imagecolorat($image, $x, $y);

        return ['r' => ($color >> 16) & 255, 'g' => ($color >> 8) & 255, 'b' => $color & 255, 'a' => ($color >> 24) & 127];
    }

    public function test_a_wide_image_gets_margins_above_and_below(): void
    {
        $result = (new ImageResizer)->fit($this->image('jpg', 1200, 600), 'image/jpeg', 300, 250);

        $this->assertSame([300, 250], [$result['width'], $result['height']]);
        $this->assertSame([300, 250], array_slice($this->decoded($result), 0, 2));
        // Margins show the blurred original (red left, blue right), not one flat colour.
        $topLeft = $this->pixel($result, 5, 2);
        $topRight = $this->pixel($result, 295, 2);
        $this->assertGreaterThan($topLeft['b'], $topLeft['r']);
        $this->assertGreaterThan($topRight['r'], $topRight['b']);
        // The picture itself is in the middle band (y 50..199).
        $this->assertGreaterThan(150, $this->pixel($result, 20, 125)['r']);
        $this->assertGreaterThan(150, $this->pixel($result, 280, 125)['b']);
    }

    public function test_a_tall_image_gets_margins_on_the_sides(): void
    {
        $result = (new ImageResizer)->fit($this->image('jpg', 600, 1200), 'image/jpeg', 300, 250);

        $this->assertSame([300, 250], array_slice($this->decoded($result), 0, 2));
        // The picture is 125x250 centred; the side margins are filled, not empty.
        $this->assertSame(0, $this->pixel($result, 5, 125)['a']);
    }

    public function test_the_same_proportions_leave_no_margins(): void
    {
        $result = (new ImageResizer)->fit($this->image('jpg', 1200, 1000), 'image/jpeg', 300, 250);

        $this->assertSame([300, 250], [$result['width'], $result['height']]);
        $corner = $this->pixel($result, 2, 2);
        $this->assertGreaterThan(150, $corner['r']);
        $this->assertLessThan(80, $corner['b']);
    }

    public function test_a_smaller_original_is_enlarged(): void
    {
        $result = (new ImageResizer)->fit($this->image('jpg', 200, 100), 'image/jpeg', 300, 250);

        $this->assertSame([300, 250], [$result['width'], $result['height']]);
        $this->assertGreaterThan(150, $this->pixel($result, 20, 125)['r']);
    }

    public function test_a_transparent_image_keeps_transparent_margins(): void
    {
        $result = (new ImageResizer)->fit($this->image('png', 600, 1200, transparent: true), 'image/png', 300, 250);

        $this->assertSame([300, 250], array_slice($this->decoded($result), 0, 2));
        $this->assertSame(127, $this->pixel($result, 5, 125)['a']);
    }

    public function test_an_opaque_png_gets_a_background_whatever_the_format(): void
    {
        // An opaque PNG gets a background, so the margins are opaque.
        $opaque = (new ImageResizer)->fit($this->image('png', 600, 1200), 'image/png', 300, 250);
        $this->assertSame(0, $this->pixel($opaque, 5, 125)['a']);
    }

    public function test_a_width_only_style_scales_without_margins(): void
    {
        $result = (new ImageResizer)->fit($this->image('jpg', 1200, 600), 'image/jpeg', 300, null);

        $this->assertSame([300, 150], [$result['width'], $result['height']]);
    }

    public function test_a_height_only_style_scales_without_margins(): void
    {
        $result = (new ImageResizer)->fit($this->image('jpg', 600, 1200), 'image/jpeg', null, 300);

        $this->assertSame([150, 300], [$result['width'], $result['height']]);
    }

    public function test_a_one_sided_style_enlarges_a_small_original(): void
    {
        $result = (new ImageResizer)->fit($this->image('jpg', 200, 100), 'image/jpeg', 300, null);

        $this->assertSame([300, 150], [$result['width'], $result['height']]);
    }

    public function test_an_animated_gif_is_not_fitted(): void
    {
        $this->assertNull((new ImageResizer)->fit($this->animated('gif'), 'image/gif', 100, 100));
    }

    public function test_the_style_format_webp_encodes_jpeg_and_png_as_webp(): void
    {
        config(['files.styles.format' => 'webp']);

        $this->assertSame('image/webp', $this->decoded((new ImageResizer)->fit($this->image('jpg', 400, 400), 'image/jpeg', 100, 100))[2]);
        $this->assertSame('image/webp', $this->decoded((new ImageResizer)->fit($this->image('png', 400, 400), 'image/png', 100, 100))[2]);
    }

    public function test_the_style_format_original_keeps_the_type(): void
    {
        config(['files.styles.format' => 'original']);

        $this->assertSame('image/jpeg', $this->decoded((new ImageResizer)->fit($this->image('jpg', 400, 400), 'image/jpeg', 100, 100))[2]);
        $this->assertSame('image/png', $this->decoded((new ImageResizer)->fit($this->image('png', 400, 400), 'image/png', 100, 100))[2]);
    }

    public function test_a_still_gif_stays_a_gif_in_both_formats(): void
    {
        foreach (['webp', 'original'] as $format) {
            config(['files.styles.format' => $format]);

            $this->assertSame('image/gif', $this->decoded((new ImageResizer)->fit($this->image('gif', 400, 400), 'image/gif', 100, 100))[2]);
        }
    }

    public function test_fit_size_rounds_and_enlarges(): void
    {
        $this->assertSame([300, 150], ImageResizer::fitSize(1200, 600, 300, 250));
        $this->assertSame([125, 250], ImageResizer::fitSize(600, 1200, 300, 250));
        $this->assertSame([300, 150], ImageResizer::fitSize(200, 100, 300, 250));
        $this->assertSame([600, 300], ImageResizer::fitSize(1200, 600, null, 300));
    }

    /**
     * A JPEG full of noise, so that the encoder quality visibly changes its size.
     */
    private function noisy(int $width, int $height): string
    {
        $image = imagecreatetruecolor($width, $height);

        for ($i = 0; $i < 20000; $i++) {
            imagesetpixel($image, random_int(0, $width - 1), random_int(0, $height - 1), random_int(0, 0xFFFFFF));
        }

        $path = tempnam(sys_get_temp_dir(), 'noise').'.jpg';
        imagejpeg($image, $path, 95);

        return $this->temp[] = $path;
    }
}
