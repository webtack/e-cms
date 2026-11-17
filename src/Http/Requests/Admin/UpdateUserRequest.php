<?php

namespace Webtack\Ecms\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use Webtack\Ecms\Contracts\EcmsUser;

/**
 * Validates the edit form of a user (the user comes from the `user` route parameter).
 *
 * An empty `password` keeps the current one. A user cannot change their own role or deactivate themselves:
 * the error is reported under `role_id` or `is_active`.
 *
 * @example
 *   // PUT /admin/users/3  name=Anna&email=anna@example.com&password=&role_id=2&is_active=0
 */
class UpdateUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        // The route user may be an administrator, and the submitted role may be `admin`: both are for administrators only.
        return $this->user()->can('users')
            && $this->user()->can('manage', $this->route('user'))
            && StoreUserRequest::mayAssignSubmittedRole($this);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            ...StoreUserRequest::sharedRules(),
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users', 'email')->ignore($this->route('user'))],
            'password' => ['nullable', 'string', 'min:'.StoreUserRequest::MIN_PASSWORD],
        ];
    }

    /**
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [function (Validator $validator) {
            /** @var EcmsUser $target */
            $target = $this->route('user');

            if (! $target->is($this->user()) || $validator->errors()->isNotEmpty()) {
                return;
            }

            if ((int) $this->input('role_id') !== $target->role_id) {
                $validator->errors()->add('role_id', __('You cannot change your own role.'));
            }

            if (! $this->boolean('is_active', $target->is_active)) {
                $validator->errors()->add('is_active', __('You cannot deactivate yourself.'));
            }
        }];
    }
}
