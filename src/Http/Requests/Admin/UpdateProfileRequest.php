<?php

namespace Webtack\Ecms\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates the "Personal data" form of the profile page: the signed-in user's own name and email.
 *
 * Only `name` and `email` are validated, so the controller (which writes `safe()` values only) can never change the
 * role or the activity. The email must be unique among users; the user's own address is not a duplicate.
 *
 * @example
 *   // PUT /admin/profile  name=Anna&email=anna@example.com
 */
class UpdateProfileRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users', 'email')->ignore($this->user()->id)],
        ];
    }
}
