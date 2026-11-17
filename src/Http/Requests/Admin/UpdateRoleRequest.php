<?php

namespace Webtack\Ecms\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Webtack\Ecms\Models\Role;

/**
 * Validates the edit form of a role (the role comes from the `role` route parameter). The system role cannot be
 * changed: the request is refused with 403 before validation.
 *
 * @example
 *   // PUT /admin/roles/2  name=Editor&permissions[nodes][news]=any
 */
class UpdateRoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var Role $role */
        $role = $this->route('role');

        return $this->user()->can('roles') && ! $role->is_system;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255', Rule::unique('roles', 'name')->ignore($this->route('role'))],
            ...StoreRoleRequest::permissionRules(),
        ];
    }
}
