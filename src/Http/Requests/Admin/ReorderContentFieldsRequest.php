<?php

namespace Webtack\Ecms\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;
use Webtack\Ecms\Content\SystemFields;
use Webtack\Ecms\Models\ContentType;

/**
 * Validates a new order of the fields of a content type.
 *
 * `order` is a list of field keys, first to last. It must contain exactly the keys of the fields the
 * fields table shows, that is all of the type's fields except panel fields (`meta_*`, `section_*`): no missing key, no
 * repeat, no foreign key (a panel field key counts as foreign). A wrong key set is reported under `order`, a repeated key under that
 * element (`order.1`).
 *
 * @example
 *   // PUT /admin/content-types/news/fields/order  order[]=title&order[]=body&order[]=summary
 *   $request->validated('order'); // ['title', 'body', 'summary']
 */
class ReorderContentFieldsRequest extends FormRequest
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
        return [
            'order' => ['required', 'array'],
            'order.*' => ['string', 'distinct'],
        ];
    }

    /**
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [function (Validator $validator) {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            /** @var ContentType $type */
            $type = $this->route('contentType');
            $systemFields = app(SystemFields::class);
            $expected = $type->fields()->pluck('key')->reject(fn (string $key) => $systemFields->panel($key) !== null)->values()->all();
            $given = $this->input('order');

            if (array_diff($expected, $given) !== [] || array_diff($given, $expected) !== []) {
                $validator->errors()->add('order', __('The order must list exactly the fields of the type.'));
            }
        }];
    }
}
