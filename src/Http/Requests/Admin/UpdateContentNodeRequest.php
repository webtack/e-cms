<?php

namespace Webtack\Ecms\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use Webtack\Ecms\Models\ContentNode;

/**
 * Validates the edit form of a content node: the required `slug`, that `values` is an array and the `file_meta`
 * of its files (rules shared with {@see StoreContentNodeRequest::fileMetaRules()}).
 *
 * The strict slug format is required only for a changed slug, so an imported node with a legacy slug
 * ({@see ContentNode::LEGACY_SLUG_PATTERN}) can be saved while its slug is untouched.
 *
 * Uniqueness ignores the node itself, so saving without changing the slug is not a duplicate; the model leaves
 * uniqueness to the database index, where a clash on update would be a 500 instead of a form error. As in
 * {@see StoreContentNodeRequest} the values are validated later by `ContentValueWriter`.
 *
 * `is_active`, `published_at` and the optional `unpublished_at` (later than `published_at`) are the service attributes; `user_id` is ignored (the author never changes).
 *
 * @example
 *   // PUT /admin/content-types/news/nodes/0190a1b2-…  slug=about-us&values[title]=About us
 *   $node->update(['slug' => $request->validated('slug')]);
 */
class UpdateContentNodeRequest extends FormRequest
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
        /** @var ContentNode $node */
        $node = $this->route('node');

        return [
            'slug' => ['required', ...StoreContentNodeRequest::slugRules($node->slug, $this->input('slug')), Rule::unique('content_nodes', 'slug')->ignore($node), ...StoreContentNodeRequest::publicPathRules($this->route('contentType'), $node->slug)],
            'is_active' => ['sometimes', 'boolean'],
            // Required on update: a cleared date is an error, never "now" (the node already has a date).
            'published_at' => ['required', 'date_format:'.StoreContentNodeRequest::PUBLISHED_AT_FORMAT],
            'unpublished_at' => ['nullable', 'date_format:'.StoreContentNodeRequest::PUBLISHED_AT_FORMAT],
            'values' => ['sometimes', 'array'],
            ...StoreContentNodeRequest::fileMetaRules(),
        ];
    }

    /**
     * @return list<callable(Validator): void>
     */
    public function after(): array
    {
        return [fn (Validator $validator) => StoreContentNodeRequest::scheduleCheck($validator)];
    }
}
