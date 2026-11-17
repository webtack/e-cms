<?php

namespace Webtack\Ecms\Menus;

use Illuminate\Support\Facades\DB;
use Webtack\Ecms\Models\Menu;
use Webtack\Ecms\Models\MenuItem;

/**
 * Reads the structure of the item tree: ancestors, descendants and heights. Levels are walked
 * breadth first, one query per level; the number of levels is bounded by the menu's maximum depth,
 * and a visited set protects against damaged data. The algorithm mirrors `Webtack\Ecms\Taxonomy\TermTree`.
 *
 * @example
 *   $tree = app(MenuItemTree::class);
 *   $tree->descendantUuids([$catalog->uuid]); // [$catalog->uuid, $jackets->uuid, $winter->uuid]
 *   $tree->height($catalog);                  // 3 (itself plus two levels below)
 *   $tree->ancestorUuids($winter);            // [$jackets->uuid, $catalog->uuid]
 */
class MenuItemTree
{
    /**
     * The given items together with all their descendants, without duplicates.
     *
     * @param  list<string>  $uuids
     * @return list<string>
     */
    public function descendantUuids(array $uuids): array
    {
        $seen = array_fill_keys($uuids, true);
        $level = array_keys($seen);

        while ($level !== []) {
            $level = DB::table('menu_items')->whereIn('parent_uuid', $level)->pluck('uuid')
                ->reject(fn (string $uuid) => isset($seen[$uuid]))
                ->unique()->values()->all();

            $seen += array_fill_keys($level, true);
        }

        return array_keys($seen);
    }

    /**
     * Levels of the subtree of an item, counting the item itself (a leaf has height 1).
     */
    public function height(MenuItem $item): int
    {
        return $this->levelsBelow([$item->uuid]);
    }

    /**
     * Levels of the whole tree of a menu (0 for an empty menu).
     */
    public function menuHeight(Menu $menu): int
    {
        $roots = DB::table('menu_items')
            ->where('menu_key', $menu->key)->whereNull('parent_uuid')->pluck('uuid')->all();

        return $this->levelsBelow($roots);
    }

    /**
     * Uuids of the ancestors of an item, nearest first.
     *
     * @return list<string>
     */
    public function ancestorUuids(MenuItem $item): array
    {
        $ancestors = [];
        $parent = $item->parent_uuid;

        while ($parent !== null && ! in_array($parent, $ancestors, true)) {
            $ancestors[] = $parent;
            $parent = DB::table('menu_items')->where('uuid', $parent)->value('parent_uuid');
        }

        return $ancestors;
    }

    /**
     * Count the levels from the given items down.
     *
     * @param  list<string>  $level
     */
    private function levelsBelow(array $level): int
    {
        $seen = array_fill_keys($level, true);
        $levels = 0;

        while ($level !== []) {
            $levels++;
            $level = DB::table('menu_items')->whereIn('parent_uuid', $level)->pluck('uuid')
                ->reject(fn (string $uuid) => isset($seen[$uuid]))->values()->all();
            $seen += array_fill_keys($level, true);
        }

        return $levels;
    }
}
