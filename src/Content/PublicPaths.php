<?php

namespace Webtack\Ecms\Content;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Webtack\Ecms\Models\AppLanguage;
use Webtack\Ecms\Models\ContentNode;
use Webtack\Ecms\Models\ContentType;

/**
 * The first URL segment shared by page slugs, `content` type slugs and the reserved paths of the site,
 * and the addresses below it: a type slug may have several segments (`common/institutions`), so a type
 * list and a node address (`type slug` + `/` + `node slug`) can mean the same URL.
 *
 * Reserved paths come from `config('content.reserved_paths')` plus the first segment of {@see self::ASSETS_PATH} and are compared with the first segment of
 * a type slug. Only nodes of kind `page` and types of kind `content` stand on the first segment, so slugs
 * of `content` and `block` nodes are never checked against it; instead a `content` node address must not
 * equal a type slug (the list would win and hide the node). All comparisons are case-insensitive. A
 * conflict is returned as a reason (`reserved`, `type`, `page`, `node`, `language`), which the form rule and the models
 * turn into a message.
 *
 * The codes of the content languages (the saved base code and every additional language, active or not) are
 * part of the same first-segment namespace, because an additional language is addressed as `/{code}/...`:
 * no page slug or type first segment may equal one ({@see self::languageCodes()}), and no language code may equal
 * a reserved path, a page slug or a type first segment ({@see self::languageCodeConflict()}). Unlike the service
 * paths of {@see self::reserved()} they are never answered by the standard 404.
 *
 * @example
 *   $paths = app(PublicPaths::class);
 *   $paths->pageSlugConflict('admin');       // "reserved"
 *   $paths->pageSlugConflict('news');        // "type" when a content type has the slug "news"
 *   $paths->typeSlugConflict('about-us');    // "page" when a page has the slug "about-us"
 *   $paths->typeSlugConflict('admin/reports'); // "reserved": the first segment is reserved
 *   $paths->typeSlugConflict('common/work-plan'); // "node" when the type "common" has a node "work-plan"
 *   $paths->nodeAddressConflict($common, 'institutions'); // true when a type has the slug "common/institutions"
 *   $paths->pageSlugConflict('en');          // "language" when `en` is a content language
 *   $paths->languageCodeConflict('faq');     // "page" when a page has the slug "faq"
 */
class PublicPaths
{
    /**
     * Reserved first segments that are pages of the site itself, not service prefixes: the site 404 answers the
     * addresses below them (`/search/lamp`), while the standard 404 stays for `/admin/...` and the like.
     */
    public const SITE_PATHS = ['search', 'sitemap'];

    /**
     * The directory the package assets are published to (`public/vendor/ecms`). Its first segment is reserved in
     * addition to `content.reserved_paths`, because the directory exists only in the host's `public/`.
     */
    public const ASSETS_PATH = 'vendor/ecms';

    public const REASON_RESERVED = 'reserved';

    public const REASON_TYPE = 'type';

    public const REASON_PAGE = 'page';

    public const REASON_NODE = 'node';

    public const REASON_LANGUAGE = 'language';

    public const REASON_ROUTE = 'route';

    /**
     * Error message of a conflict reason (a translation key, so the model and the form rule say the same).
     *
     * @example PublicPaths::message(PublicPaths::REASON_RESERVED); // "This slug is reserved."
     */
    public static function message(string $reason): string
    {
        return match ($reason) {
            self::REASON_RESERVED => 'This slug is reserved.',
            self::REASON_TYPE => 'This slug is already used by a content type.',
            self::REASON_PAGE => 'This slug is already used by a page.',
            self::REASON_NODE => 'This slug is already the address of a node.',
            self::REASON_LANGUAGE => 'This slug is the code of a content language.',
        };
    }

    /**
     * Error message of a conflict of a language code (a translation key).
     *
     * @param  self::REASON_RESERVED|self::REASON_PAGE|self::REASON_TYPE|self::REASON_ROUTE  $reason
     *
     * @example PublicPaths::languageMessage(PublicPaths::REASON_PAGE); // "This code is already the slug of a page."
     */
    public static function languageMessage(string $reason): string
    {
        return match ($reason) {
            self::REASON_RESERVED => 'This code is a reserved path of the site.',
            self::REASON_PAGE => 'This code is already the slug of a page.',
            self::REASON_TYPE => 'This code is already the first segment of a content type slug.',
            self::REASON_ROUTE => 'This code is already the first segment of a route of the application.',
        };
    }

    /**
     * The codes of the content languages in lower case: the saved base code and every additional language
     * (active or not), which are reserved as first segments of public addresses.
     *
     * @return list<string>
     *
     * @example app(PublicPaths::class)->languageCodes(); // ['uk', 'en']
     */
    public function languageCodes(): array
    {
        $codes = AppLanguage::query()->pluck('code')->all();

        if (($base = app(ContentLanguages::class)->savedBaseLang()) !== null) {
            $codes[] = $base;
        }

        return array_values(array_unique(array_map('strtolower', $codes)));
    }

    /**
     * Why a language code cannot be used: it is a reserved path, the slug of a page, the first segment of the
     * slug of a `content` type, or the first segment of a route of the host application (`/en/contact`): the
     * language prefix is stripped before routing, so such a route would stop working.
     *
     * @return self::REASON_RESERVED|self::REASON_PAGE|self::REASON_TYPE|self::REASON_ROUTE|null
     *
     * @example
     *   $paths->languageCodeConflict('search'); // "reserved"
     *   $paths->languageCodeConflict('en');     // "route" when the host has the route /en/contact
     */
    public function languageCodeConflict(string $code): ?string
    {
        $code = strtolower($code);

        if (in_array($code, $this->reserved(), true)) {
            return self::REASON_RESERVED;
        }

        if ($this->pageSlugExists($code)) {
            return self::REASON_PAGE;
        }

        $like = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $code);

        $typeExists = ContentType::query()->ofKind('content')
            ->where(fn ($query) => $query->whereRaw('LOWER(slug) = ?', [$code])->orWhereRaw('LOWER(slug) LIKE ?', [$like.'/%']))
            ->exists();

        if ($typeExists) {
            return self::REASON_TYPE;
        }

        return in_array($code, $this->hostRouteSegments(), true) ? self::REASON_ROUTE : null;
    }

    /**
     * Lower-case literal first segments of the routes that do not belong to the package (the host's own routes);
     * a parameter such as `{slug}` is not a literal and is skipped.
     *
     * @return list<string>
     */
    private function hostRouteSegments(): array
    {
        $segments = [];

        foreach (Route::getRoutes()->getRoutes() as $route) {
            if (str_starts_with(ltrim($route->getActionName(), '\\'), 'Webtack\\Ecms\\')) {
                continue;
            }

            $first = explode('/', $route->uri())[0];

            if ($first !== '' && ! str_contains($first, '{')) {
                $segments[] = strtolower($first);
            }
        }

        return array_values(array_unique($segments));
    }

    /**
     * Reserved first segments in lower case.
     *
     * @return list<string>
     *
     * @example app(PublicPaths::class)->reserved(); // ['admin', 'login', ...]
     */
    public function reserved(): array
    {
        return array_values(array_unique(array_map('strtolower', [
            ...(array) config('content.reserved_paths'),
            explode('/', self::ASSETS_PATH)[0],
        ])));
    }

    /**
     * Why a page cannot take the slug: it is a reserved path or the slug of a `content` type.
     *
     * @return self::REASON_RESERVED|self::REASON_LANGUAGE|self::REASON_TYPE|null
     */
    public function pageSlugConflict(string $slug): ?string
    {
        $slug = strtolower($slug);

        if (in_array($slug, $this->reserved(), true)) {
            return self::REASON_RESERVED;
        }

        if (in_array($slug, $this->languageCodes(), true)) {
            return self::REASON_LANGUAGE;
        }

        return $this->typeSlugExists($slug) ? self::REASON_TYPE : null;
    }

    /**
     * Why a `content` type cannot take the slug: its first segment is a reserved path, it equals the slug of a
     * page, it equals the address of an existing node, or (for an existing `$type`) it would make the address
     * of one of the type's own nodes equal to the slug of another type.
     *
     * @param  ContentType|null  $type  The type being changed; null for a type being created.
     * @return self::REASON_RESERVED|self::REASON_LANGUAGE|self::REASON_PAGE|self::REASON_NODE|null
     *
     * @example
     *   $paths->typeSlugConflict('admin/reports');        // "reserved": only the first segment is compared
     *   $paths->typeSlugConflict('news/admin');           // null
     *   $paths->typeSlugConflict('common/work-plan');     // "node" when type "common" has the node "work-plan"
     *   $paths->typeSlugConflict('common', $withNodes);   // "node" when $withNodes has "work-plan" and a type "common/work-plan" exists
     */
    public function typeSlugConflict(string $slug, ?ContentType $type = null): ?string
    {
        $slug = strtolower($slug);

        if (in_array(explode('/', $slug)[0], $this->reserved(), true)) {
            return self::REASON_RESERVED;
        }

        if (in_array(explode('/', $slug)[0], $this->languageCodes(), true)) {
            return self::REASON_LANGUAGE;
        }

        if ($this->pageSlugExists($slug)) {
            return self::REASON_PAGE;
        }

        if ($this->nodeAddressExists($slug) || ($type !== null && $this->ownNodesHideTypes($slug, $type))) {
            return self::REASON_NODE;
        }

        return null;
    }

    /**
     * Whether the address of a `content` node (its type's slug, `/`, the node slug) equals the slug of a type,
     * so the list of that type would hide the node. Types without a slug have no address.
     *
     * @example
     *   $paths->nodeAddressConflict($common, 'institutions'); // true when a type has the slug "common/institutions"
     */
    public function nodeAddressConflict(ContentType $type, string $nodeSlug): bool
    {
        if ($type->slug === null || $type->slug === '') {
            return false;
        }

        return $this->typeSlugExists(strtolower($type->slug.'/'.$nodeSlug));
    }

    /**
     * Whether a page cannot use the slug (used by the slug generator).
     */
    public function isTakenForPage(string $slug): bool
    {
        return $this->pageSlugConflict($slug) !== null;
    }

    private function typeSlugExists(string $slug): bool
    {
        return ContentType::query()->ofKind('content')->whereRaw('LOWER(slug) = ?', [$slug])->exists();
    }

    private function pageSlugExists(string $slug): bool
    {
        return ContentNode::query()
            ->whereRaw('LOWER(slug) = ?', [$slug])
            ->whereIn('content_type', ContentType::query()->ofKind('page')->select('key'))
            ->exists();
    }

    /** Whether a node already stands at the address `$slug` (the type with the parent slug has a node with the last segment). */
    private function nodeAddressExists(string $slug): bool
    {
        $position = strrpos($slug, '/');

        if ($position === false) {
            return false;
        }

        return ContentNode::query()
            ->whereRaw('LOWER(slug) = ?', [substr($slug, $position + 1)])
            ->whereIn('content_type', ContentType::query()
                ->ofKind('content')
                ->whereRaw('LOWER(slug) = ?', [substr($slug, 0, $position)])
                ->select('key'))
            ->exists();
    }

    /**
     * Whether a type one segment deeper than `$slug` (`$slug/x`, no further `/`) has the slug of one of the
     * nodes of `$type`, i.e. the list of that type would hide the node once `$type` takes `$slug`.
     */
    private function ownNodesHideTypes(string $slug, ContentType $type): bool
    {
        $deeper = ContentType::query()
            ->ofKind('content')
            ->whereRaw('LOWER(slug) LIKE ?', [str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $slug).'/%'])
            ->pluck('slug')
            ->map(fn (string $other) => substr(strtolower($other), strlen($slug) + 1))
            ->reject(fn (string $tail) => str_contains($tail, '/'))
            ->all();

        if ($deeper === []) {
            return false;
        }

        return ContentNode::query()
            ->where('content_type', $type->key)
            ->whereIn(DB::raw('LOWER(slug)'), $deeper)
            ->exists();
    }
}
