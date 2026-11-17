<?php

namespace Webtack\Ecms\Content;

use Illuminate\Support\Facades\DB;
use Webtack\Ecms\Models\ContentNode;
use Webtack\Ecms\Models\ContentType;

/**
 * Builds the tree of the web sitemap (`GET /sitemap`) from the type settings ({@see SitemapSettings}).
 *
 * The first item is the home page. Then come the visible pages of every `page` type with `web.nodes` on (one flat
 * list ordered by title), then the tree of `content` types. A `content` type with `web.type` on contributes its own
 * item (public title, linking to `/{type slug}`) whose children are its records (when `web.nodes` is on; ordered by
 * title) followed by its child types. A type with `web.type` off contributes its records and its child types' items
 * at the level of its parent instead, so no enabled content is lost. A type without a slug has no address: it
 * contributes neither its own item nor its records, but its child types are still visited (a `parent_type` cycle
 * is guarded by a visited set; types of a cycle are visited as roots). Titles are read in the reading language,
 * then in the base language, then the slug (nodes) or the name (types). Titles are compared case-insensitively by
 * code point.
 *
 * The number of queries is fixed: the types, the visible nodes of the enabled types, the node titles and the
 * type titles (`section_title`).
 *
 * @example
 *   app(SitemapTree::class)->items();
 *   // [['title' => 'Home', 'url' => '/', 'children' => []],
 *   //  ['title' => 'News', 'url' => '/news', 'children' => [['title' => 'Hello', 'url' => '/news/hello', 'children' => []]]]]
 */
class SitemapTree
{
    public function __construct(
        private readonly SitemapSettings $settings,
        private readonly PresentContext $context,
    ) {}

    /**
     * @return list<array{title: string, url: string, children: list<array<string, mixed>>}>
     */
    public function items(): array
    {
        $types = $this->settings->types();
        $web = $types->map(fn (ContentType $type) => $this->settings->web($type));
        $nodeTypes = $types->filter(fn (ContentType $type, string $key) => $web[$key]['nodes'] && SitemapSettings::hasAddress($type));

        $nodes = $this->nodes($nodeTypes->keys()->all());
        $titles = $this->nodeTitles($nodeTypes->keys()->all());
        $typeTitles = $this->typeTitles($types->where('kind', 'content')->keys()->all());

        $nodeItems = function (ContentType $type) use ($nodes, $titles): array {
            $items = [];

            foreach ($nodes[$type->key] ?? [] as $node) {
                $items[] = [
                    'title' => $titles[$node->id] ?? $node->slug,
                    'url' => SitemapSettings::nodeAddress($type, $node->slug),
                    'children' => [],
                ];
            }

            return $this->sorted($items);
        };

        $items = [['title' => __('Home'), 'url' => '/', 'children' => []]];

        foreach ($types->where('kind', 'page') as $type) {
            if ($nodeTypes->has($type->key)) {
                $items = [...$items, ...$nodeItems($type)];
            }
        }

        // Pages are one flat list ordered by title across the page types.
        $pages = array_slice($items, 1);
        $items = [$items[0], ...$this->sorted($pages)];

        $contents = $types->where('kind', 'content');
        $children = $contents->groupBy(fn (ContentType $type) => $contents->has((string) $type->parent_type) ? $type->parent_type : '');
        $visited = [];

        $walk = function (ContentType $type) use (&$walk, &$visited, $children, $web, $nodeItems, $typeTitles): array {
            $visited[$type->key] = true;
            $below = [];

            foreach ($this->sortedTypes($children->get($type->key, collect()), $typeTitles) as $child) {
                if (! isset($visited[$child->key])) {
                    $below = [...$below, ...$walk($child)];
                }
            }

            if (! SitemapSettings::hasAddress($type)) {
                return $below;
            }

            $records = $web[$type->key]['nodes'] ? $nodeItems($type) : [];

            if (! $web[$type->key]['list']) {
                return [...$records, ...$below];
            }

            return [[
                'title' => $typeTitles[$type->key] ?? $type->name,
                'url' => SitemapSettings::address($type),
                'children' => [...$records, ...$below],
            ]];
        };

        foreach ($this->sortedTypes($children->get('', collect()), $typeTitles) as $root) {
            if (! isset($visited[$root->key])) {
                $items = [...$items, ...$walk($root)];
            }
        }

        // Types of a `parent_type` cycle have no root above them: visit what is left.
        foreach ($this->sortedTypes($contents->reject(fn (ContentType $type) => isset($visited[$type->key])), $typeTitles) as $left) {
            if (! isset($visited[$left->key])) {
                $items = [...$items, ...$walk($left)];
            }
        }

        return $this->localized($items);
    }

    /**
     * The addresses of the tree in the reading language (`/en/news/hello` on an English page).
     *
     * @param  list<array<string, mixed>>  $items
     * @return list<array<string, mixed>>
     */
    private function localized(array $items): array
    {
        $url = app(LocalizedUrl::class);

        return array_map(fn (array $item) => [...$item, 'url' => $url->path($item['url']), 'children' => $this->localized($item['children'])], $items);
    }

    /**
     * The visible nodes of the given types, grouped by type key.
     *
     * @param  list<string>  $typeKeys
     * @return array<string, list<ContentNode>>
     */
    private function nodes(array $typeKeys): array
    {
        if ($typeKeys === []) {
            return [];
        }

        return ContentNode::query()
            ->visible()
            ->whereIn('content_nodes.content_type', $typeKeys)
            ->select(['content_nodes.id', 'content_nodes.content_type', 'content_nodes.slug'])
            ->get()
            ->groupBy('content_type')
            ->map(fn ($group) => $group->all())
            ->all();
    }

    /**
     * The `title` of the visible nodes of the given types: the reading language, then the base language.
     *
     * @param  list<string>  $typeKeys
     * @return array<int, string> node id => title
     */
    private function nodeTitles(array $typeKeys): array
    {
        if ($typeKeys === []) {
            return [];
        }

        $rows = ContentNode::query()
            ->visible()
            ->join('content_values_string as v', 'v.content_node_id', '=', 'content_nodes.id')
            ->whereIn('content_nodes.content_type', $typeKeys)
            ->where('v.entity', SystemFields::OWNER_NODE)
            ->where('v.field_key', 'title')
            ->whereIn('v.lang', array_unique([$this->context->lang, 'default']))
            ->toBase()
            ->get(['v.content_node_id', 'v.lang', 'v.value']);

        return $this->pick($rows->all(), 'content_node_id');
    }

    /**
     * The `section_title` of the given types: the reading language, then the base language.
     *
     * @param  list<string>  $typeKeys
     * @return array<string, string> type key => title
     */
    private function typeTitles(array $typeKeys): array
    {
        if ($typeKeys === []) {
            return [];
        }

        $rows = DB::table('content_values_string')
            ->where('entity', SystemFields::OWNER_TYPE)
            ->whereNull('content_node_id')
            ->whereIn('content_type', $typeKeys)
            ->where('field_key', 'section_title')
            ->whereIn('lang', array_unique([$this->context->lang, 'default']))
            ->get(['content_type', 'lang', 'value']);

        return $this->pick($rows->all(), 'content_type');
    }

    /**
     * One non-empty value per owner: the reading language wins over the base language.
     *
     * @param  list<object>  $rows
     * @return array<int|string, string>
     */
    private function pick(array $rows, string $ownerColumn): array
    {
        $picked = [];

        foreach ([...array_unique([$this->context->lang, 'default'])] as $lang) {
            foreach ($rows as $row) {
                if ($row->lang === $lang && trim((string) $row->value) !== '' && ! isset($picked[$row->{$ownerColumn}])) {
                    $picked[$row->{$ownerColumn}] = (string) $row->value;
                }
            }
        }

        return $picked;
    }

    /**
     * Items ordered by title (case-insensitive, by code point).
     *
     * @param  list<array{title: string, url: string, children: list<array<string, mixed>>}>  $items
     * @return list<array{title: string, url: string, children: list<array<string, mixed>>}>
     */
    private function sorted(array $items): array
    {
        usort($items, fn (array $a, array $b) => strcmp(mb_strtolower($a['title']), mb_strtolower($b['title'])));

        return $items;
    }

    /**
     * Types ordered by public title.
     *
     * @param  iterable<ContentType>  $types
     * @param  array<string, string>  $typeTitles
     * @return list<ContentType>
     */
    private function sortedTypes(iterable $types, array $typeTitles): array
    {
        $list = [...$types];
        usort($list, fn (ContentType $a, ContentType $b) => strcmp(mb_strtolower($typeTitles[$a->key] ?? $a->name), mb_strtolower($typeTitles[$b->key] ?? $b->name)));

        return $list;
    }
}
