<?php

namespace Webtack\Ecms\Http\Controllers\Public;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Webtack\Ecms\Content\PresentContext;
use Webtack\Ecms\Content\SearchResult;
use Webtack\Ecms\Content\SiteSearch;

/**
 * The site search page, `GET /search?q=…&page=N` ({@see SiteSearch} finds the nodes).
 *
 * The term is `q` trimmed and cut to `content.search.max_length` characters; a `q` that is not a string
 * (`q[]=x`) counts as empty. An empty term shows the form only, a term shorter than `content.search.min_length`
 * shows the form with a hint; neither queries nodes. The page is answered with status 200 in every case, goes
 * through the theme (candidate `search/results`, hook `search`) and carries `X-Robots-Tag: noindex`, which
 * works with every theme. The template variables: `query` (the clean term, may be empty), `tooShort`
 * (a non-empty term below the minimum), `minLength`, and `results` (a paginator of
 * {@see SearchResult} that keeps `q` in its links, or null while there is no query).
 *
 * @example
 *   GET /search                → the form only
 *   GET /search?q=ab           → the form and the "at least 3 characters" hint
 *   GET /search?q=lamp&page=2  → the second page of results; the paginator links keep q=lamp
 */
class SearchController extends PublicController
{
    /** `GET /search`. */
    public function __invoke(Request $request, SiteSearch $search): Response
    {
        $min = (int) config('content.search.min_length');
        $max = (int) config('content.search.max_length');

        $raw = $request->query('q');
        $term = is_string($raw) ? mb_substr(trim($raw), 0, $max) : '';
        // Cutting can leave a trailing space (`"ab cd…"`), which is not part of the term.
        $term = trim($term);
        $tooShort = $term !== '' && mb_strlen($term) < $min;

        $results = null;

        if ($term !== '' && ! $tooShort) {
            $results = $search->search(
                $term,
                app(PresentContext::class)->lang,
                (int) config('content.per_page'),
                max((int) $request->query('page', 1), 1),
            )->appends(['q' => $term]);
        }

        return $this->respond(
            ['search/results'],
            'search',
            ['query' => $term, 'tooShort' => $tooShort, 'minLength' => $min, 'results' => $results],
            __('Search'),
        )->header('X-Robots-Tag', 'noindex');
    }
}
