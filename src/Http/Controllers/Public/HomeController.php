<?php

namespace Webtack\Ecms\Http\Controllers\Public;

use Illuminate\Http\Response;

/** `GET /`: the home page of the site. */
class HomeController extends PublicController
{
    public function __invoke(): Response
    {
        return $this->respond(['home'], 'home');
    }
}
