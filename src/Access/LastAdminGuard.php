<?php

namespace Webtack\Ecms\Access;

use Illuminate\Support\Facades\DB;
use Webtack\Ecms\Contracts\EcmsUser;
use Webtack\Ecms\Models\Role;
use Webtack\Ecms\Support\UserModel;

/**
 * Keeps at least one active user of the system role: the last one cannot be deactivated, deleted or moved to
 * another role.
 *
 * The check and the change must run in one transaction: {@see self::run()} locks the rows of the active
 * administrators (`lockForUpdate`), so two concurrent requests cannot both remove "the other" administrator.
 *
 * @example
 *   $guard->run($user, deactivates: true, change: fn () => $user->update(['is_active' => false]));
 *   // throws LastAdminException when $user is the only active administrator
 */
class LastAdminGuard
{
    /**
     * Runs `$change` in a transaction after checking that it leaves an active administrator.
     *
     * @template T
     *
     * @param  bool  $deactivates  The change deactivates, deletes or moves the user to another role.
     * @param  callable(): T  $change
     * @return T
     *
     * @throws LastAdminException
     */
    public function run(EcmsUser $user, bool $deactivates, callable $change): mixed
    {
        return DB::transaction(function () use ($user, $deactivates, $change) {
            if ($deactivates) {
                $adminRole = Role::query()->where('is_system', true)->value('id');

                // Lock every active administrator so the count below cannot change under a concurrent request.
                $active = UserModel::query()->where('role_id', $adminRole)->where('is_active', true)->lockForUpdate()->pluck('id');

                if ($active->contains($user->id) && $active->count() === 1) {
                    throw new LastAdminException;
                }
            }

            return $change();
        });
    }
}
