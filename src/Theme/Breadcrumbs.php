<?php

namespace Webtack\Ecms\Theme;

use Webtack\Ecms\Content\LocalizedUrl;
use Webtack\Ecms\Models\ContentNode;
use Webtack\Ecms\Models\ContentType;

/**
 * Builds the breadcrumb trail of a public page from the content-type hierarchy, and its `BreadcrumbList` JSON-LD.
 *
 * Trail by page kind ({@see for()}): a record = home, ancestor types, its type, the record; a list = home, ancestor
 * types, the type; a page (`kind = page`) = home, the page; the home page and 404 = empty. Ancestors follow
 * `parent_type` (root first, a visited set guards damaged data) and a type without a slug is skipped. Every item
 * except the current page has an absolute URL; the current page has `url` of `null`. A type item is titled by
 * {@see TypeSection}, never by the administrative name. Menus do not take part.
 *
 * @phpstan-type Crumb array{title: string, url: string|null}
 *
 * @example
 *   app(Breadcrumbs::class)->for($type, $node, 'Hello');
 *   // [['title' => 'Home', 'url' => 'https://example.org/'], ['title' => 'News', 'url' => 'https://example.org/news'],
 *   //  ['title' => 'Hello', 'url' => null]]
 */
class Breadcrumbs
{
    public function __construct(private readonly TypeSection $sections) {}

    /**
     * @param  string|null  $title  The record or page title; the node slug is used when it is empty.
     * @return list<array{title: string, url: string|null}>
     */
    public function for(?ContentType $type, ?ContentNode $node, ?string $title): array
    {
        if ($type === null) {
            return [];
        }

        $localized = app(LocalizedUrl::class);
        // The home address of the base language keeps its trailing slash; a language home is `/en`.
        $trail = [['title' => __('Home'), 'url' => $localized->isAdditional() ? url($localized->path('/')) : rtrim(url('/'), '/').'/']];

        if ($type->kind === 'page') {
            if ($node === null) {
                return [];
            }

            $trail[] = ['title' => $this->nodeTitle($node, $title), 'url' => null];

            return $trail;
        }

        foreach ($this->ancestors($type) as $ancestor) {
            $trail[] = ['title' => $this->sections->title($ancestor), 'url' => url($localized->path('/'.$ancestor->slug))];
        }

        if ($type->slug === null || $type->slug === '') {
            // A type without a public address cannot be the current list or link to its records.
            return $node === null ? [] : [...$trail, ['title' => $this->nodeTitle($node, $title), 'url' => null]];
        }

        if ($node === null) {
            $trail[] = ['title' => $this->sections->title($type), 'url' => null];

            return $trail;
        }

        $trail[] = ['title' => $this->sections->title($type), 'url' => url($localized->path('/'.$type->slug))];
        $trail[] = ['title' => $this->nodeTitle($node, $title), 'url' => null];

        return $trail;
    }

    /**
     * The `BreadcrumbList` JSON for a trail, or `null` when it has fewer than two items. Safe inside a
     * `<script type="application/ld+json">`: `<`, `>` and `&` are hex-escaped, other characters stay readable.
     *
     * @param  list<array{title: string, url: string|null}>  $trail
     */
    public function jsonLd(array $trail): ?string
    {
        if (count($trail) < 2) {
            return null;
        }

        $items = [];

        foreach (array_values($trail) as $i => $crumb) {
            $item = ['@type' => 'ListItem', 'position' => $i + 1, 'name' => $crumb['title']];

            if ($crumb['url'] !== null) {
                $item['item'] = $crumb['url'];
            }

            $items[] = $item;
        }

        return json_encode(
            ['@context' => 'https://schema.org', '@type' => 'BreadcrumbList', 'itemListElement' => $items],
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP,
        ) ?: null;
    }

    private function nodeTitle(ContentNode $node, ?string $title): string
    {
        return $title !== null && $title !== '' ? $title : (string) $node->slug;
    }

    /**
     * Ancestor types with a slug, root first.
     *
     * @return list<ContentType>
     */
    private function ancestors(ContentType $type): array
    {
        $chain = [];
        $seen = [$type->key => true];
        $parent = $type->parent_type;

        while ($parent !== null && ! isset($seen[$parent])) {
            $seen[$parent] = true;
            $ancestor = ContentType::findByKey($parent);

            if ($ancestor === null) {
                break;
            }

            if ($ancestor->slug !== null && $ancestor->slug !== '') {
                $chain[] = $ancestor;
            }

            $parent = $ancestor->parent_type;
        }

        return array_reverse($chain);
    }
}
