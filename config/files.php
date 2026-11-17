<?php

use Webtack\Ecms\Files\Owners\ContentNodeOwner;
use Webtack\Ecms\Files\Owners\ContentTypeOwner;
use Webtack\Ecms\Files\Owners\MenuItemOwner;
use Webtack\Ecms\Files\Owners\SiteSettingOwner;
use Webtack\Ecms\Files\Owners\TermOwner;
use Webtack\Ecms\Files\Owners\UserOwner;

return [

    /*
    |--------------------------------------------------------------------------
    | Upload profiles
    |--------------------------------------------------------------------------
    |
    | Rules of an upload are set here by the developer, never by the user. A profile has:
    |  - disk:      a key of config/filesystems.php `disks`; files lie in its root as `{uuid}.{ext}`;
    |  - mimes:     allowed types, detected from the content, as `mime => extension of the stored file`;
    |  - max_size:  kilobytes, checked against the uploaded file before any processing;
    |  - max_width / max_height (image profiles, optional): pixels; a larger image is scaled down
    |    proportionally on upload, a side without a limit (null) is not restricted.
    | Limits come from `.env` so they can be changed without touching code; `max_width` and `max_height`
    | set to `null` there lift that limit. Executable files and SVG are rejected for every profile (see Webtack\Ecms\Files\FileStorage).
    |
    */

    'profiles' => [
        'image' => [
            'disk' => 'public',
            'mimes' => [
                'image/jpeg' => 'jpg',
                'image/png' => 'png',
                'image/webp' => 'webp',
                'image/gif' => 'gif',
            ],
            'max_size' => (int) env('FILES_IMAGE_MAX_SIZE', 30 * 1024),
            'max_width' => is_null($width = env('FILES_IMAGE_MAX_WIDTH', 1920)) ? null : (int) $width,
            'max_height' => is_null($height = env('FILES_IMAGE_MAX_HEIGHT', 1920)) ? null : (int) $height,
        ],

        // A user picture: no GIF, small size; a larger image is downscaled proportionally (no crop).
        'avatar' => [
            'disk' => 'public',
            'mimes' => [
                'image/jpeg' => 'jpg',
                'image/png' => 'png',
                'image/webp' => 'webp',
            ],
            'max_size' => (int) env('FILES_AVATAR_MAX_SIZE', 2048),
            'max_width' => is_null($avatarWidth = env('FILES_AVATAR_MAX_WIDTH', 512)) ? null : (int) $avatarWidth,
            'max_height' => is_null($avatarHeight = env('FILES_AVATAR_MAX_HEIGHT', 512)) ? null : (int) $avatarHeight,
        ],

        'document' => [
            'disk' => 'public',
            'mimes' => [
                'application/pdf' => 'pdf',
                'application/msword' => 'doc',
                'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => 'xlsx',
            ],
            'max_size' => (int) env('FILES_DOCUMENT_MAX_SIZE', 30 * 1024),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Image processing
    |--------------------------------------------------------------------------
    |
    | Used by Webtack\Ecms\Files\ImageResizer when an image exceeds the profile limits:
    |  - driver:     `imagick` or `gd` (Intervention Image driver);
    |  - quality:    1-100, applied when a JPEG or WebP is re-encoded (PNG is lossless);
    |  - max_pixels: width * height above which an image is rejected before it is decoded,
    |    so a small compressed file cannot exhaust the memory.
    |
    */

    'image' => [
        'driver' => env('FILES_IMAGE_DRIVER', 'imagick'),
        'quality' => [
            'jpeg' => (int) env('FILES_IMAGE_JPEG_QUALITY', 85),
            'webp' => (int) env('FILES_IMAGE_WEBP_QUALITY', 85),
        ],
        'max_pixels' => (int) env('FILES_IMAGE_MAX_PIXELS', 100_000_000),
    ],

    /*
    |--------------------------------------------------------------------------
    | Image styles
    |--------------------------------------------------------------------------
    |
    | Derived sizes of an image (see Webtack\Ecms\Files\ImageStyles). A style is a preset from `presets` or an
    | arbitrary size string such as `300x250`, `300x` or `x300` (Latin `x` only):
    |  - presets:       `name => [width, height, upload]`; a name must not look like a size. A preset with
    |    `upload => true` is created right after the image is stored, the others lazily on the first request;
    |  - max_dimension: the largest side, in pixels, an arbitrary size may have;
    |  - blur:          strength of the blur of the background that fills the margins of a two-sided style;
    |  - format:        `webp` (JPEG, PNG and WebP originals become WebP, GIF stays GIF) or `original`.
    |
    */

    'styles' => [
        'presets' => [
            'thumb' => [
                'width' => (int) env('FILES_STYLE_THUMB_WIDTH', 200),
                'height' => (int) env('FILES_STYLE_THUMB_HEIGHT', 200),
                'upload' => true,
            ],
        ],
        'max_dimension' => (int) env('FILES_STYLE_MAX_DIMENSION', 4096),
        'blur' => (int) env('FILES_STYLE_BLUR', 30),
        'format' => env('FILES_STYLE_FORMAT', 'webp'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Relation owners
    |--------------------------------------------------------------------------
    |
    | `media_relations.owner_type` => class implementing Webtack\Ecms\Files\Owners\MediaOwner. The media
    | library asks it for a label and an edit link of each owner; an owner type that is not listed
    | is shown as `type #id` without a link.
    |
    */

    'owners' => [
        'content_node' => ContentNodeOwner::class,
        'content_type' => ContentTypeOwner::class,
        'taxonomy_term' => TermOwner::class,
        'menu_item' => MenuItemOwner::class,
        'site_setting' => SiteSettingOwner::class,
        'user' => UserOwner::class,
    ],

];
