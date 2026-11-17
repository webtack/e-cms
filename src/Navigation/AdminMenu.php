<?php

namespace Webtack\Ecms\Navigation;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Webtack\Ecms\Models\AdminMenuEntry;
use Webtack\Ecms\Models\ContentNode;
use Webtack\Ecms\Models\ContentType;

/**
 * Writes the admin menu: groups (with a translatable title), links to types and links to nodes, two levels deep.
 *
 * Rules kept here: a group lives in the root only, a link lives in the root or in a group, a type appears at most
 * once, a node appears at most once, a group title is not blank. Violations throw {@see ValidationException}, so
 * the page shows them next to the field. Reading for the sidebar is {@see SidebarNavigation}; the table is described in
 * docs/admin-navigation-menu.md.
 *
 * @example
 *   $menu = app(AdminMenu::class);
 *   $group = $menu->addGroup('Catalog');
 *   $menu->addLink('news', $group);
 *   $menu->addNodeLink($node->uuid, $group);
 *   $menu->save([['id' => $group->id, 'children' => [...]]]);
 */
class AdminMenu
{
    /** Longest group title. */
    public const TITLE_MAX = 100;

    /** Adds a group to the end of the root. */
    public function addGroup(string $title): AdminMenuEntry
    {
        return AdminMenuEntry::create([
            'title' => [AdminMenuEntry::LANG => $this->cleanTitle($title)],
            'position' => $this->nextPosition(null),
        ]);
    }

    /** Renames the base-language title, keeping the other languages. */
    public function renameGroup(AdminMenuEntry $group, string $title): void
    {
        $this->assertGroup($group);

        $group->update(['title' => [...($group->title ?? []), AdminMenuEntry::LANG => $this->cleanTitle($title)]]);
    }

    /**
     * Adds a link to a type at the end of the root or of a group.
     *
     * @throws ValidationException for an unknown type, a type already in the menu or a parent that is not a root group
     */
    public function addLink(string $typeKey, ?AdminMenuEntry $group = null): AdminMenuEntry
    {
        if (! ContentType::query()->where('key', $typeKey)->exists()) {
            throw ValidationException::withMessages(['type_key' => __('The selected type does not exist.')]);
        }

        if (AdminMenuEntry::query()->where('type_key', $typeKey)->exists()) {
            throw ValidationException::withMessages(['type_key' => __('This type is already in the menu.')]);
        }

        $this->assertRootGroup($group);

        return AdminMenuEntry::create([
            'type_key' => $typeKey,
            'parent_id' => $group?->id,
            'position' => $this->nextPosition($group?->id),
        ]);
    }

    /**
     * Adds a link to a node at the end of the root or of a group. The label is the node title, read at display time.
     *
     * @throws ValidationException for an unknown node, a node already in the menu or a parent that is not a root group
     */
    public function addNodeLink(string $uuid, ?AdminMenuEntry $group = null): AdminMenuEntry
    {
        if (! ContentNode::query()->where('uuid', $uuid)->exists()) {
            throw ValidationException::withMessages(['node_uuid' => __('The selected node does not exist.')]);
        }

        if (AdminMenuEntry::query()->where('node_uuid', $uuid)->exists()) {
            throw ValidationException::withMessages(['node_uuid' => __('This node is already in the menu.')]);
        }

        $this->assertRootGroup($group);

        return AdminMenuEntry::create([
            'node_uuid' => $uuid,
            'parent_id' => $group?->id,
            'position' => $this->nextPosition($group?->id),
        ]);
    }

    /** Deletes a link, or a group together with its links (the database cascades; types stay). */
    public function delete(AdminMenuEntry $entry): void
    {
        $entry->delete();
    }

    /**
     * Stores a whole new structure at once: the root order and the order inside every group.
     *
     * Every existing entry must appear exactly once; an entry with `children` must be a group and its children
     * must be links, so a group can neither be nested nor lose or gain entries by accident.
     *
     * @param  list<array{id: int, children?: list<int>}>  $items
     *
     * @throws ValidationException
     */
    public function save(array $items): void
    {
        $entries = AdminMenuEntry::query()->get()->keyBy('id');
        $seen = [];
        $plan = [];

        foreach (array_values($items) as $position => $item) {
            $entry = $entries[$item['id']] ?? null;
            $children = $item['children'] ?? [];

            if ($entry === null || $children !== [] && ! $entry->isGroup()) {
                throw ValidationException::withMessages(['items' => __('The menu structure is invalid.')]);
            }

            $plan[] = [$entry->id, null, $position];
            $seen[] = $entry->id;

            foreach (array_values($children) as $childPosition => $childId) {
                $child = $entries[$childId] ?? null;

                if ($child === null || $child->isGroup()) {
                    throw ValidationException::withMessages(['items' => __('A group cannot be nested.')]);
                }

                $plan[] = [$child->id, $entry->id, $childPosition];
                $seen[] = $child->id;
            }
        }

        $expected = $entries->keys()->all();
        sort($expected);
        sort($seen);

        if ($seen !== $expected) {
            throw ValidationException::withMessages(['items' => __('The menu structure is invalid.')]);
        }

        DB::transaction(function () use ($plan) {
            foreach ($plan as [$id, $parentId, $position]) {
                AdminMenuEntry::query()->whereKey($id)->update(['parent_id' => $parentId, 'position' => $position]);
            }
        });
    }

    /** A parent, when given, must be a group in the root. */
    private function assertRootGroup(?AdminMenuEntry $group): void
    {
        if ($group === null) {
            return;
        }

        $this->assertGroup($group);

        if ($group->parent_id !== null) {
            throw ValidationException::withMessages(['parent_id' => __('A group cannot be nested.')]);
        }
    }

    private function assertGroup(AdminMenuEntry $entry): void
    {
        if (! $entry->isGroup()) {
            throw ValidationException::withMessages(['title' => __('Only a group can be changed this way.')]);
        }
    }

    private function cleanTitle(string $title): string
    {
        $title = trim($title);

        if ($title === '' || mb_strlen($title) > self::TITLE_MAX) {
            throw ValidationException::withMessages(['title' => __('The group title is required and up to :max characters.', ['max' => self::TITLE_MAX])]);
        }

        return $title;
    }

    private function nextPosition(?int $parentId): int
    {
        $max = AdminMenuEntry::query()->where('parent_id', $parentId)->max('position');

        return $max === null ? 0 : $max + 1;
    }
}
