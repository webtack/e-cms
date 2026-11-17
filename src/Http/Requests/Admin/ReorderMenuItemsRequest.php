<?php

namespace Webtack\Ecms\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;
use Webtack\Ecms\Models\Menu;

/**
 * Validates a new order of the items of a menu.
 *
 * `order` is a list of item uuids, first to last row of the item table. It must contain exactly the uuids of all
 * items of the menu: no missing one, no repeat, no foreign one. A wrong set is reported under `order`, a
 * repeated uuid under that element (`order.1`).
 *
 * @example
 *   // PUT /admin/menus/main/items/order  order[]=0190...&order[]=0190...
 *   $request->validated('order'); // ['0190...', '0190...']
 */
class ReorderMenuItemsRequest extends FormRequest
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

            /** @var Menu $menu */
            $menu = $this->route('menu');
            $expected = $menu->items()->pluck('uuid')->all();
            $given = $this->input('order');

            if (array_diff($expected, $given) !== [] || array_diff($given, $expected) !== []) {
                $validator->errors()->add('order', __('The order must list exactly the items of the menu.'));
            }
        }];
    }
}
