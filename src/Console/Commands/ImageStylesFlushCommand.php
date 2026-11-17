<?php

namespace Webtack\Ecms\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use InvalidArgumentException;
use Webtack\Ecms\Files\ImageStyles;

/**
 * Deletes created image style files: all of them, or the one style given by name (a preset or a
 * normalized size). Originals are never touched and nothing outside the `styles/` directories of the
 * `image` profile disk is removed. Deleted styles are created again on the next request, with the
 * current settings, so run it after a preset has been resized.
 *
 * @example
 *   php artisan image-styles:flush          // every style
 *   php artisan image-styles:flush thumb    // only styles/thumb
 *   php artisan image-styles:flush 300x250  // only styles/300x250
 */
#[Signature('image-styles:flush {style? : A preset name or a size such as 300x250; every style when omitted}')]
#[Description('Delete created image style files so they are created again on demand')]
class ImageStylesFlushCommand extends Command
{
    public function handle(): int
    {
        $style = $this->argument('style');

        try {
            ImageStyles::flush(is_string($style) ? $style : null);
        } catch (InvalidArgumentException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info($style === null ? 'All image styles were deleted.' : "Image style [{$style}] was deleted.");

        return self::SUCCESS;
    }
}
