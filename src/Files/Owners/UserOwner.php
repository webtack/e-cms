<?php

namespace Webtack\Ecms\Files\Owners;

use Illuminate\Support\Facades\DB;

/**
 * Owner type `user`: a file used as the avatar of a user. The label is the user name; the link leads to the
 * edit form of the user (guarded by the `users` right on its own).
 *
 * @example
 *   (new UserOwner)->describe([3]);
 *   // [3 => ['label' => 'Oksana Melnyk', 'url' => '/admin/users/3/edit']]
 */
class UserOwner implements MediaOwner
{
    public function describe(array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        $described = [];

        foreach (DB::table('users')->whereIn('id', $ids)->get(['id', 'name']) as $user) {
            $described[$user->id] = [
                'label' => $user->name,
                'url' => route('admin.users.edit', $user->id, false),
            ];
        }

        return $described;
    }
}
