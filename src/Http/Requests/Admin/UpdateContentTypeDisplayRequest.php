<?php

namespace Webtack\Ecms\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates the shape of the "Display" tab of a type: `values` is an array of field key => value (kind `content`
 * only; the values themselves are checked by `ContentValueWriter`) and `settings.public` holds the public display
 * settings. Keys of `settings.public` are checked against the type's fields by `ContentType::assertValidPublicSettings()`
 * when the type is saved. The meta data choice (`meta`) is not accepted here: it is saved by the "SEO" tab
 * (saved with the "SEO" values), and this request leaves the stored one untouched.
 *
 * @example
 *   // PUT /admin/content-types/news/display  values[section_title]=News&settings[public][per_page]=10
 */
class UpdateContentTypeDisplayRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'values' => ['array'],
            'settings' => ['nullable', 'array:public'],
            // A missing `page_fields` means "all added fields".
            'settings.public' => ['nullable', 'array:list_fields,page_fields,sort,per_page,pagination,archive'],
            'settings.public.list_fields' => ['nullable', 'array'],
            'settings.public.list_fields.*' => ['string', 'distinct'],
            'settings.public.page_fields' => ['nullable', 'array'],
            'settings.public.page_fields.*' => ['string', 'distinct'],
            'settings.public.sort' => ['nullable', 'array:field,direction'],
            'settings.public.sort.field' => ['required_with:settings.public.sort.direction', 'nullable', 'string'],
            'settings.public.sort.direction' => ['nullable', 'in:asc,desc'],
            'settings.public.archive' => ['nullable', 'array:field,sort'],
            'settings.public.archive.field' => ['required_with:settings.public.archive.sort', 'nullable', 'string'],
            'settings.public.archive.sort' => ['nullable', 'array:field,direction'],
            'settings.public.archive.sort.field' => ['required_with:settings.public.archive.sort.direction', 'nullable', 'string'],
            'settings.public.archive.sort.direction' => ['nullable', 'in:asc,desc'],
            'settings.public.per_page' => ['nullable', 'integer', 'min:0', 'max:'.config('content.max_per_page')],
            'settings.public.pagination' => ['nullable', 'boolean'],
        ];
    }
}
