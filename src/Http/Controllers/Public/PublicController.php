<?php

namespace Webtack\Ecms\Http\Controllers\Public;

use Illuminate\Http\Response;
use Webtack\Ecms\Http\Controllers\Controller;
use Webtack\Ecms\Theme\PublicData;
use Webtack\Ecms\Theme\ThemeManager;

/**
 * Base of the public site controllers: every response goes through {@see ThemeManager::render()},
 * never straight to a view, so the candidate cascade and the hooks apply everywhere.
 */
abstract class PublicController extends Controller
{
    public function __construct(
        protected readonly ThemeManager $themes,
        protected readonly PublicData $data,
    ) {}

    /**
     * @param  list<string>  $candidates  Template names, most specific first.
     * @param  array<string, mixed>  $extra  Route-specific template variables.
     */
    protected function respond(array $candidates, string $hook, array $extra = [], ?string $recordTitle = null, int $status = 200): Response
    {
        return response($this->themes->render($candidates, $this->data->make($extra, $recordTitle), $hook), $status);
    }

    /** The site 404 page in the theme, with status 404. */
    protected function notFound(): Response
    {
        // A missing page is neither canonical nor an alternate of anything.
        return $this->respond(['errors/404'], 'errors.404', ['alternates' => [], 'canonical' => null], status: 404);
    }
}
