<?php

namespace Webtack\Ecms\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Webtack\Ecms\Files\Rules\FileOfProfile;

/**
 * Validates the "Avatar" form of the profile page: the `uuid` of a file uploaded with the `avatar` profile,
 * or an empty value to remove the avatar. A file of any other profile is rejected.
 *
 * @example
 *   // PUT /admin/profile/avatar  avatar=0190a1b27c3e7f0a8b1c2d3e4f5a6b7c
 *   // PUT /admin/profile/avatar  avatar=            (removes the avatar)
 */
class UpdateProfileAvatarRequest extends FormRequest
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
            'avatar' => ['nullable', 'string', new FileOfProfile('avatar')],
        ];
    }
}
