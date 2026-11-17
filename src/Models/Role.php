<?php

namespace Webtack\Ecms\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Webtack\Ecms\Contracts\EcmsUser;
use Webtack\Ecms\Database\Factories\RoleFactory;
use Webtack\Ecms\Support\UserModel;

/**
 * A role: a named set of permissions; every user has exactly one.
 *
 * The system role `admin` (`is_system`) has no permission rows: the access resolver treats it as allowed
 * everything, so new types are available to it automatically. See docs/access-control.md.
 *
 * @property int $id
 * @property string $name Unique.
 * @property bool $is_system
 *
 * @example
 *   Role::admin(); // the system role
 */
#[UseFactory(RoleFactory::class)]
#[Fillable(['name'])]
class Role extends Model
{
    /** @use HasFactory<RoleFactory> */
    use HasFactory;

    /** Name of the system role. */
    public const ADMIN = 'admin';

    protected function casts(): array
    {
        return ['is_system' => 'boolean'];
    }

    /**
     * The system role, created by the migration (created here too when missing, e.g. a database without it).
     */
    public static function admin(): self
    {
        $role = static::query()->where('name', self::ADMIN)->first();

        if ($role === null) {
            $role = new static(['name' => self::ADMIN]);
            $role->is_system = true;
            $role->save();
        }

        return $role;
    }

    /**
     * @return HasMany<RolePermission, $this>
     */
    public function permissions(): HasMany
    {
        return $this->hasMany(RolePermission::class);
    }

    /**
     * @return HasMany<EcmsUser, $this>
     */
    public function users(): HasMany
    {
        return $this->hasMany(UserModel::class());
    }
}
