<?php

namespace Webtack\Ecms\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Webtack\Ecms\Models\Role;
use Webtack\Ecms\Support\UserModel;

/**
 * Validates the create form of a user: name, unique email, password and an existing role.
 *
 * The administrator who creates the user sets the password; there is no invitation mail. `UpdateUserRequest`
 * reuses {@see self::sharedRules()}.
 *
 * @example
 *   // POST /admin/users  name=Anna&email=anna@example.com&password=secret-pass&role_id=2&is_active=1
 */
class StoreUserRequest extends FormRequest
{
    public const MIN_PASSWORD = 8;

    public function authorize(): bool
    {
        return $this->user()->can('users') && self::mayAssignSubmittedRole($this);
    }

    /**
     * Whether the actor may give the submitted role (only an administrator gives `admin`). An unknown or non-integer
     * id passes here, so the 403 never masks the "role does not exist" validation error.
     */
    public static function mayAssignSubmittedRole(FormRequest $request): bool
    {
        $id = $request->input('role_id');
        $role = is_int($id) || (is_string($id) && ctype_digit($id)) ? Role::query()->find((int) $id) : null;

        return $role === null || $request->user()->can('assignRole', [UserModel::class(), $role]);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            ...self::sharedRules(),
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users', 'email')],
            'password' => ['required', 'string', 'min:'.self::MIN_PASSWORD],
        ];
    }

    /**
     * Rules of the fields both forms edit.
     *
     * @return array<string, array<int, mixed>>
     */
    public static function sharedRules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'role_id' => ['required', 'integer', Rule::exists('roles', 'id')],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
