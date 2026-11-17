<?php

namespace Webtack\Ecms\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Webtack\Ecms\Files\ImageStyles;
use Webtack\Ecms\Files\Rules\FileValue;

/**
 * Lazy creator of image style files: `GET {url of the image disk}/styles/{style}/{file}`.
 *
 * A web server serves a style file that exists as a static file and never reaches PHP. For a file that
 * does not exist yet the standard fallback to the front controller (`public/.htaccess`, nginx `try_files`)
 * lands here: the style is created from the original, written to the disk and sent. Every refusal is a 404
 * and writes nothing: the file name is not `{uuid}.{ext}`, the style is not a preset or a size within the
 * limit (in its normalized spelling), an arbitrary size has no valid `itok`, the original is missing or is
 * not an image, or the extension does not match the style format of that original.
 *
 * @example
 *   // GET /storage/styles/thumb/0190a1b27c3e7f0a8b1c2d3e4f5a6b7c.webp
 *   // GET /storage/styles/300x250/0190a1b27c3e7f0a8b1c2d3e4f5a6b7c.webp?itok=1a2b3c4d5e6f7a8b
 */
class ImageStyleController extends Controller
{
    public function __invoke(Request $request, string $style, string $file): StreamedResponse
    {
        if (preg_match(FileValue::PATTERN, $file, $match) !== 1) {
            abort(404);
        }

        $parsed = ImageStyles::parse($style);

        // Only the normalized spelling has a file, so `300X250` and `0300x250` cannot duplicate `300x250`.
        if ($parsed === null || $parsed['name'] !== $style) {
            abort(404);
        }

        if (! $parsed['preset']) {
            $token = $request->query('itok');

            if (! is_string($token) || ! hash_equals(ImageStyles::signature($style, $file), $token)) {
                abort(404);
            }
        }

        $disk = (string) config('files.profiles.'.ImageStyles::PROFILE.'.disk');
        $storage = Storage::disk($disk);
        $original = null;

        foreach (ImageStyles::originalExtensions() as $extension) {
            if ($storage->exists($match[1].'.'.$extension)) {
                $original = $match[1].'.'.$extension;

                break;
            }
        }

        if ($original === null || ImageStyles::extension(pathinfo($original, PATHINFO_EXTENSION)) !== $match[2]) {
            abort(404);
        }

        $path = ImageStyles::create($disk, $original, $style);

        if ($path === null) {
            abort(404);
        }

        return $storage->response($path);
    }
}
