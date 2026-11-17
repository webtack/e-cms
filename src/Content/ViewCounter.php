<?php

namespace Webtack\Ecms\Content;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;
use Webtack\Ecms\Models\ContentNode;

/**
 * Counts a view of a public node page (`content_nodes.views`).
 *
 * By default every `GET` with a User-Agent that is not a bot counts, signed-in users included, with no
 * per-visitor limit. Two settings tighten this: `content.views.count_authenticated` = false skips signed-in
 * users, and `content.views.ttl` > 0 counts a visitor (IP + User-Agent, no cookies) once per node per that
 * many seconds; the "already counted" mark is an atomic `Cache::add`, so concurrent requests cannot both
 * pass. With `ttl` = 0 the cache is not used at all. The update is one atomic
 * `UPDATE ... SET views = views + 1` that leaves `updated_at` alone ({@see ContentNode::incrementViews()}).
 *
 * Counting never breaks a page: any failure (cache, database) is logged at `warning` and swallowed. When the
 * cache fails the view is simply not counted, because an undercount is better than an inflated counter.
 *
 * Checks run from the cheapest: enabled, method, authenticated user, User-Agent, dedup key (only when
 * `ttl` > 0), update.
 *
 * @example
 *   // In the controller, after the response is built:
 *   app(ViewCounter::class)->record($request, $node);
 *
 *   // config/content.php (defaults: count everything)
 *   // 'views' => ['enabled' => true, 'ttl' => 0, 'count_authenticated' => true, 'bot_agents' => ['bot', 'curl', ...]]
 *   // Stricter: once per visitor per day, guests only
 *   // CONTENT_VIEWS_TTL=86400  CONTENT_VIEWS_COUNT_AUTHENTICATED=false
 */
class ViewCounter
{
    /**
     * Count the visit when it passes every rule; do nothing otherwise. Never throws.
     */
    public function record(Request $request, ContentNode $node): void
    {
        try {
            if ($this->shouldCount($request, $node)) {
                $node->incrementViews();
            }
        } catch (Throwable $e) {
            Log::warning('The view of a node was not counted.', ['node' => $node->getKey(), 'error' => $e->getMessage()]);
        }
    }

    /** Whether this request is a new human view of the node (claims the dedup key when it is). */
    private function shouldCount(Request $request, ContentNode $node): bool
    {
        if (! config('content.views.enabled')
            || $request->getMethod() !== 'GET'
            || ($request->user() !== null && ! config('content.views.count_authenticated'))
            || $this->isBot((string) $request->userAgent())) {
            return false;
        }

        $ttl = (int) config('content.views.ttl');

        if ($ttl <= 0) {
            return true;
        }

        // `add` stores the key only when it is absent, so exactly one of the concurrent requests wins.
        return Cache::add($this->key($request, $node), 1, $ttl);
    }

    /** An empty User-Agent or one containing a configured substring (case-insensitive) is a bot. */
    private function isBot(string $userAgent): bool
    {
        $userAgent = trim($userAgent);

        if ($userAgent === '') {
            return true;
        }

        foreach ((array) config('content.views.bot_agents') as $needle) {
            if ($needle !== '' && stripos($userAgent, (string) $needle) !== false) {
                return true;
            }
        }

        return false;
    }

    private function key(Request $request, ContentNode $node): string
    {
        return 'node-view:'.$node->getKey().':'.sha1($request->ip().'|'.$request->userAgent());
    }
}
