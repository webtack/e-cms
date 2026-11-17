<?php

namespace Webtack\Ecms\Files;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Webtack\Ecms\Models\MediaRelation;

/**
 * The single writer of `media_relations`: which files an owner uses, in which role and language.
 *
 * It opens no transaction of its own. A consumer calls it inside the transaction that writes the
 * value, so the relations and the value commit or roll back together.
 *
 * @example
 *   $relations = app(FileRelations::class);
 *   $relations->sync('content_node', $node->id, 'cover', 'default', [$uuid]); // bind, replace or clear
 *   $relations->forgetOwner('content_node', $node->id);                       // the owner is deleted
 *   $relations->usageCounts([$uuid]);                                         // [$uuid => 1]
 */
class FileRelations
{
    /**
     * Make the files of one owner, role and language exactly `$fileUuids`: relations to other files
     * are removed, missing ones are added, existing ones stay. Repeating a call changes nothing.
     *
     * @param  list<string>  $fileUuids  `media.uuid` values; an empty list clears the role.
     */
    public function sync(string $ownerType, int $ownerId, string $role, string $lang, array $fileUuids): void
    {
        $wanted = array_values(array_unique($fileUuids));

        $scope = fn () => MediaRelation::query()
            ->where('owner_type', $ownerType)
            ->where('owner_id', $ownerId)
            ->where('role', $role)
            ->where('lang', $lang);

        $current = $scope()->pluck('file_uuid')->all();

        $stale = array_diff($current, $wanted);
        if ($stale !== []) {
            $scope()->whereIn('file_uuid', $stale)->delete();
        }

        $missing = array_diff($wanted, $current);
        if ($missing !== []) {
            $now = now();

            MediaRelation::query()->insert(array_map(fn (string $uuid) => [
                'file_uuid' => $uuid,
                'owner_type' => $ownerType,
                'owner_id' => $ownerId,
                'role' => $role,
                'lang' => $lang,
                'created_at' => $now,
                'updated_at' => $now,
            ], array_values($missing)));
        }
    }

    /**
     * Remove every relation of an owner (the owner is deleted). The files stay.
     */
    public function forgetOwner(string $ownerType, int $ownerId): void
    {
        MediaRelation::query()->where('owner_type', $ownerType)->where('owner_id', $ownerId)->delete();
    }

    /**
     * Remove the relations of one role (the field is deleted), for the given owners only: a role such
     * as a field key repeats across content types, so the caller narrows it to the owners concerned.
     *
     * @param  mixed  $ownerIds  Owner ids, or a subquery selecting them; `null` means every owner of the type.
     *
     * @example
     *   $relations->forgetRole('content_node', 'cover', ContentNode::query()->where('content_type', 'article')->select('id'));
     */
    public function forgetRole(string $ownerType, string $role, mixed $ownerIds = null): void
    {
        MediaRelation::query()
            ->where('owner_type', $ownerType)
            ->where('role', $role)
            ->when($ownerIds !== null, fn ($query) => $query->whereIn('owner_id', $ownerIds))
            ->delete();
    }

    /**
     * Number of relations per file; files without relations are present with 0.
     *
     * @param  list<string>  $fileUuids
     * @return array<string, int>
     */
    public function usageCounts(array $fileUuids): array
    {
        $counts = MediaRelation::query()
            ->whereIn('file_uuid', $fileUuids)
            ->select('file_uuid', DB::raw('count(*) as total'))
            ->groupBy('file_uuid')
            ->pluck('total', 'file_uuid');

        return collect($fileUuids)->mapWithKeys(fn (string $uuid) => [$uuid => (int) ($counts[$uuid] ?? 0)])->all();
    }

    /**
     * The relations of one file, oldest first.
     *
     * @return Collection<int, MediaRelation>
     */
    public function usages(string $fileUuid): Collection
    {
        return MediaRelation::query()->where('file_uuid', $fileUuid)->orderBy('id')->get();
    }
}
