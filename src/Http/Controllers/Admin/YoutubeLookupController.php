<?php

namespace Webtack\Ecms\Http\Controllers\Admin;

use Illuminate\Http\JsonResponse;
use Webtack\Ecms\Files\FileStorage;
use Webtack\Ecms\Http\Controllers\Controller;
use Webtack\Ecms\Http\Requests\Admin\YoutubeLookupRequest;
use Webtack\Ecms\Youtube\YoutubeLookup;
use Webtack\Ecms\Youtube\YoutubeLookupException;

/**
 * "Get data" of the `youtube` field: reads a video from YouTube and stores its screenshot in the media
 * library. It changes no record; the form sends what it got as an ordinary value on save.
 */
class YoutubeLookupController extends Controller
{
    /**
     * Answer 200 with the video, 422 for a bad or unknown video, 503 when YouTube cannot be used.
     *
     * @example
     *   // POST /admin/youtube/lookup  {"url":"https://youtu.be/dQw4w9WgXcQ"}
     *   // 200 {"url":"https://youtu.be/dQw4w9WgXcQ","title":"…","description":"…",
     *   //      "screen":{"value":"<uuid>.jpg","uuid":"<uuid>","name":"dQw4w9WgXcQ.jpg","mime":"image/jpeg","size":1234,"url":"/storage/<uuid>.jpg","preview":"/storage/styles/thumb/<uuid>.webp"}}
     *   // 422 {"message":"The video was not found or it is private.","reason":"not_found"}
     */
    public function __invoke(YoutubeLookupRequest $request, YoutubeLookup $lookup, FileStorage $storage): JsonResponse
    {
        try {
            $video = $lookup->lookup($request->validated('url'), $request->user());
        } catch (YoutubeLookupException $e) {
            return response()->json(['message' => $e->userMessage(), 'reason' => $e->reason], $e->status());
        }

        $screen = $video['screen'];

        return response()->json([
            'url' => $video['url'],
            'title' => $video['title'],
            'description' => $video['description'],
            'screen' => [
                'value' => $screen->path,
                'uuid' => $screen->uuid,
                'name' => $screen->original_name,
                'mime' => $screen->mime,
                'size' => $screen->size,
                'url' => $storage->url($screen),
                'preview' => $storage->previewForValue($screen->profile, $screen->path, $screen->mime),
            ],
        ]);
    }
}
