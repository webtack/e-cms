<?php

namespace Webtack\Ecms\Files\Owners;

use Illuminate\Support\Facades\DB;

/**
 * Owner type `menu_item`: a file used as the image of a menu item. The label is the item title in the
 * default language (the item uuid when it has none). The link leads to the edit form of the item, built
 * from the menu key of the item, so no query per owner is needed.
 *
 * @example
 *   (new MenuItemOwner)->describe([5]);
 *   // [5 => ['label' => 'About us', 'url' => '/admin/menus/main/items/0190…/edit']]
 */
class MenuItemOwner implements MediaOwner
{
    public function describe(array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        $items = DB::table('menu_items')->whereIn('id', $ids)->get(['id', 'uuid', 'menu_key']);

        $titles = DB::table('menu_item_translations')
            ->whereIn('item_uuid', $items->pluck('uuid'))
            ->where('lang', config('content.default_lang'))
            ->pluck('title', 'item_uuid');

        $described = [];

        foreach ($items as $item) {
            $described[$item->id] = [
                'label' => $titles[$item->uuid] ?? $item->uuid,
                'url' => route('admin.menus.items.edit', [$item->menu_key, $item->uuid], false),
            ];
        }

        return $described;
    }
}
