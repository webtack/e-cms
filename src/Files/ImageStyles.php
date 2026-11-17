<?php

namespace Webtack\Ecms\Files;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Throwable;
use Webtack\Ecms\Files\Rules\FileValue;

/**
 * Image styles: derived sizes of a stored image.
 *
 * A style is either a **preset** from `files.styles.presets` (`thumb`) or an **arbitrary size** string
 * `300x250`, `300x` or `x300` (Latin `x` of any case; the Cyrillic `х` is rejected). The file of a style
 * lies on the disk of the `image` profile at `styles/{style}/{uuid}.{ext}`, where `{style}` is the preset
 * name or the normalized size and `{ext}` follows `files.styles.format` (see {@see self::extension()}).
 * The address is derived from the address of the original without any I/O, so templates can ask for it
 * cheaply; the file itself is created on upload (presets marked `upload`) or lazily by
 * Webtack\Ecms\Http\Controllers\ImageStyleController.
 *
 * An arbitrary size is only served with a signature (`itok`, see {@see self::signature()}) that
 * {@see self::url()} adds, so nobody can make the server render thousands of sizes.
 *
 * @phpstan-type Style array{name: string, width: int|null, height: int|null, preset: bool, upload: bool}
 *
 * @example
 *   ImageStyles::parse('300X250');   // ['name' => '300x250', 'width' => 300, 'height' => 250, 'preset' => false, ...]
 *   ImageStyles::parse('thumb');     // ['name' => 'thumb', 'width' => 200, 'height' => 200, 'preset' => true, ...]
 *   ImageStyles::parse('abc');       // null
 *   ImageStyles::url('/storage/0190a1b27c3e7f0a8b1c2d3e4f5a6b7c.jpg', 'thumb');
 *   // '/storage/styles/thumb/0190a1b27c3e7f0a8b1c2d3e4f5a6b7c.webp'
 *   ImageStyles::url('/storage/0190a1b27c3e7f0a8b1c2d3e4f5a6b7c.jpg', '300x');
 *   // '/storage/styles/300x/0190a1b27c3e7f0a8b1c2d3e4f5a6b7c.webp?itok=1a2b3c4d5e6f7a8b'
 */
class ImageStyles
{
    /** Disk directory that holds every style. */
    public const DIRECTORY = 'styles';

    /** Profile whose disk stores the originals and the styles. */
    public const PROFILE = 'image';

    /** Length of the `itok` signature in hex characters. */
    private const SIGNATURE_LENGTH = 16;

    /**
     * Parse and validate a style.
     *
     * @return Style|null Null when the style is neither a preset nor a valid size within the limit.
     *
     * @throws InvalidArgumentException When the configuration is wrong (a preset named like a size).
     */
    public static function parse(string $style): ?array
    {
        $presets = self::presets();

        if (isset($presets[$style])) {
            return $presets[$style];
        }

        // Digits are capped at five characters so a huge number cannot overflow before the range check.
        if (preg_match('/^(\d{1,5})?[xX](\d{1,5})?$/', $style, $match) !== 1) {
            return null;
        }

        $width = ($match[1] ?? '') === '' ? null : (int) $match[1];
        $height = ($match[2] ?? '') === '' ? null : (int) $match[2];

        if ($width === null && $height === null) {
            return null;
        }

        $max = (int) config('files.styles.max_dimension', 4096);

        foreach ([$width, $height] as $side) {
            if ($side !== null && ($side < 1 || $side > $max)) {
                return null;
            }
        }

        return [
            'name' => ($width ?? '').'x'.($height ?? ''),
            'width' => $width,
            'height' => $height,
            'preset' => false,
            'upload' => false,
        ];
    }

    /**
     * Presets marked for creation on upload.
     *
     * @return list<Style>
     */
    public static function uploadPresets(): array
    {
        return array_values(array_filter(self::presets(), fn (array $style) => $style['upload']));
    }

    /**
     * The configured presets, validated.
     *
     * @return array<string, Style>
     *
     * @throws InvalidArgumentException When a preset is named like an arbitrary size or has an invalid size.
     */
    private static function presets(): array
    {
        $presets = [];

        foreach ((array) config('files.styles.presets', []) as $name => $preset) {
            $name = (string) $name;

            if (preg_match('/^(\d+)?[xX](\d+)?$/', $name) === 1) {
                throw new InvalidArgumentException("Image style preset [{$name}] must not look like a size.");
            }

            if (preg_match('/^[A-Za-z0-9_-]+$/', $name) !== 1) {
                throw new InvalidArgumentException("Image style preset [{$name}] has an invalid name.");
            }

            $width = (int) ($preset['width'] ?? 0);
            $height = (int) ($preset['height'] ?? 0);

            if ($width < 1 || $height < 1) {
                throw new InvalidArgumentException("Image style preset [{$name}] needs a positive width and height.");
            }

            $presets[$name] = [
                'name' => $name,
                'width' => $width,
                'height' => $height,
                'preset' => true,
                'upload' => (bool) ($preset['upload'] ?? false),
            ];
        }

        return $presets;
    }

    /**
     * The MIME type a style file of an original gets: GIF stays GIF, otherwise WebP or the original
     * type, as `files.styles.format` says.
     *
     * @throws InvalidArgumentException When the configured format is unknown.
     */
    public static function outputMime(string $originalMime): string
    {
        return match (self::format()) {
            'webp' => $originalMime === 'image/gif' ? 'image/gif' : 'image/webp',
            'original' => $originalMime,
        };
    }

    /**
     * The extension of the style file of an original with this extension.
     *
     * @example
     *   ImageStyles::extension('jpg'); // 'webp' (format `webp`) or 'jpg' (format `original`)
     *   ImageStyles::extension('gif'); // 'gif' in both modes
     */
    public static function extension(string $originalExtension): string
    {
        $originalExtension = strtolower($originalExtension);

        return match (self::format()) {
            'webp' => $originalExtension === 'gif' ? 'gif' : 'webp',
            'original' => $originalExtension,
        };
    }

    /**
     * The `itok` signature of a style file: the first 16 hex characters of HMAC-SHA256 over
     * `{style}|{file}` keyed with the application key. Bound to the normalized style and the name of
     * the style file, not to a user or a time.
     */
    public static function signature(string $style, string $file): string
    {
        return substr(hash_hmac('sha256', $style.'|'.$file, (string) config('app.key')), 0, self::SIGNATURE_LENGTH);
    }

    /**
     * Disk path of a style file.
     *
     * @example ImageStyles::path('thumb', '0190a1b27c3e7f0a8b1c2d3e4f5a6b7c.webp'); // 'styles/thumb/0190a1b27c3e7f0a8b1c2d3e4f5a6b7c.webp'
     */
    public static function path(string $style, string $file): string
    {
        return self::DIRECTORY.'/'.$style.'/'.$file;
    }

    /**
     * The address of the style file of an image, without touching the disk or the database.
     *
     * Accepts a URL of a stored image (relative or absolute) or a `{uuid}.{ext}` value; the scheme, host
     * and directory of the original are kept. An address that does not look like a stored image (empty,
     * external, other extension) and an invalid style give the input back unchanged, so a template never breaks.
     *
     * @example
     *   ImageStyles::url(null, 'thumb');                                                  // null
     *   ImageStyles::url('https://cdn.example.com/photo.jpg', 'thumb');                   // unchanged
     *   ImageStyles::url('0190a1b27c3e7f0a8b1c2d3e4f5a6b7c.png', 'thumb');                // 'http://host/storage/styles/thumb/....webp'
     */
    public static function url(?string $url, string $style): ?string
    {
        if ($url === null || $url === '') {
            return null;
        }

        $parsed = self::parse($style);

        if ($parsed === null) {
            return $url;
        }

        if (! str_contains($url, '/') && preg_match(FileValue::PATTERN, $url) === 1) {
            $url = app(FileStorage::class)->urlForValue(self::PROFILE, $url);
        }

        $address = preg_split('/[?#]/', $url, 2)[0];
        $slash = strrpos($address, '/');
        $directory = $slash === false ? '' : substr($address, 0, $slash + 1);
        $name = $slash === false ? $address : substr($address, $slash + 1);

        if (preg_match(FileValue::PATTERN, $name, $match) !== 1 || ! in_array($match[2], self::originalExtensions(), true)) {
            return $url;
        }

        $file = $match[1].'.'.self::extension($match[2]);
        $result = $directory.self::DIRECTORY.'/'.$parsed['name'].'/'.$file;

        return $parsed['preset'] ? $result : $result.'?itok='.self::signature($parsed['name'], $file);
    }

    /**
     * Create the file of a style from the original on the disk.
     *
     * Animated images are copied unchanged (so the address stays predictable and later requests are
     * static). The file is written under a temporary name and renamed, so a parallel request never
     * serves a half-written file. An existing style file is left as it is.
     *
     * @param  string  $disk  Disk of the original and of the style.
     * @param  string  $originalPath  Path of the original on the disk, `{uuid}.{ext}`.
     * @param  string  $style  A preset name or an arbitrary size.
     * @return string|null Disk path of the style file, or null when no file can be made (unknown style,
     *                     missing original, not a processable image, decoding failure).
     */
    public static function create(string $disk, string $originalPath, string $style, ?ImageResizer $resizer = null): ?string
    {
        $parsed = self::parse($style);

        if ($parsed === null || preg_match(FileValue::PATTERN, $originalPath, $match) !== 1 || ! in_array($match[2], self::originalExtensions(), true)) {
            return null;
        }

        // A long-lived PHP-FPM worker keeps resolved paths in the realpath cache (`realpath_cache_ttl`), so
        // `is_dir()` can still say a removed style directory exists. Flysystem then skips `mkdir` and the write
        // fails. Reset the cache here: this path is already about to decode and encode an image.
        clearstatcache(true);

        $storage = Storage::disk($disk);
        $target = self::path($parsed['name'], $match[1].'.'.self::extension($match[2]));

        if ($storage->exists($target)) {
            return $target;
        }

        $stream = $storage->readStream($originalPath);

        if (! is_resource($stream)) {
            return null;
        }

        $temp = tempnam(sys_get_temp_dir(), 'style');

        try {
            $out = fopen($temp, 'wb');
            stream_copy_to_stream($stream, $out);
            fclose($out);
            fclose($stream);

            $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($temp) ?: '';
            $size = @getimagesize($temp);

            if (! in_array($mime, array_keys(config('files.profiles.'.self::PROFILE.'.mimes', [])), true) || $size === false
                || (int) config('files.image.max_pixels') < $size[0] * $size[1]) {
                return null;
            }

            $resizer ??= new ImageResizer;
            $result = $resizer->fit($temp, $mime, $parsed['width'], $parsed['height']);
            $contents = $result['contents'] ?? file_get_contents($temp);

            // Animated WebP keeps its content under the extension of the style.
            $partial = $target.'.'.Str::random(8).'.tmp';

            // The disk does not throw (`throw` is false), so a failed write only returns false: log it.
            if ($storage->put($partial, $contents) === false) {
                Log::warning('Could not create an image style: the file could not be written', ['disk' => $disk, 'path' => $originalPath, 'style' => $style]);

                return null;
            }

            if (! $storage->move($partial, $target)) {
                $storage->delete($partial);
                Log::warning('Could not create an image style: the file could not be moved into place', ['disk' => $disk, 'path' => $originalPath, 'style' => $style]);

                return null;
            }

            return $target;
        } catch (Throwable $e) {
            Log::warning('Could not create an image style: '.$e->getMessage(), ['disk' => $disk, 'path' => $originalPath, 'style' => $style]);

            return null;
        } finally {
            @unlink($temp);
        }
    }

    /**
     * Delete every style file of an original, in every style directory, whatever its extension.
     * Missing files are not an error.
     */
    public static function forget(string $disk, string $path): void
    {
        if (preg_match(FileValue::PATTERN, basename($path), $match) !== 1) {
            return;
        }

        $storage = Storage::disk($disk);

        foreach ($storage->directories(self::DIRECTORY) as $directory) {
            foreach ($storage->files($directory) as $file) {
                if (str_starts_with(basename($file), $match[1].'.')) {
                    $storage->delete($file);
                }
            }
        }
    }

    /**
     * Delete the created files of one style, or of all styles, on the disk of the image profile.
     * Originals are never touched.
     *
     * @throws InvalidArgumentException When the style is not valid.
     */
    public static function flush(?string $style = null): void
    {
        $storage = Storage::disk(config('files.profiles.'.self::PROFILE.'.disk'));

        if ($style === null) {
            $storage->deleteDirectory(self::DIRECTORY);

            return;
        }

        $parsed = self::parse($style);

        if ($parsed === null) {
            throw new InvalidArgumentException("Unknown image style [{$style}].");
        }

        $storage->deleteDirectory(self::DIRECTORY.'/'.$parsed['name']);
    }

    /**
     * Extensions of the originals of the image profile.
     *
     * @return list<string>
     */
    public static function originalExtensions(): array
    {
        return array_values(config('files.profiles.'.self::PROFILE.'.mimes', []));
    }

    /**
     * The configured style format.
     *
     * @throws InvalidArgumentException When it is neither `webp` nor `original`.
     */
    private static function format(): string
    {
        $format = config('files.styles.format', 'webp');

        if (! in_array($format, ['webp', 'original'], true)) {
            throw new InvalidArgumentException("Unknown image style format [{$format}].");
        }

        return $format;
    }
}
