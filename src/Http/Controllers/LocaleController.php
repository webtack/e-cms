<?php

namespace Webtack\Ecms\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Validation\Rule;

class LocaleController extends Controller
{
    /**
     * Switch the interface language (`POST /locale`).
     *
     * An authenticated user's choice is saved in `users.locale`; the `locale`
     * cookie is always set so the choice survives logout on the login page.
     */
    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'locale' => ['required', 'string', Rule::in(array_keys((array) config('ecms.locales')))],
        ]);

        $locale = $validated['locale'];

        if ($user = $request->user()) {
            $user->forceFill(['locale' => $locale])->save();
        }

        Cookie::queue(Cookie::forever('locale', $locale));

        return back();
    }
}
