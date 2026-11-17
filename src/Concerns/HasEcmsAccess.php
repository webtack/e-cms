<?php

namespace Webtack\Ecms\Concerns;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Webtack\Ecms\Contracts\EcmsUser;
use Webtack\Ecms\Files\FileStorage;
use Webtack\Ecms\Models\Role;
use Webtack\Ecms\Models\UserFavoriteType;

/**
 * Gives a host user model what {@see EcmsUser} requires: the role relation,
 * the favorite types and the avatar URL, and casts `is_active` to boolean.
 *
 * `role_id` is null for an account that has no role (for example a host account that existed before the CMS was
 * installed); such a user has no rights in the CMS.
 *
 * The model needs the columns `role_id`, `is_active`, `locale` and `avatar` (added by the package migrations).
 *
 * @example
 *   class User extends Authenticatable implements EcmsUser
 *   {
 *       use HasEcmsAccess;
 *   }
 *
 * @mixin Model
 */
trait HasEcmsAccess
{
    /**
     * Eloquent trait initializer: `role_id` and `is_active` become mass-assignable and `is_active` is cast to
     * boolean, so a host model only needs `use HasEcmsAccess`. `locale` stays non-mass-assignable (set only by
     * the language switcher).
     */
    public function initializeHasEcmsAccess(): void
    {
        // A model that opens mass assignment with `$guarded = [...]` (no `$fillable`) already accepts both columns, and
        // a non-empty `$fillable` would switch it to whitelist mode and block its other attributes.
        if ($this->getFillable() !== [] || $this->getGuarded() === ['*']) {
            $this->mergeFillable(['role_id', 'is_active']);
        }

        $this->mergeCasts(['is_active' => 'boolean']);
    }

    /**
     * The user's only role (a missing role gives null).
     *
     * @return BelongsTo<Role, $this>
     */
    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    /**
     * Types in the user's personal "Favorites" list, in the order they were added.
     *
     * @return HasMany<UserFavoriteType, $this>
     */
    public function favoriteTypes(): HasMany
    {
        return $this->hasMany(UserFavoriteType::class, 'user_id')->orderBy('id');
    }

    /**
     * The public URL of the avatar, or null when the user has none (the UI then shows the initials).
     *
     * @example $user->avatarUrl() // '/storage/0190a1b27c3e7f0a8b1c2d3e4f5a6b7c.jpg'
     */
    public function avatarUrl(): ?string
    {
        return $this->avatar === null ? null : app(FileStorage::class)->urlForValue('avatar', $this->avatar);
    }
}
