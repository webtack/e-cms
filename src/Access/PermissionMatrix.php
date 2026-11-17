<?php

namespace Webtack\Ecms\Access;

use Illuminate\Support\Facades\DB;
use Webtack\Ecms\Models\ContentType;
use Webtack\Ecms\Models\Role;
use Webtack\Ecms\Models\RolePermission;

/**
 * Reads and writes the permissions of a role in the shape of the role form: system switches, a level per
 * content type (grouped by kind) and a level for media.
 *
 * The matrix is built from the types that exist at the moment, so a type created after the role shows `none`.
 * The system role is shown as allowed everything and is never written. See docs/admin-roles.md.
 *
 * @example
 *   $matrix = app(PermissionMatrix::class);
 *   $matrix->for($role);   // ['system' => ['users' => true, …], 'nodes' => ['news' => 'own'], 'media' => 'any']
 *   $matrix->groups();     // [['kind' => 'content', 'types' => [['key' => 'news', 'name' => 'News']]], …]
 *   $matrix->save($role, ['system' => ['menus' => true], 'nodes' => ['news' => 'own'], 'media' => 'none']);
 */
class PermissionMatrix
{
    /** Kinds in the order the form shows them. */
    public const KINDS = ['content', 'page', 'block'];

    public function __construct(private readonly AccessResolver $access) {}

    /**
     * The types grouped by kind, by name, for rendering the matrix.
     *
     * @return list<array{kind: string, types: list<array{key: string, name: string}>}>
     */
    public function groups(): array
    {
        $types = ContentType::query()->orderBy('name')->get(['key', 'name', 'kind']);

        return collect(self::KINDS)->map(fn (string $kind) => [
            'kind' => $kind,
            'types' => $types->where('kind', $kind)->map(fn (ContentType $type) => $type->only(['key', 'name']))->values()->all(),
        ])->all();
    }

    /**
     * The role's permissions: every system key, every existing type key and media, with `none` for a missing row.
     *
     * @return array{system: array<string, bool>, nodes: array<string, string>, media: string}
     */
    public function for(Role $role): array
    {
        $rows = $role->is_system ? collect() : $role->permissions()->pluck('level', 'scope');
        $level = fn (string $scope) => $role->is_system ? AccessResolver::ANY : $this->level($rows[$scope] ?? null);

        return [
            'system' => collect(AccessResolver::SYSTEM)->mapWithKeys(fn (string $key) => [
                $key => $role->is_system || ($rows[$key] ?? null) === 'allow',
            ])->all(),
            'nodes' => ContentType::query()->pluck('key')->mapWithKeys(fn (string $key) => [$key => $level("node:{$key}")])->all(),
            'media' => $level(AccessResolver::MEDIA),
        ];
    }

    /**
     * Replaces the role's permissions with the submitted ones. Unknown system keys and keys of types that do not
     * exist are ignored; `none` stores no row. Does nothing for the system role.
     *
     * @param  array{system?: array<string, mixed>, nodes?: array<string, mixed>, media?: mixed}  $permissions
     */
    public function save(Role $role, array $permissions): void
    {
        if ($role->is_system) {
            return;
        }

        $rows = [];

        foreach (AccessResolver::SYSTEM as $key) {
            if (filter_var($permissions['system'][$key] ?? false, FILTER_VALIDATE_BOOLEAN)) {
                $rows[$key] = 'allow';
            }
        }

        foreach (ContentType::query()->pluck('key') as $key) {
            $level = $this->level($permissions['nodes'][$key] ?? null);

            if ($level !== AccessResolver::NONE) {
                $rows["node:{$key}"] = $level;
            }
        }

        $media = $this->level($permissions['media'] ?? null);

        if ($media !== AccessResolver::NONE) {
            $rows[AccessResolver::MEDIA] = $media;
        }

        DB::transaction(function () use ($role, $rows) {
            $role->permissions()->delete();

            foreach ($rows as $scope => $level) {
                RolePermission::create(['role_id' => $role->id, 'scope' => $scope, 'level' => $level]);
            }
        });

        // The new permissions apply from the next request.
        $this->access->flush();
    }

    private function level(mixed $value): string
    {
        return in_array($value, [AccessResolver::OWN, AccessResolver::ANY], true) ? $value : AccessResolver::NONE;
    }
}
