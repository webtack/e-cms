<?php

namespace Webtack\Ecms\Http\Controllers\Public;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Webtack\Ecms\Content\SitemapXml;
use Webtack\Ecms\Http\Controllers\Controller;

/**
 * `GET /sitemap.xml`: the XML sitemap for search engines ({@see SitemapXml}). It is not a themed page: the body is
 * built directly and the addresses are absolute on the host of the request.
 *
 * @example
 *   GET /sitemap.xml → 200 application/xml; charset=utf-8, <urlset> with the home page first
 */
class SitemapXmlController extends Controller
{
    public function __invoke(Request $request, SitemapXml $sitemap): Response
    {
        return response($sitemap->build($request->getSchemeAndHttpHost()), 200, ['Content-Type' => 'application/xml; charset=utf-8']);
    }
}
