<?php

namespace Webtack\Ecms\Access;

use Webtack\Ecms\Contracts\EcmsUser;
use Webtack\Ecms\Models\ContentType;
use Webtack\Ecms\Models\Role;
use Webtack\Ecms\Policies\UserPolicy;

/**
 * Answers "what may this user do" from the permission rows of the user's role.
 *
 * Scopes: system keys ({@see self::SYSTEM}, yes/no), `node:{type_key}` and `media` (levels `none` / `own` / `any`).
 * A missing row means `none`. The system role (`admin`) is allowed everything and has no rows, so a type
 * created later is open to it at once. Permissions of a role are loaded once per user object and kept in memory
 * (until {@see self::flush()}, which role changes call). See docs/access-control.md.
 *
 * @example
 *   $access = app(AccessResolver::class);
 *   $access->allows($user, 'menus');        // bool
 *   $access->level($user, 'node:news');     // 'none' | 'own' | 'any'
 *   $access->mediaLevel($user);             // 'none' | 'own' | 'any'
 *   $access->canEditAnyContent($user);      // own/any on some type or on media
 */
class AccessResolver
{
    public const NONE = 'none';

    public const OWN = 'own';

    public const ANY = 'any';

    /** System permissions (the `allow` level). */
    public const SYSTEM = ['users', 'roles', 'menus', 'settings', 'taxonomy', 'types'];

    /** Scope of the media library. */
    public const MEDIA = 'media';

    /** @var \WeakMap<EcmsUser, array{role_id: int|null, data: array{system: bool, levels: array<string, string>}}> Per user instance. */
    private \WeakMap $roles;

    /** The permission set of a user without a role (or with a role that no longer exists). */
    private const NO_ACCESS = ['system' => false, 'levels' => []];

    public function __construct()
    {
        $this->roles = new \WeakMap;
    }

    /**
     * Whether the user holds a system permission (one of {@see self::SYSTEM}).
     */
    public function allows(EcmsUser $user, string $permission): bool
    {
        $role = $this->role($user);

        return $role['system'] || (in_array($permission, self::SYSTEM, true) && ($role['levels'][$permission] ?? null) === 'allow');
    }

    /**
     * Whether the user has the system role (`admin`), the only role that is allowed everything.
     *
     * Not the same as holding every permission: a role with `users` and `roles` is not an administrator. Object-level
     * rules that protect administrator accounts ({@see UserPolicy}) ask this.
     *
     * @example
     *   app(AccessResolver::class)->isAdministrator($user); // true only for the system role
     */
    public function isAdministrator(EcmsUser $user): bool
    {
        return $this->role($user)['system'];
    }

    /**
     * The level of a `node:{type_key}` or `media` scope; the system role has `any` everywhere.
     *
     * @return 'none'|'own'|'any'
     */
    public function level(EcmsUser $user, string $scope): string
    {
        $role = $this->role($user);

        if ($role['system']) {
            return self::ANY;
        }

        $level = $role['levels'][$scope] ?? self::NONE;

        return in_array($level, [self::OWN, self::ANY], true) ? $level : self::NONE;
    }

    /**
     * The level of the nodes of a type.
     *
     * @return 'none'|'own'|'any'
     */
    public function nodeLevel(EcmsUser $user, ContentType|string $type): string
    {
        return $this->level($user, 'node:'.($type instanceof ContentType ? $type->key : $type));
    }

    /**
     * Whether the user may change (and so open the edit form of) a node of a type, given the node author.
     *
     * The one rule behind `ContentNodePolicy::update`/`delete` and the node links of the admin menu: `any` allows
     * every node, `own` only the nodes the user authored, `none` nothing.
     */
    public function canEditNode(EcmsUser $user, ContentType|string $type, int $authorId): bool
    {
        return match ($this->nodeLevel($user, $type)) {
            self::ANY => true,
            self::OWN => $authorId === $user->id,
            default => false,
        };
    }

    /**
     * The level of the media library.
     *
     * @return 'none'|'own'|'any'
     */
    public function mediaLevel(EcmsUser $user): string
    {
        return $this->level($user, self::MEDIA);
    }

    /**
     * Whether the user may write something: `own` or `any` on at least one existing type or on media. Rows of
     * types that no longer exist are ignored.
     */
    public function canEditAnyContent(EcmsUser $user): bool
    {
        $role = $this->role($user);

        if ($role['system'] || $this->mediaLevel($user) !== self::NONE) {
            return true;
        }

        $keys = [];

        foreach ($role['levels'] as $scope => $level) {
            if (str_starts_with($scope, 'node:') && in_array($level, [self::OWN, self::ANY], true)) {
                $keys[] = substr($scope, 5);
            }
        }

        return $keys !== [] && ContentType::query()->whereIn('key', $keys)->exists();
    }

    /**
     * The permissions as shared with the client, for showing and hiding elements only: system permissions, the level
     * per type key, the level of media, whether the user may write anything and, per kind of type, whether the user
     * has `own` or `any` on at least one type of it (the sidebar shows a section by that).
     *
     * @return array{system: array<string, bool>, nodes: array<string, string>, kinds: array<string, bool>, media: string, edit: bool}
     */
    public function forClient(EcmsUser $user): array
    {
        $types = ContentType::query()->get(['key', 'kind']);
        $nodes = $types->mapWithKeys(fn (ContentType $type) => [$type->key => $this->nodeLevel($user, $type)]);

        return [
            'system' => collect(self::SYSTEM)->mapWithKeys(fn (string $key) => [$key => $this->allows($user, $key)])->all(),
            'nodes' => $nodes->all(),
            'kinds' => collect(['content', 'page', 'block'])->mapWithKeys(fn (string $kind) => [
                $kind => $types->where('kind', $kind)->contains(fn (ContentType $type) => $nodes[$type->key] !== self::NONE),
            ])->all(),
            'media' => $this->mediaLevel($user),
            'edit' => $this->canEditAnyContent($user),
        ];
    }

    /**
     * Drops the in-memory permissions; call it after a role's permissions change.
     */
    public function flush(): void
    {
        $this->roles = new \WeakMap;
    }

    /**
     * @return array{system: bool, levels: array<string, string>}
     */
    private function role(EcmsUser $user): array
    {
        // No role, no rights: the empty set, without a query.
        if ($user->role_id === null) {
            return self::NO_ACCESS;
        }

        $cached = $this->roles[$user] ?? null;

        if ($cached === null || $cached['role_id'] !== $user->role_id) {
            $cached = $this->roles[$user] = ['role_id' => $user->role_id, 'data' => $this->load($user->role_id)];
        }

        return $cached['data'];
    }

    /**
     * @return array{system: bool, levels: array<string, string>}
     */
    private function load(int $roleId): array
    {
        $role = Role::query()->find($roleId);

        if ($role === null) {
            return self::NO_ACCESS;
        }

        // The system role is allowed everything and has no rows to read.
        return [
            'system' => $role->is_system,
            'levels' => $role->is_system ? [] : $role->permissions()->pluck('level', 'scope')->all(),
        ];
    }
}
