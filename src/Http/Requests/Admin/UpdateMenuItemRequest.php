<?php

namespace Webtack\Ecms\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Webtack\Ecms\Menus\MenuItemTree;
use Webtack\Ecms\Models\MenuItem;

/**
 * Validates the edit form of a menu item (the menu and the item come from the route parameters, the item is
 * already bound within the menu).
 *
 * The menu and `uuid` of an item never change: they have no rules. The parent can be neither the item nor
 * any of its descendants (the model also guards this and the depth limit).
 *
 * @example
 *   // PUT /admin/menus/main/items/{uuid}  translations[default][title]=About us&parent_uuid=
 */
class UpdateMenuItemRequest extends FormRequest
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
        /** @var MenuItem $item */
        $item = $this->route('item');
        $rules = StoreMenuItemRequest::sharedRules($this->route('menu'));
        $rules['parent_uuid'][] = Rule::notIn(app(MenuItemTree::class)->descendantUuids([$item->uuid]));

        return $rules;
    }

    /**
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [StoreMenuItemRequest::scheduleCheck(...)];
    }
}
