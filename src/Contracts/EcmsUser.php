<?php

namespace Webtack\Ecms\Contracts;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Webtack\Ecms\Concerns\HasEcmsAccess;
use Webtack\Ecms\Models\Role;
use Webtack\Ecms\Models\UserFavoriteType;

/**
 * A user of the host application who can take part in the CMS: has a role, favorite types and an avatar.
 *
 * Package code type-hints this contract wherever it works with a CMS user. A host user model satisfies it
 * by implementing the interface and using {@see HasEcmsAccess}. An interface cannot
 * declare attributes, so the columns the package reads are documented here:
 *
 * @property int $id
 * @property int|null $role_id Role of the user (`roles.id`); null = no role, so no rights in the CMS.
 * @property bool $is_active Deactivated users cannot log in and their open sessions end.
 * @property string|null $locale Interface language code (null = not chosen yet).
 * @property string|null $avatar `{uuid}.{ext}` of a file of the `avatar` upload profile (null = initials are shown).
 *
 * @example
 *   class User extends Authenticatable implements EcmsUser
 *   {
 *       use HasEcmsAccess;
 *   }
 */
interface EcmsUser extends Authenticatable
{
    /** Owner type of the user's avatar in the media library (`media_relations.owner_type`). */
    public const MEDIA_OWNER = 'user';

    /**
     * The user's only role (null relation result when the user has no role).
     *
     * @return BelongsTo<Role, $this>
     */
    public function role(): BelongsTo;

    /**
     * Types in the user's personal "Favorites" list, in the order they were added.
     *
     * @return HasMany<UserFavoriteType, $this>
     */
    public function favoriteTypes(): HasMany;

    /**
     * The public URL of the avatar, or null when the user has none (the UI then shows the initials).
     *
     * @example $user->avatarUrl() // '/storage/0190a1b27c3e7f0a8b1c2d3e4f5a6b7c.jpg'
     */
    public function avatarUrl(): ?string;
}
