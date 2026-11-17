<?php

namespace Webtack\Ecms\Http\Controllers\Admin\Ui;

use Inertia\Inertia;
use Inertia\Response;
use Webtack\Ecms\Http\Controllers\Controller;

/**
 * Demo page of the `FileUpload` widget for developers and e2e tests (not in the navigation, 404 in production).
 *
 * It shows one widget per starting profile (`image`, `document`). Files are really stored; the page
 * has no form, so nothing uses them; they stay in the media library until deleted by hand.
 */
class FileUploadDemoController extends Controller
{
    public function __invoke(): Response
    {
        abort_if(app()->isProduction(), 404);

        return Inertia::render('Admin/Ui/FileUploadDemo');
    }
}
