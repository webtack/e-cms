<?php

namespace Webtack\Ecms\Theme;

/**
 * Marks, in a menu tree, the item that matches the requested path and every ancestor of it.
 *
 * Every item (at every depth) gets two booleans: `is_active` for the match and `is_trail` for each ancestor of the
 * match; at most one item is active. Matching is by path only: the query string, the fragment and a trailing slash
 * are ignored. An item matches when its path equals the request path, or is a prefix of it that ends at a segment
 * boundary (`/news` matches `/news/hello`, not `/newsletter`); `/` matches only the home page. The longest matching
 * path wins, the first in tree order on a tie. An item without an address, an external one, `mailto:`, `tel:`, and an
 * address starting with `#` or `?` never match.
 *
 * It reads the STORED address, so call it before addresses are made absolute.
 *
 * @example
 *   $tree = [['url' => '/catalog', 'children' => [['url' => '/catalog/jackets', 'children' => []]]]];
 *   MenuActive::mark($tree, '/catalog/jackets/red');
 *   // catalog: is_active false, is_trail true; jackets: is_active true, is_trail false
 */
final class MenuActive
{
    /**
     * @param  list<array<string, mixed>>  $items  A menu tree whose items have `url` and `children`.
     * @return list<array<string, mixed>> The same tree with `is_active` and `is_trail` on every item.
     */
    public static function mark(array $items, string $requestPath): array
    {
        $order = 0;
        $winner = self::findWinner($items, self::normalise($requestPath), null, $order);
        $order = 0;

        return self::apply($items, $winner, $order);
    }

    /** A path without query, fragment or trailing slash, always starting with `/`. */
    private static function normalise(string $path): string
    {
        return '/'.trim(preg_split('/[?#]/', $path, 2)[0], '/');
    }

    /** The path of an item's stored address when it is a relative one, otherwise null. */
    private static function itemPath(?string $url): ?string
    {
        if ($url === null || ! str_starts_with($url, '/') || str_starts_with($url, '//')) {
            return null;
        }

        return self::normalise($url);
    }

    private static function matches(string $item, string $request): bool
    {
        return $item === $request || ($item !== '/' && str_starts_with($request, $item.'/'));
    }

    /**
     * The best match in the whole tree: the longest path, the first in tree order on a tie. Items are numbered in
     * tree order (parent before its children) so the winner can be found again by {@see apply()}.
     *
     * @param  list<array<string, mixed>>  $items
     * @param  array{path: string, order: int}|null  $best
     * @return array{path: string, order: int}|null
     */
    private static function findWinner(array $items, string $request, ?array $best, int &$order): ?array
    {
        foreach ($items as $item) {
            $path = self::itemPath($item['url'] ?? null);

            if ($path !== null && self::matches($path, $request) && ($best === null || strlen($path) > strlen($best['path']))) {
                $best = ['path' => $path, 'order' => $order];
            }

            $order++;
            $best = self::findWinner($item['children'] ?? [], $request, $best, $order);
        }

        return $best;
    }

    /**
     * Set the flags in one walk, numbering items in the same order as {@see findWinner()}.
     *
     * @param  list<array<string, mixed>>  $items
     * @param  array{path: string, order: int}|null  $winner
     * @return list<array<string, mixed>>
     */
    private static function apply(array $items, ?array $winner, int &$order): array
    {
        foreach ($items as $i => $item) {
            $own = $order++;
            $firstChild = $order;
            $item['children'] = self::apply($item['children'] ?? [], $winner, $order);
            $item['is_active'] = $winner !== null && $own === $winner['order'];
            $item['is_trail'] = $winner !== null && $winner['order'] >= $firstChild && $winner['order'] < $order;
            $items[$i] = $item;
        }

        return $items;
    }
}
