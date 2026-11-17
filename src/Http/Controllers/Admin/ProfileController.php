<?php

namespace Webtack\Ecms\Http\Controllers\Admin;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Webtack\Ecms\Contracts\EcmsUser;
use Webtack\Ecms\Files\FileRelations;
use Webtack\Ecms\Http\Controllers\Controller;
use Webtack\Ecms\Http\Requests\Admin\UpdateProfileAvatarRequest;
use Webtack\Ecms\Http\Requests\Admin\UpdateProfilePasswordRequest;
use Webtack\Ecms\Http\Requests\Admin\UpdateProfileRequest;
use Webtack\Ecms\Models\File;

/**
 * Self-service profile of the signed-in user. Open to every authenticated, verified user (no `can:` gate).
 *
 * Each write has its own endpoint and FormRequest, so role and activity cannot be reached from here.
 */
class ProfileController extends Controller
{
    /**
     * The profile page: the user's name, email, avatar URL and (read-only) role.
     */
    public function show(Request $request): Response
    {
        $user = $request->user()->loadMissing('role:id,name');

        return Inertia::render('Admin/Profile', [
            'profile' => [
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role?->name,
                'avatar' => $user->avatarUrl(),
            ],
        ]);
    }

    /**
     * Saves the name and email. `email_verified_at` is left as it is: the project sends no verification mail.
     */
    public function update(UpdateProfileRequest $request): RedirectResponse
    {
        $request->user()->update($request->safe()->only(['name', 'email']));

        return back()->with('success', __('Profile updated.'));
    }

    /**
     * Replaces the password (hashed by the model cast). Other sessions of the user end by `AuthenticateSession`; this one stays signed in.
     */
    public function updatePassword(UpdateProfilePasswordRequest $request): RedirectResponse
    {
        $request->user()->update(['password' => $request->validated('password')]);

        return back()->with('success', __('Password changed.'));
    }

    /**
     * Sets, replaces or removes the avatar. The stored `{uuid}.{ext}` is built from the `media` row, never taken
     * from the input; the column and the media library relation change in one transaction.
     */
    public function updateAvatar(UpdateProfileAvatarRequest $request, FileRelations $relations): RedirectResponse
    {
        $user = $request->user();
        $uuid = $request->validated('avatar');
        $file = $uuid === null || $uuid === '' ? null : File::findByUuid($uuid);

        DB::transaction(function () use ($user, $file, $relations) {
            $user->forceFill(['avatar' => $file === null ? null : $file->uuid.'.'.pathinfo($file->path, PATHINFO_EXTENSION)])->save();

            $relations->sync(EcmsUser::MEDIA_OWNER, $user->id, 'avatar', config('content.default_lang'), $file === null ? [] : [$file->uuid]);
        });

        return back()->with('success', __($file === null ? 'Avatar removed.' : 'Avatar saved.'));
    }
}
