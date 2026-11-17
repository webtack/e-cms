<?php

namespace Webtack\Ecms\Http\Controllers\Auth;

use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;
use Webtack\Ecms\Http\Controllers\Controller;
use Webtack\Ecms\Http\Requests\Auth\LoginRequest;

class LoginController extends Controller
{
    /**
     * Show the login page.
     */
    public function show(): Response
    {
        return Inertia::render('Auth/Login');
    }

    /**
     * Authenticate the user and redirect to the intended page.
     */
    public function store(LoginRequest $request): RedirectResponse
    {
        $request->authenticate();

        $request->session()->regenerate();

        return redirect()->intended('/admin/dashboard');
    }
}
