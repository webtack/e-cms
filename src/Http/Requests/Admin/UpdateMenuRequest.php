<?php

namespace Webtack\Ecms\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;
use Webtack\Ecms\Menus\MenuItemTree;
use Webtack\Ecms\Models\Menu;

/**
 * Validates the edit form of a menu (the menu comes from the `menu` route parameter).
 *
 * `key` has no rule, so it never reaches `validated()` and stays unchanged. A maximum depth below the
 * height of the existing item tree is reported under `max_depth`.
 *
 * @example
 *   // PUT /admin/menus/main  name=Main menu&description=&max_depth=2
 */
class UpdateMenuRequest extends FormRequest
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
        return StoreMenuRequest::sharedRules();
    }

    /**
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [function (Validator $validator) {
            $depth = $this->input('max_depth');

            if ($validator->errors()->isNotEmpty() || $depth === null || $depth === '') {
                return;
            }

            /** @var Menu $menu */
            $menu = $this->route('menu');

            if (app(MenuItemTree::class)->menuHeight($menu) > (int) $depth) {
                $validator->errors()->add('max_depth', __('The maximum depth is lower than the depth of the existing items.'));
            }
        }];
    }
}
