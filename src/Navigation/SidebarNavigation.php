<?php

namespace Webtack\Ecms\Navigation;

use Illuminate\Support\Collection;
use Webtack\Ecms\Access\AccessResolver;
use Webtack\Ecms\Contracts\EcmsUser;
use Webtack\Ecms\Http\Controllers\Admin\Concerns\TypeSections;
use Webtack\Ecms\Models\AdminMenuEntry;
use Webtack\Ecms\Models\ContentNode;
use Webtack\Ecms\Models\ContentType;
use Webtack\Ecms\Models\UserFavoriteType;

/**
 * What the sidebar shows besides its fixed items: the user's favorites and the admin menu, both filtered by the
 * user's node level (a type with level `none` is hidden, a group without visible links too). A node link of the
 * menu is shown only when the user may edit that node ({@see AccessResolver::canEditNode()}), labeled with the
 * node title read at display time.
 *
 * Filtering happens on every read and nothing is deleted: when access comes back, the item comes back. Rows of
 * deleted types are gone by foreign-key cascade. See docs/admin-favorites.md and docs/admin-navigation-menu.md.
 *
 * @example
 *   app(SidebarNavigation::class)->forUser($user);
 *   // ['favorites' => [['key' => 'news', 'name' => 'News', 'kind' => 'content', 'href' => '/admin/content-types/news/nodes']],
 *   //  'menu' => [['type' => 'group', 'id' => 3, 'title' => 'Catalog', 'children' => [...]], ['type' => 'link', ...]]]
 */
class SidebarNavigation
{
    public function __construct(private readonly AccessResolver $access, private readonly NodeTitles $nodeTitles) {}

    /**
     * @return array{favorites: list<array<string, string>>, menu: list<array<string, mixed>>}
     */
    public function forUser(EcmsUser $user): array
    {
        $types = ContentType::query()->get(['key', 'name', 'kind'])->keyBy('key');

        return ['favorites' => $this->favorites($user, $types), 'menu' => $this->menu($user, $types)];
    }

    /**
     * @param  Collection<string, ContentType>  $types
     * @return list<array<string, string>>
     */
    private function favorites(EcmsUser $user, $types): array
    {
        return UserFavoriteType::query()->where('user_id', $user->id)->orderBy('id')->pluck('type_key')
            ->map(fn (string $key) => $this->link($user, $types[$key] ?? null))
            ->filter()
            ->values()
            ->all();
    }

    /**
     * @param  Collection<string, ContentType>  $types
     * @return list<array<string, mixed>>
     */
    private function menu(EcmsUser $user, $types): array
    {
        $entries = AdminMenuEntry::query()->orderBy('position')->orderBy('id')->get();
        $byParent = $entries->groupBy('parent_id');
        $nodes = $this->nodes($entries);
        $link = fn (AdminMenuEntry $entry) => $entry->isNodeLink()
            ? $this->nodeLink($user, $nodes[$entry->node_uuid] ?? null, $types)
            : $this->link($user, $types[$entry->type_key] ?? null);
        $menu = [];

        foreach ($byParent->get(null, collect()) as $entry) {
            if (! $entry->isGroup()) {
                $item = $link($entry);

                if ($item !== null) {
                    $menu[] = ['type' => 'link', ...$item];
                }

                continue;
            }

            $children = $byParent->get($entry->id, collect())
                ->map($link)
                ->filter()
                ->values()
                ->all();

            if ($children !== []) {
                $menu[] = ['type' => 'group', 'id' => $entry->id, 'title' => $entry->titleFor(), 'children' => array_map(fn (array $item) => ['type' => 'link', ...$item], $children)];
            }
        }

        return $menu;
    }

    /**
     * The nodes of all node links with their titles, in one query for the nodes and one for the titles.
     *
     * @param  Collection<int, AdminMenuEntry>  $entries
     * @return array<string, array{node: ContentNode, title: string}> Keyed by node uuid.
     */
    private function nodes($entries): array
    {
        $uuids = $entries->pluck('node_uuid')->filter()->values()->all();

        if ($uuids === []) {
            return [];
        }

        $titles = $this->nodeTitles->for($uuids);

        return ContentNode::query()->whereIn('uuid', $uuids)->get(['uuid', 'content_type', 'user_id'])
            ->mapWithKeys(fn (ContentNode $node) => [$node->uuid => ['node' => $node, 'title' => $titles[$node->uuid] ?? $node->uuid]])
            ->all();
    }

    /**
     * The link of a node the user may edit, or null.
     *
     * @param  array{node: ContentNode, title: string}|null  $row
     * @param  Collection<string, ContentType>  $types
     * @return array{key: string, name: string, kind: string, href: string, node: true}|null
     */
    private function nodeLink(EcmsUser $user, ?array $row, $types): ?array
    {
        if ($row === null) {
            return null;
        }

        $node = $row['node'];

        if (! $this->access->canEditNode($user, $node->content_type, $node->user_id)) {
            return null;
        }

        $type = $types[$node->content_type] ?? null;

        return $type === null ? null : [
            'node' => true,
            'key' => $node->uuid,
            'name' => $row['title'],
            'kind' => $type->kind,
            'href' => '/admin/'.TypeSections::segment($type->kind).'/'.$type->key.'/nodes/'.$node->uuid.'/edit',
        ];
    }

    /**
     * The link of a type the user may open, or null.
     *
     * @return array{key: string, name: string, kind: string, href: string}|null
     */
    private function link(EcmsUser $user, ?ContentType $type): ?array
    {
        if ($type === null || $this->access->nodeLevel($user, $type) === AccessResolver::NONE) {
            return null;
        }

        return [
            'key' => $type->key,
            'name' => $type->name,
            'kind' => $type->kind,
            'href' => '/admin/'.TypeSections::segment($type->kind).'/'.$type->key.'/nodes',
        ];
    }
}
