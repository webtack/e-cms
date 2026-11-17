<?php

namespace Webtack\Ecms\Content;

use Illuminate\Support\Collection;
use Webtack\Ecms\Models\ContentType;

/**
 * Reads the sitemap settings (`settings.sitemap`) of all `content` and `page` types once, so the XML sitemap and the
 * web sitemap use the same reading and cannot disagree about what a setting means.
 *
 * One query loads every type of those kinds (ordered by key); the settings are decoded in memory. A type without
 * `settings.sitemap`, or with every checkbox off, is in neither map. A `content` type without a slug has no public
 * address: it is still returned by {@see types()} (the web tree walks through it to reach its children) but never
 * by {@see xml()} and its lists and records carry no address. See `docs/type-sitemap.md`.
 *
 * @example
 *   $settings = app(SitemapSettings::class);
 *   $settings->xml();           // [['type' => $news, 'list' => true, 'nodes' => true, 'changefreq' => 'weekly', 'priority' => '0.5'], ...]
 *   $settings->web();           // [['type' => $news, 'list' => true, 'nodes' => false], ...]
 *   SitemapSettings::address($news); // "/news"
 */
class SitemapSettings
{
    /** @var Collection<string, ContentType>|null */
    private ?Collection $types = null;

    /**
     * Every `content` and `page` type, keyed by type key and ordered by key.
     *
     * @return Collection<string, ContentType>
     */
    public function types(): Collection
    {
        return $this->types ??= ContentType::query()
            ->whereIn('kind', ['content', 'page'])
            ->orderBy('key')
            ->get()
            ->keyBy('key');
    }

    /**
     * Types that have XML sitemap checkboxes on and a public address, ordered by key.
     *
     * `list` is the list page `/{type slug}` (kind `content` only), `nodes` the visible records (or pages).
     *
     * @return list<array{type: ContentType, list: bool, nodes: bool, changefreq: string, priority: string}>
     */
    public function xml(): array
    {
        $result = [];

        foreach ($this->types() as $type) {
            $xml = $type->settings['sitemap']['xml'] ?? [];
            $list = $type->kind === 'content' && ($xml['type'] ?? false) === true;
            $nodes = ($xml['nodes'] ?? false) === true;

            if (! ($list || $nodes) || ! self::hasAddress($type)) {
                continue;
            }

            $result[] = [
                'type' => $type,
                'list' => $list,
                'nodes' => $nodes,
                'changefreq' => $xml['changefreq'] ?? 'weekly',
                'priority' => $xml['priority'] ?? '0.5',
            ];
        }

        return $result;
    }

    /**
     * The web sitemap flags of a type: `list` (its own item) and `nodes` (its records, or pages).
     *
     * @return array{list: bool, nodes: bool}
     */
    public function web(ContentType $type): array
    {
        $web = $type->settings['sitemap']['web'] ?? [];

        return [
            'list' => $type->kind === 'content' && ($web['type'] ?? false) === true,
            'nodes' => ($web['nodes'] ?? false) === true,
        ];
    }

    /** Whether the type has a public address: a page type stands on its pages' slugs, a content type needs its own slug. */
    public static function hasAddress(ContentType $type): bool
    {
        return $type->kind === 'page' || ($type->slug !== null && $type->slug !== '');
    }

    /**
     * The relative address of a content type's list page.
     *
     * @example SitemapSettings::address($news); // "/news"
     */
    public static function address(ContentType $type): string
    {
        return '/'.$type->slug;
    }

    /**
     * The relative address of a node: `/{slug}` for a page, `/{type slug}/{slug}` for a record.
     *
     * @example SitemapSettings::nodeAddress($news, 'hello'); // "/news/hello"
     */
    public static function nodeAddress(ContentType $type, string $nodeSlug): string
    {
        return $type->kind === 'page' ? '/'.$nodeSlug : '/'.$type->slug.'/'.$nodeSlug;
    }
}
