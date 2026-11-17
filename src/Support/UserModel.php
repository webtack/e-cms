<?php

namespace Webtack\Ecms\Support;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Webtack\Ecms\Concerns\HasEcmsAccess;
use Webtack\Ecms\Contracts\EcmsUser;

/**
 * Resolves the host application's user model class.
 *
 * The package never names a concrete user class: it takes the one configured for the default `users`
 * provider (`auth.providers.users.model`). That model must implement {@see EcmsUser}
 * (normally by using {@see HasEcmsAccess}).
 */
final class UserModel
{
    /**
     * The class name of the user model.
     *
     * @return class-string<Model&EcmsUser>
     *
     * @example
     *   $this->belongsTo(UserModel::class(), 'user_id');
     *   UserModel::class()::factory()->create();
     */
    public static function class(): string
    {
        /** @var class-string<Model&EcmsUser> */
        return config('auth.providers.users.model');
    }

    /**
     * A query builder on the user model.
     *
     * @return Builder<Model&EcmsUser>
     *
     * @example UserModel::query()->where('email', $email)->first();
     */
    public static function query(): Builder
    {
        return self::class()::query();
    }
}
