<?php

namespace Webtack\Ecms\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates the create form of a role: a unique name and the permission matrix.
 *
 * The matrix is `permissions.system.{key}` (boolean), `permissions.nodes.{type_key}` and `permissions.media`
 * (`none` | `own` | `any`). Keys that do not exist are ignored by `PermissionMatrix::save()`.
 * `UpdateRoleRequest` reuses {@see self::permissionRules()}.
 *
 * @example
 *   // POST /admin/roles  name=Editor&permissions[system][menus]=1&permissions[nodes][news]=own&permissions[media]=any
 */
class StoreRoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('roles');
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255', Rule::unique('roles', 'name')],
            ...self::permissionRules(),
        ];
    }

    /**
     * Rules of the permission matrix.
     *
     * @return array<string, array<int, mixed>>
     */
    public static function permissionRules(): array
    {
        return [
            'permissions' => ['sometimes', 'array'],
            'permissions.system' => ['sometimes', 'array'],
            'permissions.system.*' => ['boolean'],
            'permissions.nodes' => ['sometimes', 'array'],
            'permissions.nodes.*' => ['in:none,own,any'],
            'permissions.media' => ['sometimes', 'in:none,own,any'],
        ];
    }
}
