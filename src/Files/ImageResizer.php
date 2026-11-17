<?php

namespace Webtack\Ecms\Files;

use Intervention\Image\Alignment;
use Intervention\Image\Drivers\Gd\Driver as GdDriver;
use Intervention\Image\Drivers\Imagick\Driver as ImagickDriver;
use Intervention\Image\Exceptions\RuntimeException;
use Intervention\Image\Format;
use Intervention\Image\ImageManager;
use Intervention\Image\Interfaces\ImageInterface;
use InvalidArgumentException;

/**
 * Scales a raster image down so that it fits into the given width and height limits.
 *
 * API choices (verified against intervention/image 4.4 in vendor/):
 *  - orientation: the manager is created with `autoOrientation: true`, so the decoder applies the
 *    EXIF orientation while reading and the stored pixels look the way a viewer shows them;
 *  - metadata: `strip: true` drops EXIF (including GPS) from the re-encoded file;
 *  - scaling: the target size is computed here (see {@see self::targetSize()}) and applied with
 *    `resize($width, $height)`, so the side that defines the scale equals its limit exactly;
 *  - animation: detected from the file header (see {@see self::isAnimated()}) before any pixel is
 *    decoded, so a big animated file is never decompressed just to be skipped;
 *  - encoding: `encodeUsingFormat()` keeps the format; JPEG and WebP use the configured quality,
 *    PNG is lossless.
 *
 * The caller (Webtack\Ecms\Files\FileStorage) has already checked the type and the pixel count.
 *
 * @phpstan-type Resized array{contents: string, width: int, height: int}
 *
 * @example
 *   $resizer = new ImageResizer;
 *   $resizer->resize('/tmp/photo.jpg', 'image/jpeg', 1000, 1000);
 *   // ['contents' => '...jpeg bytes...', 'width' => 1000, 'height' => 750] for a 4000x3000 photo
 *   $resizer->resize('/tmp/small.png', 'image/png', 1000, 1000);  // null: already fits, keep the original
 *   $resizer->resize('/tmp/photo.jpg', 'image/jpeg', null, null); // null: nothing to limit
 */
class ImageResizer
{
    /** Types this class re-encodes, as `mime => Intervention format`. */
    private const FORMATS = [
        'image/jpeg' => Format::JPEG,
        'image/png' => Format::PNG,
        'image/webp' => Format::WEBP,
        'image/gif' => Format::GIF,
    ];

    /**
     * Scale the image down to the limits, or report that the original must be kept.
     *
     * @param  string  $path  Path of the image file.
     * @param  string  $mime  Content-detected MIME type of the file.
     * @param  int|null  $maxWidth  Maximum width in pixels; null leaves the width unrestricted.
     * @param  int|null  $maxHeight  Maximum height in pixels; null leaves the height unrestricted.
     * @return Resized|null Null when the image fits, is animated, has no limits or has an unsupported type.
     *
     * @throws RuntimeException When the image cannot be decoded or encoded.
     */
    public function resize(string $path, string $mime, ?int $maxWidth, ?int $maxHeight): ?array
    {
        $format = self::FORMATS[$mime] ?? null;

        if ($format === null || ($maxWidth === null && $maxHeight === null)) {
            return null;
        }

        $size = @getimagesize($path);

        if ($size === false) {
            return null;
        }

        [$width, $height] = $size;

        // getimagesize() reports the stored pixels; a viewer (and the decoder) shows them rotated.
        if ($mime === 'image/jpeg' && $this->swapsSides($path)) {
            [$width, $height] = [$height, $width];
        }

        if (self::targetSize($width, $height, $maxWidth, $maxHeight) === null || $this->isAnimated($path, $mime)) {
            return null;
        }

        $image = $this->manager()->decodePath($path);

        // The decoded image is already oriented, so its real size decides the target.
        $target = self::targetSize($image->width(), $image->height(), $maxWidth, $maxHeight);

        if ($target === null) {
            return null;
        }

        $image->resize($target[0], $target[1]);

        $encoded = $image->encodeUsingFormat($format, ...$this->encoderOptions($format));

        return [
            'contents' => $encoded->toString(),
            'width' => $image->width(),
            'height' => $image->height(),
        ];
    }

    /**
     * Render an image of an image style (see Webtack\Ecms\Files\ImageStyles).
     *
     * With both sides the result is exactly `$width` x `$height`: the original is scaled proportionally
     * to fit inside (a smaller original is enlarged, nothing is cropped) and centred; the free margins
     * show a blurred copy of the same original scaled to cover the whole canvas. An image with
     * transparent pixels (checked on the pixels, not the format) gets transparent margins and no
     * background. With one side the image is only scaled by that side (and enlarged if needed).
     * The encoding follows `files.styles.format`, see {@see ImageStyles::outputMime()}.
     *
     * The blurred background is built from a copy reduced four times, blurred with a quarter of
     * `files.styles.blur` and stretched back: the same look, a much cheaper blur.
     *
     * @param  string  $path  Path of the image file.
     * @param  string  $mime  Content-detected MIME type of the file.
     * @param  int|null  $width  Width of the style; null leaves the width free.
     * @param  int|null  $height  Height of the style; null leaves the height free.
     * @return Resized|null Null for an animated or unsupported image: the caller uses the original as is.
     *
     * @throws RuntimeException When the image cannot be decoded or encoded.
     *
     * @example
     *   $resizer->fit('/tmp/wide.jpg', 'image/jpeg', 300, 250); // 1200x600 source: 300x250, picture 300x150 on a blurred background
     *   $resizer->fit('/tmp/photo.jpg', 'image/jpeg', 300, null); // 1200x600 source: 300x150
     */
    public function fit(string $path, string $mime, ?int $width, ?int $height): ?array
    {
        if (! isset(self::FORMATS[$mime]) || ($width === null && $height === null) || $this->isAnimated($path, $mime)) {
            return null;
        }

        $format = self::FORMATS[ImageStyles::outputMime($mime)];
        $image = $this->manager()->decodePath($path);
        $sourceWidth = $image->width();
        $sourceHeight = $image->height();
        [$fitWidth, $fitHeight] = self::fitSize($sourceWidth, $sourceHeight, $width, $height);
        $transparent = $this->hasTransparency($image, $mime);

        // The background is made before the original is scaled in place.
        $canvas = null;
        if ($width !== null && $height !== null && ($fitWidth !== $width || $fitHeight !== $height)) {
            $canvas = $transparent ? $this->manager()->createImage($width, $height) : $this->blurredBackground($image, $width, $height);
        }

        $image->resize($fitWidth, $fitHeight);

        if ($canvas !== null) {
            $canvas->insert($image, alignment: Alignment::CENTER);
            $image = $canvas;
        }

        $encoded = $image->encodeUsingFormat($format, ...$this->encoderOptions($format));

        return [
            'contents' => $encoded->toString(),
            'width' => $image->width(),
            'height' => $image->height(),
        ];
    }

    /**
     * The size of the picture of a style inside its canvas; unlike {@see self::targetSize()} it enlarges.
     *
     * With two sides `scale = min(width / sourceWidth, height / sourceHeight)`, the side that defines the
     * scale equals its limit exactly. With one side only that side is set and the other follows the proportions.
     *
     * @return array{0: int, 1: int}
     *
     * @example
     *   ImageResizer::fitSize(1200, 600, 300, 250); // [300, 150]
     *   ImageResizer::fitSize(600, 1200, 300, 250); // [125, 250]
     *   ImageResizer::fitSize(200, 100, 300, 250);  // [300, 150]
     *   ImageResizer::fitSize(1200, 600, null, 300); // [600, 300]
     */
    public static function fitSize(int $sourceWidth, int $sourceHeight, ?int $width, ?int $height): array
    {
        if ($sourceWidth < 1 || $sourceHeight < 1) {
            throw new InvalidArgumentException('Image dimensions must be positive.');
        }

        if ($width === null && $height === null) {
            throw new InvalidArgumentException('At least one side is required.');
        }

        if ($height === null) {
            return [$width, max(1, (int) round($sourceHeight * $width / $sourceWidth))];
        }

        if ($width === null) {
            return [max(1, (int) round($sourceWidth * $height / $sourceHeight)), $height];
        }

        if ($width / $sourceWidth <= $height / $sourceHeight) {
            return [$width, max(1, (int) round($sourceHeight * $width / $sourceWidth))];
        }

        return [max(1, (int) round($sourceWidth * $height / $sourceHeight)), $height];
    }

    /**
     * A canvas of the given size filled with a blurred copy of the image scaled to cover it.
     */
    private function blurredBackground(ImageInterface $image, int $width, int $height): ImageInterface
    {
        $background = clone $image;
        $background->cover(max(1, intdiv($width, 4)), max(1, intdiv($height, 4)));
        $background->blur(max(1, intdiv((int) config('files.styles.blur', 30), 4)));
        $background->resize($width, $height);

        return $background;
    }

    /**
     * Whether the image has transparent or semi-transparent pixels, judged on a reduced copy.
     *
     * A reduced copy is scanned (at most 64 x 64 pixels) so a big image is not walked pixel by pixel;
     * averaging keeps even a thin transparent area below full opacity. JPEG cannot be transparent.
     */
    private function hasTransparency(ImageInterface $image, string $mime): bool
    {
        if ($mime === 'image/jpeg') {
            return false;
        }

        $small = clone $image;
        $small->scaleDown(64, 64);

        for ($x = 0; $x < $small->width(); $x++) {
            for ($y = 0; $y < $small->height(); $y++) {
                if ($small->colorAt($x, $y)->alpha()->value() < 250) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * The size an image has to be scaled to, or null when it already fits.
     *
     * `scale = min(maxWidth / width, maxHeight / height, 1)`; a missing limit does not restrict its
     * side. The side that defines the scale equals its limit exactly, the other one is rounded to the
     * nearest integer (at least 1). Images are never enlarged.
     *
     * @return array{0: int, 1: int}|null Width and height, or null when no scaling is needed.
     *
     * @example
     *   ImageResizer::targetSize(4000, 3000, 1000, 1000); // [1000, 750]
     *   ImageResizer::targetSize(1600, 1200, 800, 400);   // [533, 400]
     *   ImageResizer::targetSize(2000, 3000, 500, null);  // [500, 750]
     *   ImageResizer::targetSize(640, 480, 1000, 1000);   // null
     */
    public static function targetSize(int $width, int $height, ?int $maxWidth, ?int $maxHeight): ?array
    {
        if ($width < 1 || $height < 1) {
            throw new InvalidArgumentException('Image dimensions must be positive.');
        }

        $widthScale = $maxWidth === null ? 1.0 : $maxWidth / $width;
        $heightScale = $maxHeight === null ? 1.0 : $maxHeight / $height;
        $scale = min($widthScale, $heightScale, 1.0);

        if ($scale >= 1.0) {
            return null;
        }

        // The limiting side is set to its limit directly, so rounding cannot leave it off by one.
        if ($widthScale <= $heightScale) {
            return [$maxWidth, max(1, (int) round($height * $scale))];
        }

        return [max(1, (int) round($width * $scale)), $maxHeight];
    }

    /**
     * Whether the file holds an animation (GIF with several frames, animated WebP).
     *
     * Read from the file header, no pixels are decoded.
     */
    public function isAnimated(string $path, string $mime): bool
    {
        if ($mime === 'image/webp') {
            $head = (string) file_get_contents($path, false, null, 0, 32);

            // VP8X extended header: bit 1 (0x02) of the flags byte marks an animation.
            return str_starts_with(substr($head, 8, 4), 'WEBP')
                && substr($head, 12, 4) === 'VP8X'
                && (ord($head[20] ?? "\0") & 0x02) !== 0;
        }

        if ($mime === 'image/gif') {
            // Every frame starts with a graphic control extension followed by an image descriptor
            // or another extension; more than one such sequence means an animation.
            $frames = preg_match_all(
                '#\x00\x21\xF9\x04.{4}\x00[\x2C\x21]#s',
                (string) file_get_contents($path),
                $matches,
            );

            return $frames > 1;
        }

        return false;
    }

    /**
     * Whether the EXIF orientation of a JPEG turns it by 90° (values 5-8), exchanging width and height.
     */
    private function swapsSides(string $path): bool
    {
        if (! function_exists('exif_read_data')) {
            return false;
        }

        $exif = @exif_read_data($path, 'IFD0');

        return is_array($exif) && in_array($exif['Orientation'] ?? 1, [5, 6, 7, 8], true);
    }

    /**
     * Encoder arguments of a format: the configured quality for lossy formats, metadata stripped where the encoder supports it.
     *
     * @return array<string, int|bool>
     */
    private function encoderOptions(Format $format): array
    {
        return match ($format) {
            Format::JPEG => ['quality' => (int) config('files.image.quality.jpeg', 85), 'strip' => true],
            Format::WEBP => ['quality' => (int) config('files.image.quality.webp', 85), 'strip' => true],
            Format::PNG => ['strip' => true],
            default => [],
        };
    }

    /**
     * Image manager with the driver from `files.image.driver`.
     */
    private function manager(): ImageManager
    {
        $driver = config('files.image.driver', 'imagick') === 'gd' ? GdDriver::class : ImagickDriver::class;

        return ImageManager::usingDriver($driver, autoOrientation: true, decodeAnimation: false, strip: true);
    }
}
