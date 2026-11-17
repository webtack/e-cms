<?php

namespace Webtack\Ecms\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates the "Password" form of the profile page: the current password, the new one and its confirmation.
 *
 * A wrong current password is reported under `current_password`; a short or unconfirmed new password under `password`.
 * The minimum length is shared with the users section ({@see StoreUserRequest::MIN_PASSWORD}).
 *
 * @example
 *   // PUT /admin/profile/password  current_password=old-pass&password=new-secret&password_confirmation=new-secret
 */
class UpdateProfilePasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'current_password' => ['required', 'string', 'current_password'],
            'password' => ['required', 'string', 'min:'.StoreUserRequest::MIN_PASSWORD, 'confirmed'],
        ];
    }
}
