<?php

namespace Webtack\Ecms\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;
use Webtack\Ecms\Models\Vocabulary;

/**
 * Validates a new order of the terms of a vocabulary.
 *
 * `order` is a list of term uuids, first to last row of the term table. It must contain exactly the uuids of all
 * terms of the vocabulary: no missing one, no repeat, no foreign one. A wrong set is reported under `order`, a
 * repeated uuid under that element (`order.1`).
 *
 * @example
 *   // PUT /admin/taxonomy/categories/terms/order  order[]=0190...&order[]=0190...
 *   $request->validated('order'); // ['0190...', '0190...']
 */
class ReorderTermsRequest extends FormRequest
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

            /** @var Vocabulary $vocabulary */
            $vocabulary = $this->route('vocabulary');
            $expected = $vocabulary->terms()->pluck('uuid')->all();
            $given = $this->input('order');

            if (array_diff($expected, $given) !== [] || array_diff($given, $expected) !== []) {
                $validator->errors()->add('order', __('The order must list exactly the terms of the vocabulary.'));
            }
        }];
    }
}
