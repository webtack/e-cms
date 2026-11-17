<?php

namespace Webtack\Ecms\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates the translation form of a node: only that `values` is an array. The values themselves are checked by
 * `TranslationSaver` (translatable fields only, no required rule). Authorized like editing the node in the base
 * language, so a user who may not edit another user's node may not translate it either. `file_meta` carries the
 * translated descriptions of the node's files (see {@see self::fileDescriptionRules()}).
 *
 * @example
 *   // PUT /admin/content-types/news/nodes/0190a1b2-…/translations/en  values[title]=Hello
 */
class SaveNodeTranslationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('node'));
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return ['values' => ['sometimes', 'array'], ...self::fileDescriptionRules()];
    }

    /**
     * Rules of the translated file names and descriptions (`file_meta.<uuid>.name` / `.description`), shared with the type
     * tabs. The translated name is for display only; the original name of the file is never changed. That a uuid belongs to the owner is checked
     * by `TranslationSaver::saveFileDescriptions()`.
     *
     * @return array<string, array<int, mixed>>
     *
     * @example
     *   // file_meta[0190a1b27c3e7f0a8b1c2d3e4f5a6b7c][description]=Front cover
     */
    public static function fileDescriptionRules(): array
    {
        return [
            'file_meta' => StoreContentNodeRequest::fileMetaRules()['file_meta'],
            'file_meta.*' => ['array'],
            'file_meta.*.name' => ['nullable', 'string', 'max:255'],
            'file_meta.*.description' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
