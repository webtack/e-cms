<?php

namespace Webtack\Ecms\Policies;

use Webtack\Ecms\Access\AccessResolver;
use Webtack\Ecms\Contracts\EcmsUser;
use Webtack\Ecms\Models\Role;
use Webtack\Ecms\Support\UserModel;

/**
 * Object-level rules of user management, on top of the `users` permission (`can:users` on the routes).
 *
 * Only an administrator (the system role `admin`) may assign that role or touch a user who has it. Other roles are
 * not ranked against each other. A target without a role counts as a non-system account. See docs/admin-users.md.
 *
 * @example
 *   Gate::authorize('manage', $user);                   // open/edit/deactivate/delete a user
 *   $request->user()->can('assignRole', [UserModel::class(), $role]); // give $role to a user
 */
class UserPolicy
{
    public function __construct(private readonly AccessResolver $access) {}

    /** Whether the actor may open, edit, deactivate or delete the target. */
    public function manage(EcmsUser $actor, EcmsUser $target): bool
    {
        return ! ($target->role?->is_system ?? false) || $this->access->isAdministrator($actor);
    }

    /** Whether the actor may give the role to a user (create or change a role). */
    public function assignRole(EcmsUser $actor, Role $role): bool
    {
        return ! $role->is_system || $this->access->isAdministrator($actor);
    }
}
