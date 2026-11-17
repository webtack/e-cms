<?php

namespace Webtack\Ecms\Content;

use RuntimeException;

/**
 * A theme template could not be copied for a cloned type: the target already exists or cannot be written.
 *
 * `$theme` and `$path` (relative to the theme directory) name the place, never the server path of the project.
 */
class ThemeTemplateCopyException extends RuntimeException
{
    public function __construct(public readonly string $theme, public readonly string $path, string $reason)
    {
        parent::__construct("Cannot copy the theme template [{$theme}/{$path}]: {$reason}.");
    }
}
