<?php

namespace Webtack\Ecms\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Webtack\Ecms\Access\AccessResolver;

/**
 * Validates the lookup of a YouTube video: only the presence of a link; whether it points to a video
 * is decided (and reported with a reason) by `Webtack\Ecms\Youtube\YoutubeLookup`.
 *
 * @example
 *   // POST /admin/youtube/lookup  {"url": "https://youtu.be/dQw4w9WgXcQ"}
 *   $request->validated('url'); // 'https://youtu.be/dQw4w9WgXcQ'
 */
class YoutubeLookupRequest extends FormRequest
{
    public function authorize(): bool
    {
        return app(AccessResolver::class)->canEditAnyContent($this->user());
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return ['url' => ['required', 'string', 'max:2048']];
    }
}
