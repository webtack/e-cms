<?php

namespace Webtack\Ecms\Menus;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Webtack\Ecms\Content\ContentLanguages;
use Webtack\Ecms\Models\Menu;
use Webtack\Ecms\Models\MenuItem;

/**
 * Builds the nested tree of a menu in memory from two queries (items, then translations of the
 * language), whatever the depth.
 *
 * {@see self::tree()} is for the site: only visible items, a hidden item hides its whole branch.
 * {@see self::adminTree()} is for the admin: every item in tree order with its computed status.
 * Titles and descriptions come in the asked language without fallback: a missing translation is `null`.
 * With `$fallback` (the public site) a blank title or description is replaced by the `default` one, each
 * independently; the address, order, nesting and visibility never depend on the language.
 *
 * @example
 *   app(MenuTree::class)->tree('main', 'en', fallback: true); // an item without an English title shows its default title
 *   app(MenuTree::class)->tree('main', 'default');
 *   // [['uuid' => '…', 'title' => 'Catalog', 'description' => null, 'url' => '/catalog', 'target' => '_self',
 *   //   'css_class' => null, 'image' => null, 'is_external' => false, 'children' => [...]]]
 */
class MenuTree
{
    public function __construct(private readonly ContentLanguages $languages) {}

    /**
     * The visible items of a menu as a nested tree, siblings ordered by weight then id.
     *
     * @param  CarbonInterface|null  $now  The moment visibility is judged at; defaults to the current time.
     * @param  bool  $fallback  Replace a blank title or description by the `default` one (public site only).
     * @return list<array{uuid: string, title: string|null, description: string|null, url: string|null, target: string, css_class: string|null, image: string|null, is_external: bool|null, children: list<array<string, mixed>>}>
     *
     * @throws ModelNotFoundException When the menu does not exist.
     */
    public function tree(string $menuKey, ?string $lang = 'default', ?CarbonInterface $now = null, bool $fallback = false): array
    {
        $menu = Menu::query()->where('key', $menuKey)->firstOrFail();
        $lang = $this->languages->assertReadable($lang ?? 'default');
        $now ??= now();

        [$byParent, $texts] = $this->load($menu, $lang, $fallback);

        $build = function (string $parent, array $path) use (&$build, $byParent, $texts, $now): array {
            $nodes = [];

            foreach ($byParent->get($parent, []) as $item) {
                if (in_array($item->uuid, $path, true) || ! $this->isVisible($item, $now)) {
                    continue;
                }

                $nodes[] = [
                    'uuid' => $item->uuid,
                    'title' => $texts[$item->uuid]->title ?? null,
                    'description' => $texts[$item->uuid]->description ?? null,
                    'url' => $item->url,
                    'target' => $item->target,
                    'css_class' => $item->css_class,
                    'image' => $item->image,
                    'is_external' => MenuUrl::isExternal($item->url),
                    'children' => $build($item->uuid, [...$path, $item->uuid]),
                ];
            }

            return $nodes;
        };

        return $build('', []);
    }

    /**
     * Every item of a menu in tree order (roots by weight, each followed by its subtree) for the
     * admin table. `status` is the item's own status: ancestors are not considered.
     *
     * @return list<array{uuid: string, parent_uuid: string|null, title: string|null, url: string|null, weight: int, depth: int, status: string, children_count: int}>
     */
    public function adminTree(Menu $menu, string $lang = 'default', ?CarbonInterface $now = null): array
    {
        $now ??= now();
        [$byParent, $texts] = $this->load($menu, $lang);
        $rows = [];

        $walk = function (string $parent, int $depth, array $path) use (&$walk, &$rows, $byParent, $texts, $now): void {
            foreach ($byParent->get($parent, []) as $item) {
                if (in_array($item->uuid, $path, true)) {
                    continue;
                }

                $rows[] = [
                    'uuid' => $item->uuid,
                    'parent_uuid' => $item->parent_uuid,
                    'title' => $texts[$item->uuid]->title ?? null,
                    'url' => $item->url,
                    'weight' => (int) $item->weight,
                    'depth' => $depth,
                    'status' => $this->status($item, $now),
                    'children_count' => count($byParent->get($item->uuid, [])),
                ];

                $walk($item->uuid, $depth + 1, [...$path, $item->uuid]);
            }
        };

        $walk('', 1, []);

        return $rows;
    }

    /**
     * Two queries: the items of the menu grouped by parent, and the translations of one language
     * (a third one for the `default` texts when `$fallback` is on and the language is an additional one).
     *
     * @return array{0: Collection<string, Collection<int, object>>, 1: Collection<string, object>}
     */
    private function load(Menu $menu, string $lang, bool $fallback = false): array
    {
        $items = DB::table('menu_items')
            ->where('menu_key', $menu->key)
            ->orderBy('weight')->orderBy('id')
            ->get(['uuid', 'parent_uuid', 'weight', 'url', 'target', 'css_class', 'image', 'is_active', 'published_at', 'unpublished_at']);

        $texts = DB::table('menu_item_translations')
            ->whereIn('item_uuid', $items->pluck('uuid'))->where('lang', $lang)
            ->get(['item_uuid', 'title', 'description'])->keyBy('item_uuid');

        if ($fallback && $lang !== config('content.default_lang')) {
            $base = DB::table('menu_item_translations')
                ->whereIn('item_uuid', $items->pluck('uuid'))->where('lang', config('content.default_lang'))
                ->get(['item_uuid', 'title', 'description'])->keyBy('item_uuid');
            $blank = fn (?string $text) => $text === null || trim($text) === '';

            $texts = $items->mapWithKeys(fn (object $item) => [$item->uuid => (object) [
                'title' => $blank($texts[$item->uuid]->title ?? null) ? ($base[$item->uuid]->title ?? null) : $texts[$item->uuid]->title,
                'description' => $blank($texts[$item->uuid]->description ?? null) ? ($base[$item->uuid]->description ?? null) : $texts[$item->uuid]->description,
            ]]);
        }

        return [$items->groupBy(fn (object $item) => $item->parent_uuid ?? ''), $texts];
    }

    private function isVisible(object $item, CarbonInterface $now): bool
    {
        return $this->status($item, $now) === 'published';
    }

    /**
     * Status of a raw row, with the same rules as {@see MenuItem::status()}.
     */
    private function status(object $item, CarbonInterface $now): string
    {
        $model = new MenuItem([
            'is_active' => (bool) $item->is_active,
            'published_at' => $item->published_at,
            'unpublished_at' => $item->unpublished_at,
        ]);

        return $model->status($now);
    }
}
