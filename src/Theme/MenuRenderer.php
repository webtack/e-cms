<?php

namespace Webtack\Ecms\Theme;

use Webtack\Ecms\Content\LocalizedUrl;
use Webtack\Ecms\Content\PresentContext;
use Webtack\Ecms\Menus\MenuTree;
use Webtack\Ecms\Menus\MenuUrl;
use Webtack\Ecms\Models\Menu;

/**
 * Places a menu into a template by its key: `@menu('main')`.
 *
 * The template receives the nested tree of the VISIBLE items in the reading language (a hidden item takes its
 * whole branch with it, see {@see MenuTree::tree()}). A menu that does not exist, or has no visible items,
 * renders `''` and does not break the page.
 *
 * Candidates, most specific first: `menus/menu--{key}`, `menus/menu`. The hook is `menu`. Variables: `$menu`
 * (the model), `$key` and `$items`; an item is `['uuid', 'title', 'description', 'url', 'target', 'css_class',
 * 'image', 'is_external', 'is_active', 'is_trail', 'children']`. `is_active` marks the item that matches the
 * request path and `is_trail` each of its ancestors ({@see MenuActive}); a stored address that starts with `/` is given
 * as an absolute URL (`is_external` stays `false`). An address that is not allowed by {@see MenuUrl} (written around the
 * admin, for example `javascript:`) is dropped from the item, so a template never prints it.
 *
 * @example
 *   app(MenuRenderer::class)->render('main');   // menus/menu--main, else menus/menu
 * @example In a template
 *
 *   @menu('footer')
 */
class MenuRenderer
{
    public function __construct(
        private readonly ThemeManager $themes,
        private readonly MenuTree $tree,
        private readonly PresentContext $context,
    ) {}

    /**
     * The template candidates of a menu, most specific first.
     *
     * @return list<string>
     *
     * @example app(MenuRenderer::class)->candidates('footer'); // ['menus/menu--footer', 'menus/menu']
     */
    public function candidates(string $key): array
    {
        return ["menus/menu--{$key}", 'menus/menu'];
    }

    /**
     * The HTML of the menu with this key, or `''` when there is no such menu or nothing in it is visible.
     */
    public function render(string $key): string
    {
        $menu = Menu::findByKey($key);

        if ($menu === null) {
            return '';
        }

        $items = $this->absolute(MenuActive::mark(
            $this->safe($this->tree->tree($key, $this->context->lang, fallback: $this->context->fallback)),
            request()->getPathInfo(),
        ));

        if ($items === []) {
            return '';
        }

        return $this->themes->render($this->candidates($key), ['menu' => $menu, 'key' => $key, 'items' => $items], 'menu');
    }

    /**
     * Drop the addresses a menu item may not have, in the whole tree.
     *
     * @param  list<array<string, mixed>>  $items
     * @return list<array<string, mixed>>
     */
    private function safe(array $items): array
    {
        return array_map(function (array $item) {
            if ($item['url'] !== null && ! MenuUrl::isValid($item['url'])) {
                $item['url'] = null;
                $item['is_external'] = null;
            }

            $item['children'] = $this->safe($item['children']);

            return $item;
        }, $items);
    }

    /**
     * Turn stored addresses that start with `/` (not `//`) into absolute URLs. Runs after marking and after
     * `is_external` is known, both of which are decided from the stored address.
     *
     * @param  list<array<string, mixed>>  $items
     * @return list<array<string, mixed>>
     */
    private function absolute(array $items): array
    {
        $localized = app(LocalizedUrl::class);

        return array_map(function (array $item) use ($localized) {
            if ($item['url'] !== null && str_starts_with($item['url'], '/') && ! str_starts_with($item['url'], '//')) {
                $item['url'] = url($localized->path($item['url']));
            }

            $item['children'] = $this->absolute($item['children']);

            return $item;
        }, $items);
    }
}
