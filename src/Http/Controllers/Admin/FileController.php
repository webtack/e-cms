<?php

namespace Webtack\Ecms\Http\Controllers\Admin;

use Illuminate\Http\JsonResponse;
use Webtack\Ecms\Content\FieldTypeRegistry;
use Webtack\Ecms\Content\FieldTypes\HasUploadProfile;
use Webtack\Ecms\Files\FileStorage;
use Webtack\Ecms\Files\UploadRules;
use Webtack\Ecms\Http\Controllers\Controller;
use Webtack\Ecms\Http\Requests\Admin\StoreFileRequest;

/**
 * Upload endpoint of the file storage: the first step of the two-step flow.
 *
 * The form then sends only the returned `uuid` (or `value`, `{uuid}.{ext}`) as an ordinary value; the endpoint knows nothing about
 * the consumer (whether the file belongs to the consumer's profile is checked by the consumer with
 * the `FileOfProfile` rule).
 */
class FileController extends Controller
{
    /**
     * Store an uploaded file under a profile, or for a content field (`content_type` + `field_key`, whose
     * type gives the profile and whose settings narrow it), and answer 201 with its metadata.
     *
     * @example
     *   // POST /admin/files  file=<binary>  profile=image
     *   // 201 {"uuid":"0190a1b27c3e7f0a8b1c2d3e4f5a6b7c","profile":"image","name":"logo.png",
     *   //      "mime":"image/png","size":1234,"width":640,"height":480,
     *   //      "value":"0190a1b27c3e7f0a8b1c2d3e4f5a6b7c.png","url":"/storage/0190a1b27c3e7f0a8b1c2d3e4f5a6b7c.png",
     *   //      "preview":"/storage/styles/thumb/0190a1b27c3e7f0a8b1c2d3e4f5a6b7c.webp"}
     */
    public function store(StoreFileRequest $request, FileStorage $storage, FieldTypeRegistry $registry): JsonResponse
    {
        $field = $request->field();

        // By a field the profile is the field type's, narrowed by the field's settings (`richtext`: its `image` images).
        $type = $field === null ? null : $registry->get($field->type);
        $profile = $type instanceof HasUploadProfile ? $type->uploadProfile() : $type?->profile();

        $file = $field === null
            ? $storage->store($request->file('file'), $request->validated('profile'), $request->user())
            : $storage->store($request->file('file'), $profile, $request->user(), UploadRules::forField($field));

        return response()->json([
            'uuid' => $file->uuid,
            'profile' => $file->profile,
            'name' => $file->original_name,
            'mime' => $file->mime,
            'size' => $file->size,
            'width' => $file->width,
            'height' => $file->height,
            'value' => $file->path,
            'url' => $storage->url($file),
            // The `thumb` style for the widget's preview; null for a document.
            'preview' => $storage->previewForValue($file->profile, $file->path, $file->mime),
        ], 201);
    }
}
