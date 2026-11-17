<?php

use Webtack\Ecms\Files\FileStorage;
use Webtack\Ecms\Files\ImageStyles;
use Webtack\Ecms\Files\Rules\FileValue;
use Webtack\Ecms\Theme\ThemeManager;

if (! function_exists('image_style')) {
    /**
     * The address of an image in an image style, for templates (see Webtack\Ecms\Files\ImageStyles).
     *
     * Takes the URL of a stored image (relative or absolute) or a `{uuid}.{ext}` value and a style:
     * a preset name (`thumb`) or an arbitrary size (`300x250`, `300x`, `x300`, Latin `x`). No database
     * or disk access happens here; the file is created on its first request. An arbitrary size gets a
     * signature (`itok`). An address that is not a stored image (external, empty extension, document) and
     * an invalid style are returned unchanged; null stays null.
     *
     * @example
     *   image_style('/storage/0190a1b27c3e7f0a8b1c2d3e4f5a6b7c.jpg', 'thumb');
     *   // '/storage/styles/thumb/0190a1b27c3e7f0a8b1c2d3e4f5a6b7c.webp'
     *   image_style('/storage/0190a1b27c3e7f0a8b1c2d3e4f5a6b7c.jpg', '300x250');
     *   // '/storage/styles/300x250/0190a1b27c3e7f0a8b1c2d3e4f5a6b7c.webp?itok=1a2b3c4d5e6f7a8b'
     *   image_style('https://cdn.example.com/photo.jpg', 'thumb'); // unchanged
     *   image_style(null, 'thumb');                                // null
     */
    function image_style(?string $url, string $style): ?string
    {
        return ImageStyles::url($url, $style);
    }
}

if (! function_exists('file_url')) {
    /**
     * The public URL of a stored `{uuid}.{ext}` file value, for templates (see Webtack\Ecms\Files\FileStorage).
     *
     * The raw value of a file or image field (`$values['cover']`) is only the file name; this turns it into
     * the address on the disk of the upload profile. No database access happens here. A value that is not
     * `{uuid}.{ext}` (an address, a document name) is returned unchanged; null and '' give null.
     *
     * @param  string  $profile  Upload profile whose disk holds the file (a key of config/files.php `profiles`).
     *
     * @example
     *   file_url('0190a1b27c3e7f0a8b1c2d3e4f5a6b7c.jpg');              // '/storage/0190a1b27c3e7f0a8b1c2d3e4f5a6b7c.jpg'
     *   file_url('0190a1b27c3e7f0a8b1c2d3e4f5a6b7c.pdf', 'file');      // URL on the disk of the `file` profile
     *   file_url('https://cdn.example.com/photo.jpg');                 // unchanged
     *   file_url(null);                                                // null
     */
    function file_url(?string $value, string $profile = 'image'): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (preg_match(FileValue::PATTERN, $value) !== 1) {
            return $value;
        }

        return app(FileStorage::class)->urlForValue($profile, $value);
    }
}

if (! function_exists('theme_asset')) {
    /**
     * URL of a theme static file with a cache-busting `?v=<mtime>`, for PHP code and hooks.
     *
     * A thin wrapper over {@see ThemeManager::assetUrl()}: looks in the active theme, then in
     * `default`; a missing file gives the URL without `?v`.
     *
     * @example
     *   theme_asset('images/logo1.png'); // '/app/dcculture/images/logo1.png?v=1731000000'
     */
    function theme_asset(string $path): string
    {
        return app(ThemeManager::class)->assetUrl($path);
    }
}
