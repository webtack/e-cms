<?php

namespace Webtack\Ecms\Files;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Webtack\Ecms\Access\AccessResolver;
use Webtack\Ecms\Contracts\EcmsUser;
use Webtack\Ecms\Models\MediaRelation;

/**
 * Which places of a file's use a viewer may see in the media details.
 *
 * Seeing the library does not mean seeing everything that uses a file. A place is visible when the viewer
 * could open its owner anyway: a node when their level on its type is not `none` (and linkable only when
 * they may edit that node); a user with `users`; a site setting with `settings`; a term with `taxonomy`; a
 * menu item with `menus`; a type with `types`. An owner of an unknown kind is never visible. An
 * administrator sees every known owner.
 *
 * @example
 *   $access = app(UsageAccess::class)->for($viewer, $usages);
 *   // [0 => ['visible' => true, 'linkable' => true], 1 => ['visible' => false, 'linkable' => false]]
 */
class UsageAccess
{
    /** Owner type => the system right that opens it. */
    private const RIGHTS = [
        'user' => 'users',
        'site_setting' => 'settings',
        'taxonomy_term' => 'taxonomy',
        'menu_item' => 'menus',
        'content_type' => 'types',
    ];

    public function __construct(private readonly AccessResolver $access) {}

    /**
     * Visibility of each usage, keyed by its position in `$usages`.
     *
     * @param  Collection<int, MediaRelation>  $usages
     * @return array<int, array{visible: bool, linkable: bool}>
     */
    public function for(EcmsUser $viewer, Collection $usages): array
    {
        $administrator = $this->access->isAdministrator($viewer);
        $nodes = $this->nodes($usages->where('owner_type', 'content_node'));
        $result = [];

        foreach ($usages->values() as $index => $usage) {
            $result[$index] = match (true) {
                $usage->owner_type === 'content_node' => $this->node($viewer, $administrator, $nodes[$usage->owner_id] ?? null),
                isset(self::RIGHTS[$usage->owner_type]) => [
                    'visible' => $administrator || $this->access->allows($viewer, self::RIGHTS[$usage->owner_type]),
                    'linkable' => true,
                ],
                default => ['visible' => false, 'linkable' => false],
            };
        }

        return $result;
    }

    /**
     * @param  object{content_type: string, user_id: int|null}|null  $node  Null when the node row is gone.
     * @return array{visible: bool, linkable: bool}
     */
    private function node(EcmsUser $viewer, bool $administrator, ?object $node): array
    {
        if ($administrator) {
            return ['visible' => true, 'linkable' => true];
        }

        if ($node === null || $this->access->nodeLevel($viewer, $node->content_type) === AccessResolver::NONE) {
            return ['visible' => false, 'linkable' => false];
        }

        return ['visible' => true, 'linkable' => $this->access->canEditNode($viewer, $node->content_type, (int) $node->user_id)];
    }

    /**
     * Type and author of the nodes in `$usages`, in one query.
     *
     * @param  Collection<int, MediaRelation>  $usages
     * @return Collection<int, object{content_type: string, user_id: int|null}>
     */
    private function nodes(Collection $usages): Collection
    {
        $ids = $usages->pluck('owner_id')->unique()->values()->all();

        if ($ids === []) {
            return collect();
        }

        return DB::table('content_nodes')->whereIn('id', $ids)->get(['id', 'content_type', 'user_id'])->keyBy('id');
    }
}
