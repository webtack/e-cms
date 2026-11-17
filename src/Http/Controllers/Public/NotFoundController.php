<?php

namespace Webtack\Ecms\Http\Controllers\Public;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Webtack\Ecms\Content\PublicPaths;

/**
 * The 404 of the public site, rendered by the exception handler when a GET matches no route (three or more
 * segments, a file name and the like). There is deliberately no `Route::fallback`: a GET catch-all route
 * turns every wrong-verb request (a `PUT` to an unknown admin URL, say) from 404 into 405.
 *
 * It answers only a browser GET/HEAD outside the service prefixes (`PublicPaths::reserved()`), so the
 * admin, `/storage/**`, JSON requests and other verbs keep the standard 404. The reserved paths that are
 * pages of the site itself (`PublicPaths::SITE_PATHS`, the search page) are not service prefixes: an address
 * below them, such as `/search/lamp`, gets the theme's 404.
 *
 * @example bootstrap/app.php
 *   $exceptions->render(fn (NotFoundHttpException $e, Request $request) => app(NotFoundController::class)->render($request));
 */
class NotFoundController extends PublicController
{
    /** The theme's 404 for a request that belongs to the public site, otherwise null (standard handling). */
    public function render(Request $request): ?Response
    {
        if (! $request->isMethodSafe() || $request->expectsJson()) {
            return null;
        }

        $segment = strtolower((string) $request->segment(1));

        if (in_array($segment, app(PublicPaths::class)->reserved(), true) && ! in_array($segment, PublicPaths::SITE_PATHS, true)) {
            return null;
        }

        return $this->notFound();
    }
}
