<?php

namespace Webtack\Ecms\Http\Controllers\Auth;

use Inertia\Inertia;
use Inertia\Response;
use Webtack\Ecms\Http\Controllers\Controller;

class VerifyEmailNoticeController extends Controller
{
    /**
     * Show the "email not verified" page (target of the `verified` middleware).
     */
    public function __invoke(): Response
    {
        return Inertia::render('Auth/VerifyEmail');
    }
}
