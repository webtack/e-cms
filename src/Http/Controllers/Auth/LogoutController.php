<?php

namespace Webtack\Ecms\Http\Controllers\Auth;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Webtack\Ecms\Http\Controllers\Controller;

class LogoutController extends Controller
{
    /**
     * Log the user out and invalidate the session.
     */
    public function __invoke(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/login');
    }
}
