<?php

namespace Webtack\Ecms\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates the clone dialog of a content type: `name`, `key`, `parent_type` and (kind `content` only) `slug`,
 * by the same rules as the create form ({@see StoreContentTypeRequest}). `description` and `settings` are not
 * accepted: the clone has no description and takes the settings from the source.
 *
 * @example
 *   // POST /admin/content-types/news/clone  name=Press&key=press&slug=press&parent_type=
 */
class CloneContentTypeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $shared = StoreContentTypeRequest::sharedRules(kind: $this->route()->defaults['kind']);

        return [
            ...array_intersect_key($shared, array_flip(['name', 'slug', 'parent_type'])),
            'key' => StoreContentTypeRequest::keyRules(),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'key.not_in' => __('This key is reserved.'),
        ];
    }
}
