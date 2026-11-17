<?php

namespace Webtack\Ecms\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Webtack\Ecms\Models\ContentType;

/**
 * Validates the edit form of a content type (the type comes from the `contentType` route parameter,
 * already bound within the route's kind).
 *
 * `key` and `kind` have no rules, so they never reach `validated()` and stay unchanged. The slug (kind
 * `content` only) is unique among the other types, and the parent can be neither the type itself nor any descendant
 * (that would make a cycle).
 *
 * @example
 *   // PUT /admin/content-types/news  name=Latest&slug=latest&parent_type=&description=
 *   $type->update($request->validated());
 */
class UpdateContentTypeRequest extends FormRequest
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
        /** @var ContentType $type */
        $type = $this->route('contentType');
        $rules = StoreContentTypeRequest::sharedRules($type, $type->kind);
        $rules['parent_type'][] = Rule::notIn([$type->key, ...$type->descendantKeys()]);

        return $rules;
    }
}
