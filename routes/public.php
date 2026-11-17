<?php

/*
|--------------------------------------------------------------------------
| Public site routes of the package
|--------------------------------------------------------------------------
|
| Loaded by Webtack\Ecms\EcmsServiceProvider inside the `web` middleware group, only when `ecms.public_site` is
| on, and after every other route of the application (the provider loads this file once the application has
| booted), so a route of the host always wins over a page of the same address.
|
*/

use Illuminate\Support\Facades\Route;
use Webtack\Ecms\Content\PublicPaths;
use Webtack\Ecms\Http\Controllers\Public\HomeController;
use Webtack\Ecms\Http\Controllers\Public\PublicNodeController;
use Webtack\Ecms\Http\Controllers\Public\SearchController;
use Webtack\Ecms\Http\Controllers\Public\SitemapController;
use Webtack\Ecms\Http\Controllers\Public\SitemapXmlController;
use Webtack\Ecms\Models\ContentNode;

// The public site comes last, so every other route (the package's service routes and the host's) wins over the slugs below.
// `/sitemap` (the web sitemap) and `/sitemap.xml` (the XML one) are registered the same way. `/search` is the site search page: its first segment is reserved (so no page or type can take it), and it is
// registered before `/{path}`, which never matches it. `/search/anything` matches no route and ends in the site 404.
// The address is one parameter of one or more slash-separated segments (a page, a type list whose slug may
// have several segments, or a node of such a type; see `PublicNodeController::show()`). Every segment must
// look like a slug (legacy format included) and the first one must not be a reserved path (`content.reserved_paths`), so
// `/favicon.ico` and the like match nothing and get the 404 of the site (see `NotFoundController`),
// while `GET /logout` keeps its 405. An empty segment (`/news//hello`) does not match either. Blocks have no address.
// A segment is wider than the strict slug format: it also lets the legacy node slugs through (`a---b`, `jam-session-`,
// `etno_wave`, see `ContentNode::LEGACY_SLUG_PATTERN`). Lookups are exact, so unknown segments still end in the 404.
$slug = substr(ContentNode::LEGACY_SLUG_PATTERN, 2, -2);
$reserved = implode('|', array_map(fn (string $path) => preg_quote($path, '#'), app(PublicPaths::class)->reserved()));
$path = "(?!(?:{$reserved})(?:/|$)){$slug}(?:/{$slug})*";

Route::get('/', HomeController::class)->name('public.home');
Route::get('/search', SearchController::class)->middleware('throttle:search')->name('public.search');
Route::get('/sitemap', SitemapController::class)->name('public.sitemap');
Route::get('/sitemap.xml', SitemapXmlController::class)->name('public.sitemap.xml');
Route::get('/{path}', [PublicNodeController::class, 'show'])->where('path', $path)->name('public.show');
