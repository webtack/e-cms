<?php

namespace Webtack\Ecms\Http\Controllers\Public;

use Illuminate\Http\Response;
use Webtack\Ecms\Content\SitemapTree;

/**
 * `GET /sitemap`: the human-readable site map as a tree of links ({@see SitemapTree}), rendered by the theme
 * template `sitemap/page` (hook `sitemap`) with the title "Sitemap". The template gets `items`: a list of
 * `{title, url, children}` (`title` is plain text to be escaped, `url` is relative). `/sitemap/anything` matches no
 * route and ends in the site 404 (`sitemap` is a site path, see `PublicPaths::SITE_PATHS`).
 *
 * @example
 *   GET /sitemap → 200, the sitemap page in the theme shell
 */
class SitemapController extends PublicController
{
    public function __invoke(SitemapTree $tree): Response
    {
        return $this->respond(['sitemap/page'], 'sitemap', ['items' => $tree->items()], __('Sitemap'));
    }
}
