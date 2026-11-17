<?php

namespace Webtack\Ecms\Http\Controllers\Admin;

use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Webtack\Ecms\Access\AccessResolver;
use Webtack\Ecms\Access\LastAdminException;
use Webtack\Ecms\Access\LastAdminGuard;
use Webtack\Ecms\Contracts\EcmsUser;
use Webtack\Ecms\Files\FileRelations;
use Webtack\Ecms\Http\Controllers\Controller;
use Webtack\Ecms\Http\Requests\Admin\StoreUserRequest;
use Webtack\Ecms\Http\Requests\Admin\UpdateUserRequest;
use Webtack\Ecms\Models\ContentNode;
use Webtack\Ecms\Models\Role;
use Webtack\Ecms\Support\Table\TablePage;
use Webtack\Ecms\Support\Table\TableRequest;
use Webtack\Ecms\Support\UserModel;

/**
 * Admin pages of the users (`/admin/users/**`): list, create, edit and delete.
 *
 * Every route needs the `users` permission (`can:users` in `routes/web.php`); `{user}` is bound by id. A user
 * created here is active with a verified email and has exactly one role. A user who authored content is only
 * deactivated, never deleted; the last active administrator cannot be deactivated, deleted or moved to another
 * role ({@see LastAdminGuard}); nobody changes their own role or deactivates themselves. See docs/admin-users.md.
 *
 * @example
 *   // GET /admin/users?q=anna&sort=role&dir=asc
 */
class UserController extends Controller
{
    public function __construct(private readonly LastAdminGuard $guard, private readonly FileRelations $fileRelations) {}

    /** The list of users with their role and activity. */
    public function index(Request $request): Response
    {
        $table = TableRequest::from($request, [
            ['key' => 'name', 'type' => 'text', 'sortable' => true],
            ['key' => 'email', 'type' => 'text', 'sortable' => true],
            ['key' => 'role', 'type' => 'text', 'sortable' => true],
            ['key' => 'is_active', 'type' => 'boolean', 'sortable' => true, 'searchable' => false],
        ]);

        $rows = UserModel::query()->with('role:id,name,is_system')->get()
            ->map(fn (EcmsUser $user) => [
                'id' => $user->id,
                'name' => $user->name,
                'avatar' => $user->avatarUrl(),
                'email' => $user->email,
                'role' => $user->role?->name ?? __('No role'),
                'is_active' => $user->is_active,
                'is_self' => $user->is($request->user()),
                'can_manage' => $request->user()->can('manage', $user),
            ])
            ->sortBy(fn (array $row) => mb_strtolower($row['name']))
            ->values();

        return Inertia::render('Admin/Users/Index', ['table' => TablePage::fromCollection($rows, $table)]);
    }

    /** The create form. */
    public function create(Request $request): Response
    {
        return Inertia::render('Admin/Users/Create', ['roles' => $this->roles($request->user())]);
    }

    /** Creates an active user with a verified email. */
    public function store(StoreUserRequest $request): RedirectResponse
    {
        $user = new (UserModel::class())($request->safe()->except(['is_active']));
        $user->is_active = $request->boolean('is_active', true);
        // There is no verification mail: the administrator vouches for the address.
        $user->email_verified_at = now();
        $user->save();

        return to_route('admin.users.index')->with('success', __('User created.'));
    }

    /** The edit form; the password field starts empty. */
    public function edit(Request $request, EcmsUser $user): Response
    {
        // An administrator's account is opened only by administrators.
        Gate::authorize('manage', $user);

        return Inertia::render('Admin/Users/Edit', [
            'roles' => $this->roles($request->user()),
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role_id' => $user->role_id,
                'is_active' => $user->is_active,
                'is_self' => $user->is($request->user()),
            ],
        ]);
    }

    /**
     * Saves name, email, role and activity; a non-empty password replaces the current one. Moving the last active
     * administrator out of the system role or deactivating them is refused (errors under `role_id` / `is_active`). Stays on the edit form.
     */
    public function update(UpdateUserRequest $request, EcmsUser $user): RedirectResponse
    {
        $data = $request->safe()->except(['password', 'is_active']);
        $active = $request->boolean('is_active', $user->is_active);

        if ($request->filled('password')) {
            $data['password'] = $request->validated('password');
        }

        $adminRole = Role::query()->where('is_system', true)->value('id');
        $leavesAdmin = $user->role_id === $adminRole && ((int) $data['role_id'] !== $adminRole || ! $active);

        try {
            $this->guard->run($user, $leavesAdmin, function () use ($user, $data, $active) {
                $user->fill($data);
                $user->is_active = $active;
                $user->save();
            });
        } catch (LastAdminException $e) {
            throw ValidationException::withMessages([
                $active ? 'role_id' : 'is_active' => __($e->getMessage()),
            ]);
        }

        return back()->with('success', __('User updated.'));
    }

    /** Deletes a user who authored no content; an author can only be deactivated. */
    public function destroy(EcmsUser $user): RedirectResponse
    {
        // An administrator's account is deleted only by administrators.
        Gate::authorize('manage', $user);

        if (ContentNode::query()->where('user_id', $user->id)->exists()) {
            return back()->with('error', __('Cannot delete a user who authored content. Deactivate the user instead.'));
        }

        try {
            $this->guard->run($user, true, function () use ($user) {
                $user->delete();
                // The relation has no foreign key to its owner, so it is released here; the file stays in the storage.
                $this->fileRelations->forgetOwner(EcmsUser::MEDIA_OWNER, $user->id);
            });
        } catch (LastAdminException $e) {
            return back()->with('error', __($e->getMessage()));
        } catch (QueryException) {
            // The foreign key of `content_nodes.user_id` is the second guard against a concurrent write.
            return back()->with('error', __('Cannot delete a user who authored content. Deactivate the user instead.'));
        }

        return to_route('admin.users.index')->with('success', __('User deleted.'));
    }

    /**
     * Roles for the select of the form, by name (case-insensitive). The system role is offered only to administrators.
     *
     * @return list<array{id: int, name: string}>
     */
    private function roles(EcmsUser $actor): array
    {
        $administrator = app(AccessResolver::class)->isAdministrator($actor);

        return Role::query()->get(['id', 'name', 'is_system'])
            ->filter(fn (Role $role) => $administrator || ! $role->is_system)
            ->sortBy(fn (Role $role) => mb_strtolower($role->name))
            ->map(fn (Role $role) => $role->only(['id', 'name']))
            ->values()
            ->all();
    }
}
