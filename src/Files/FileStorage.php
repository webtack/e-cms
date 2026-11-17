<?php

namespace Webtack\Ecms\Files;

use Illuminate\Database\QueryException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Number;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;
use Webtack\Ecms\Contracts\EcmsUser;
use Webtack\Ecms\Models\File;

/**
 * The single entry point for storing, addressing and deleting files.
 *
 * `store()` checks the upload against its profile (config/files.php) before anything is written,
 * scales a too large image down to the pixel limits of the rules (see ImageResizer), puts the file on
 * the profile's disk under a generated name, records a `media` row describing the stored file and creates the
 * image style presets marked `upload` (see ImageStyles). Consumers keep
 * only the file `uuid`; they never delete a file themselves (the media library does, and only
 * when nothing uses the file).
 *
 * @example
 *   $storage = app(FileStorage::class);
 *   $file = $storage->store($request->file('file'), 'image'); // Webtack\Ecms\Models\File
 *   $storage->url($file);                                     // '/storage/0190a1b27c3e7f0a8b1c2d3e4f5a6b7c.png'
 *   $storage->delete($file);                                  // row first (FK may refuse), then the disk
 */
class FileStorage
{
    /** Detected types rejected for every profile, even if a profile lists them. */
    public const FORBIDDEN_MIMES = [
        'image/svg+xml',
        'text/x-php',
        'application/x-httpd-php',
        'application/x-php',
        'text/x-shellscript',
        'application/x-sh',
        'application/x-shellscript',
        'application/x-dosexec',
        'application/x-msdownload',
        'application/x-executable',
        'application/x-mach-binary',
        'application/x-sharedlib',
    ];

    /** Original-name extensions rejected for every profile. */
    public const FORBIDDEN_EXTENSIONS = [
        'php', 'php3', 'php4', 'php5', 'php7', 'php8', 'phtml', 'phar', 'phps',
        'sh', 'bash', 'zsh', 'exe', 'bat', 'cmd', 'com', 'msi', 'dll', 'cgi', 'pl', 'py',
        'svg', 'svgz',
    ];

    public function __construct(
        private readonly FileProfiles $profiles,
        private readonly ImageResizer $resizer = new ImageResizer,
    ) {}

    /**
     * Validate an upload against a profile, write it to the root of the profile's disk and record it.
     *
     * `$rules` may only narrow the profile (see `UploadRules::forField()`); without it the whole
     * profile applies. Rules of another profile are a programming error.
     *
     * The size limit is checked against the uploaded file, before any scaling; `size`, `hash`, `width`
     * and `height` of the row describe the file that was actually stored.
     *
     * @throws ValidationException Under the `file` key, when the upload is rejected; nothing is written.
     * @throws \InvalidArgumentException When the profile is unknown (callers validate the key first) or `$rules` belong to another profile.
     */
    public function store(UploadedFile $upload, string $profile, ?EcmsUser $user = null, ?UploadRules $rules = null): File
    {
        $settings = $this->profiles->get($profile);
        $rules ??= UploadRules::forProfile($profile);

        if ($rules->profile !== $profile) {
            throw new \InvalidArgumentException("Upload rules of profile [{$rules->profile}] cannot be used for [{$profile}].");
        }

        if (! $upload->isValid() || ! is_file((string) $upload->getRealPath())) {
            $this->reject(__('The file could not be uploaded.'));
        }

        $path = $upload->getRealPath();
        $mime = $this->detectMime($path);
        $this->assertAllowed($upload, $mime, $rules);

        $dimensions = null;
        $resized = null;
        if (str_starts_with($mime, 'image/')) {
            $dimensions = @getimagesize($path);
            if ($dimensions === false) {
                $this->reject(__('The file is not a valid image.'));
            }

            // Before decoding: a small compressed file can hold a huge picture.
            if ((int) config('files.image.max_pixels') < $dimensions[0] * $dimensions[1]) {
                $this->reject(__('The image has too many pixels. The maximum is :max megapixels.', [
                    'max' => Number::format((int) config('files.image.max_pixels') / 1_000_000, maxPrecision: 1),
                ]));
            }

            $resized = $this->scaleDown($path, $mime, $rules);
        }

        $uuid = File::newUuid();
        $diskPath = $uuid.'.'.$settings['mimes'][$mime];

        $written = $resized !== null
            ? Storage::disk($settings['disk'])->put($diskPath, $resized['contents'])
            : Storage::disk($settings['disk'])->putFileAs('', $upload, $diskPath);
        if ($written === false) {
            throw new RuntimeException("Could not write the file to disk [{$settings['disk']}].");
        }

        try {
            $file = File::query()->create([
                'uuid' => $uuid,
                'user_id' => $user?->id,
                'profile' => $profile,
                'disk' => $settings['disk'],
                'path' => $diskPath,
                'original_name' => Str::limit(basename(str_replace('\\', '/', $upload->getClientOriginalName())), 255, ''),
                'mime' => $mime,
                'size' => $resized !== null ? strlen($resized['contents']) : filesize($path),
                'hash' => $resized !== null ? hash('sha256', $resized['contents']) : hash_file('sha256', $path),
                'width' => $resized['width'] ?? $dimensions[0] ?? null,
                'height' => $resized['height'] ?? $dimensions[1] ?? null,
            ]);
        } catch (Throwable $e) {
            // The database transaction cannot roll the disk back, so compensate by hand.
            Storage::disk($settings['disk'])->delete($diskPath);

            throw $e;
        }

        if ($dimensions !== null && $profile === ImageStyles::PROFILE) {
            $this->createUploadStyles($settings['disk'], $diskPath);
        }

        return $file;
    }

    /**
     * The URL of a file, built from the disk recorded on its row.
     */
    public function url(File $file): string
    {
        return Storage::disk($file->disk)->url($file->path);
    }

    /**
     * The URL of a stored `{uuid}.{ext}` value of a field, without a query: the file lies in the root of
     * its profile's disk, so the value is the path. Only the disk comes from the profile's configuration.
     *
     * @example app(FileStorage::class)->urlForValue('image', '0190a1b27c3e7f0a8b1c2d3e4f5a6b7c.png')
     */
    public function urlForValue(string $profile, string $value): string
    {
        return Storage::disk($this->profiles->get($profile)['disk'])->url($value);
    }

    /**
     * The `thumb` style address of a stored `{uuid}.{ext}` value for the admin previews, or null for a file
     * that is not an image (`$mime` comes from its `media` row; an empty string when the row is gone).
     *
     * @example
     *   app(FileStorage::class)->previewForValue('image', '0190a1b27c3e7f0a8b1c2d3e4f5a6b7c.png', 'image/png');
     *   // '/storage/styles/thumb/0190a1b27c3e7f0a8b1c2d3e4f5a6b7c.webp'
     */
    public function previewForValue(string $profile, string $value, string $mime): ?string
    {
        return str_starts_with($mime, 'image/') ? image_style($this->urlForValue($profile, $value), 'thumb') : null;
    }

    /**
     * Delete the row, then the file on disk and its image styles. A foreign key that still references the
     * row makes the delete throw before the disk is touched; a file already missing on disk is not an error.
     *
     * @throws QueryException When a consumer still references the file.
     */
    public function delete(File $file): void
    {
        $disk = $file->disk;
        $path = $file->path;

        $file->delete();

        Storage::disk($disk)->delete($path);
        ImageStyles::forget($disk, $path);
    }

    /**
     * Create the image style presets marked `upload` for a stored image.
     *
     * A failure is logged and swallowed: the file is already stored and the preset is created lazily on
     * its first request (see ImageStyleController), so the upload must not be rejected.
     */
    private function createUploadStyles(string $disk, string $path): void
    {
        try {
            foreach (ImageStyles::uploadPresets() as $style) {
                ImageStyles::create($disk, $path, $style['name'], $this->resizer);
            }
        } catch (Throwable $e) {
            Log::warning('Could not create the image styles of an uploaded image: '.$e->getMessage(), ['path' => $path, 'exception' => $e]);
        }
    }

    /**
     * Scale an image down to the pixel limits of the rules.
     *
     * @return array{contents: string, width: int, height: int}|null Null when the original is stored as is.
     *
     * @throws ValidationException When the image cannot be processed.
     */
    private function scaleDown(string $path, string $mime, UploadRules $rules): ?array
    {
        try {
            return $this->resizer->resize($path, $mime, $rules->maxWidth, $rules->maxHeight);
        } catch (Throwable $e) {
            Log::warning('Could not process an uploaded image: '.$e->getMessage(), ['exception' => $e]);

            $this->reject(__('The image could not be processed.'));
        }
    }

    /**
     * The type of the content (not the extension or the client-sent header).
     */
    private function detectMime(string $path): string
    {
        return (new \finfo(FILEINFO_MIME_TYPE))->file($path) ?: 'application/octet-stream';
    }

    /**
     * @throws ValidationException
     */
    private function assertAllowed(UploadedFile $upload, string $mime, UploadRules $rules): void
    {
        $extension = strtolower(pathinfo($upload->getClientOriginalName(), PATHINFO_EXTENSION));

        if (in_array($mime, self::FORBIDDEN_MIMES, true) || in_array($extension, self::FORBIDDEN_EXTENSIONS, true)) {
            $this->reject(__('This file type is not allowed.'));
        }

        if (! $rules->allows($mime)) {
            $this->reject(__('This file type is not allowed.'));
        }

        if (filesize($upload->getRealPath()) > $rules->maxSize * 1024) {
            $this->reject(__('The file is too large. The maximum size is :max.', ['max' => Number::fileSize($rules->maxSize * 1024)]));
        }
    }

    /**
     * @throws ValidationException
     */
    private function reject(string $message): never
    {
        throw ValidationException::withMessages(['file' => $message]);
    }
}
