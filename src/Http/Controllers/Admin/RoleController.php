<?php

namespace Webtack\Ecms\Http\Controllers\Admin;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Webtack\Ecms\Access\PermissionMatrix;
use Webtack\Ecms\Http\Controllers\Controller;
use Webtack\Ecms\Http\Requests\Admin\StoreRoleRequest;
use Webtack\Ecms\Http\Requests\Admin\UpdateRoleRequest;
use Webtack\Ecms\Models\Role;
use Webtack\Ecms\Support\Table\TablePage;
use Webtack\Ecms\Support\Table\TableRequest;

/**
 * Admin pages of the roles (`/admin/roles/**`): list, create, edit with the permission matrix, delete.
 *
 * Every route needs the `roles` permission (`can:roles` in `routes/web.php`). `{role}` is bound by id. The
 * system role `admin` is shown read-only and cannot be changed (403) or deleted (403); a role that has users
 * cannot be deleted. See docs/admin-roles.md.
 *
 * @example
 *   // GET /admin/roles?q=edit&sort=users_count&dir=desc
 */
class RoleController extends Controller
{
    public function __construct(private readonly PermissionMatrix $matrix) {}

    /** The list of roles with the number of their users. */
    public function index(Request $request): Response
    {
        $table = TableRequest::from($request, [
            ['key' => 'name', 'type' => 'text', 'sortable' => true],
            ['key' => 'users_count', 'type' => 'number', 'sortable' => true, 'searchable' => false],
            ['key' => 'is_system', 'type' => 'boolean', 'sortable' => true, 'searchable' => false],
        ]);

        $rows = Role::query()->withCount('users')->get()
            ->map(fn (Role $role) => [
                'id' => $role->id,
                'name' => $role->name,
                'users_count' => $role->users_count,
                'is_system' => $role->is_system,
            ])
            ->sortBy(fn (array $row) => mb_strtolower($row['name']))
            ->values();

        return Inertia::render('Admin/Roles/Index', ['table' => TablePage::fromCollection($rows, $table)]);
    }

    /** The create form with an empty matrix. */
    public function create(): Response
    {
        return Inertia::render('Admin/Roles/Create', [
            'groups' => $this->matrix->groups(),
            'role' => ['name' => '', 'is_system' => false, 'permissions' => $this->matrix->for(new Role)],
        ]);
    }

    /** Creates a role with the submitted permissions. */
    public function store(StoreRoleRequest $request): RedirectResponse
    {
        DB::transaction(function () use ($request) {
            $role = Role::create(['name' => $request->validated('name')]);
            $this->matrix->save($role, $request->validated('permissions') ?? []);
        });

        return to_route('admin.roles.index')->with('success', __('Role created.'));
    }

    /** The edit form; the system role is shown read-only. */
    public function edit(Role $role): Response
    {
        return Inertia::render('Admin/Roles/Edit', [
            'groups' => $this->matrix->groups(),
            'role' => [
                'id' => $role->id,
                'name' => $role->name,
                'is_system' => $role->is_system,
                'permissions' => $this->matrix->for($role),
            ],
        ]);
    }

    /** Updates the name and the permissions; they apply to the role's users from their next request. Stays on the edit form. */
    public function update(UpdateRoleRequest $request, Role $role): RedirectResponse
    {
        DB::transaction(function () use ($request, $role) {
            $role->update(['name' => $request->validated('name')]);
            $this->matrix->save($role, $request->validated('permissions') ?? []);
        });

        return back()->with('success', __('Role updated.'));
    }

    /** Deletes a role that has no users (its permissions go with it); the system role is refused with 403. */
    public function destroy(Role $role): RedirectResponse
    {
        abort_if($role->is_system, 403);

        if ($role->users()->exists()) {
            return back()->with('error', __('Cannot delete a role that has users.'));
        }

        $role->delete();

        return to_route('admin.roles.index')->with('success', __('Role deleted.'));
    }
}
