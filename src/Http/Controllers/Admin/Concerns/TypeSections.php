<?php

namespace Webtack\Ecms\Http\Controllers\Admin\Concerns;

/**
 * The single map from a content-type kind to the URL segment of its admin section.
 *
 * Shared by {@see ResolvesTypeSection} and by code outside controllers that links into a section.
 *
 * @example
 *   TypeSections::segment('block'); // 'block-types'
 */
final class TypeSections
{
    /** URL segment of the section per kind; a new kind must be added here and in `routes/web.php`. */
    public const SEGMENTS = ['content' => 'content-types', 'page' => 'page-types', 'block' => 'block-types'];

    /** The URL segment of the section of the kind, e.g. `block-types` for kind `block`. */
    public static function segment(string $kind): string
    {
        return self::SEGMENTS[$kind];
    }
}
