<?php

namespace Webtack\Ecms\Http\Controllers\Admin;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Webtack\Ecms\Files\FileStorage;
use Webtack\Ecms\Http\Controllers\Admin\Concerns\ResolvesTranslationLanguage;
use Webtack\Ecms\Http\Controllers\Controller;
use Webtack\Ecms\Http\Requests\Admin\ReorderMenuItemsRequest;
use Webtack\Ecms\Http\Requests\Admin\SaveMenuItemTranslationRequest;
use Webtack\Ecms\Http\Requests\Admin\StoreMenuItemRequest;
use Webtack\Ecms\Http\Requests\Admin\UpdateMenuItemRequest;
use Webtack\Ecms\Menus\MenuItemTree;
use Webtack\Ecms\Menus\MenuItemWriter;
use Webtack\Ecms\Menus\MenuTree;
use Webtack\Ecms\Models\File;
use Webtack\Ecms\Models\Menu;
use Webtack\Ecms\Models\MenuItem;
use Webtack\Ecms\Models\MenuItemTranslation;

/**
 * Admin pages of the items of a menu (`/admin/menus/{menu}/items/**`): the tree table, create, edit, delete and
 * reorder.
 *
 * `{menu}` is bound by `key` and `{item}` by `uuid` within that menu (see `routes/web.php`), so a foreign item
 * answers 404. Writes go through `MenuItemWriter` (item, translations and image in one transaction); its
 * validation errors come back to the form as errors per field. The page hands **all** items of the menu to the
 * client table in tree order, each with its `depth`, `children_count` and computed `status`.
 *
 * Dragging a row only changes the order: the weights become `0…n-1` by row position and the parents stay as they
 * are, so the order among siblings follows the order of their rows. The parent is changed in the item form.
 *
 * Translations are edited one language at a time (`?lang=` on the edit page, see `docs/admin-translation-mode.md`):
 * `saveTranslation` and `clearTranslation` change only that language; the base form never touches translations.
 *
 * @example
 *   // GET /admin/menus/main/items
 *   // PUT /admin/menus/main/items/order  order[]=0190…&order[]=0190…
 */
class MenuItemController extends Controller
{
    use ResolvesTranslationLanguage;

    public function __construct(
        private readonly MenuItemTree $tree,
        private readonly MenuTree $menuTree,
        private readonly MenuItemWriter $writer,
        private readonly FileStorage $files,
    ) {}

    /** The tree table of the menu. */
    public function index(Menu $menu): Response
    {
        return Inertia::render('Admin/Menus/Items/Index', [
            'menu' => $this->menuProps($menu),
            'items' => $this->menuTree->adminTree($menu),
        ]);
    }

    /** The create form; the weight is prefilled so that the new item goes last among its siblings. */
    public function create(Menu $menu): Response
    {
        return Inertia::render('Admin/Menus/Items/Create', [
            'menu' => $this->menuProps($menu),
            'parentOptions' => $this->parentOptions($menu),
            'defaultWeight' => $this->nextWeight($menu, null),
        ]);
    }

    /** Creates an item and returns to the item table. */
    public function store(StoreMenuItemRequest $request, Menu $menu): RedirectResponse
    {
        $data = $this->withoutBlankDefaults($request->validated());
        $data['weight'] ??= $this->nextWeight($menu, $data['parent_uuid'] ?? null);

        $this->writer->create($menu, $data);

        return to_route('admin.menus.items.index', $menu->key)->with('success', __('Menu item created.'));
    }

    /**
     * The edit form; the item and its descendants are not offered as parents. With `?lang=` of an active additional
     * language the page is the translation mode: the title and description prefilled from the translation, else from
     * the base language.
     */
    public function edit(Request $request, Menu $menu, MenuItem $item): Response
    {
        $lang = $this->resolveTranslationLang($request);
        $translations = $item->translations->keyBy('lang');
        $base = $translations->get(config('content.default_lang'));
        $current = $lang === null ? null : $translations->get($lang);

        $values = $lang === null ? null : [
            'title' => $current?->title ?? $base?->title ?? '',
            'description' => $current?->description ?? $base?->description ?? '',
        ];

        return Inertia::render('Admin/Menus/Items/Edit', [
            'menu' => $this->menuProps($menu),
            'item' => [
                'uuid' => $item->uuid,
                'weight' => $item->weight,
                'parent_uuid' => $item->parent_uuid,
                'url' => $item->url,
                'target' => $item->target,
                'css_class' => $item->css_class,
                'image' => $item->image,
                'is_active' => $item->is_active,
                // `datetime-local` inputs take `YYYY-MM-DDTHH:MM`; the stored values are UTC.
                'published_at' => $item->published_at?->utc()->format('Y-m-d\TH:i'),
                'unpublished_at' => $item->unpublished_at?->utc()->format('Y-m-d\TH:i'),
                'title' => $base?->title ?? '',
                'description' => $base?->description ?? '',
            ],
            'imageFile' => $this->imageInfo($item->image),
            'parentOptions' => $this->parentOptions($menu, $item),
            'translation' => $this->translationProp($lang, $this->translatedLanguages($item), $values, $current !== null),
        ]);
    }

    /**
     * Saves the title and description of one language (the translation mode). Values equal to the base are not
     * stored and an empty field removes its translation; other languages and the base are unchanged.
     */
    public function saveTranslation(SaveMenuItemTranslationRequest $request, Menu $menu, MenuItem $item, string $lang): RedirectResponse
    {
        $lang = $this->activeTranslationLang($lang) ?? abort(404);

        $this->writer->saveTranslation($item, $lang, $request->validated());

        return to_route('admin.menus.items.edit', [$menu->key, $item->uuid, 'lang' => $lang])->with('success', __('Translation saved.'));
    }

    /** Deletes the translation of one language (the "Clear translation" action). */
    public function clearTranslation(Menu $menu, MenuItem $item, string $lang): RedirectResponse
    {
        $lang = $this->activeTranslationLang($lang) ?? abort(404);

        $this->writer->clearTranslation($item, $lang);

        return to_route('admin.menus.items.edit', [$menu->key, $item->uuid, 'lang' => $lang])->with('success', __('Translation cleared.'));
    }

    /**
     * The languages the item is translated into: those with a stored row that holds at least one value (rows only
     * keep values that differ from the base, see `MenuItemWriter::saveTranslation()`).
     *
     * @return list<string>
     */
    private function translatedLanguages(MenuItem $item): array
    {
        return $item->translations
            ->where('lang', '!=', config('content.default_lang'))
            ->filter(fn (MenuItemTranslation $row) => $row->title !== null || $row->description !== null)
            ->pluck('lang')->values()->all();
    }

    /**
     * Updates an item. A blank `weight` keeps the stored one; a blank `parent_uuid` makes the item a root, a blank
     * `url` makes it a group heading, blank dates remove the schedule and a blank `image` clears the image. Stays on the edit form.
     */
    public function update(UpdateMenuItemRequest $request, Menu $menu, MenuItem $item): RedirectResponse
    {
        $data = $this->withoutBlankDefaults($request->validated());

        if (($data['weight'] ?? null) === null) {
            unset($data['weight']);
        }

        $this->writer->update($item, $data);

        return back()->with('success', __('Menu item updated.'));
    }

    /**
     * Deletes an item. One with children is kept with a message that says why. The model enforces the same rule;
     * the check here only gives the user a plain message.
     */
    public function destroy(Menu $menu, MenuItem $item): RedirectResponse
    {
        if ($item->children()->exists()) {
            return back()->with('error', __('Cannot delete a menu item that has child items. Move or delete them first.'));
        }

        try {
            $item->delete();
        } catch (ValidationException $e) {
            return back()->with('error', collect($e->errors())->flatten()->first());
        }

        return to_route('admin.menus.items.index', $menu->key)->with('success', __('Menu item deleted.'));
    }

    /**
     * Sets only `is_active` of the item (the one-click badge in the list); the dates, title, address, parent and
     * weight stay. The save goes through the model, so its rules still apply and a refusal becomes an error flash.
     *
     * @example
     *   // PATCH /admin/menus/main/items/0190…/active  is_active=0
     */
    public function setActive(Request $request, Menu $menu, MenuItem $item): RedirectResponse
    {
        $active = $request->validate(['is_active' => ['required', 'boolean']])['is_active'];

        try {
            $item->update(['is_active' => $active]);
        } catch (ValidationException $e) {
            return back()->with('error', collect($e->errors())->flatten()->first());
        }

        return back()->with('success', $active ? __('Menu item enabled.') : __('Menu item disabled.'));
    }

    /**
     * Saves a new order: the weights become 0…n-1 in the given order. Only the weight changes and the uuid set was
     * validated, so the rows are updated directly, without the model's checks.
     */
    public function reorder(ReorderMenuItemsRequest $request, Menu $menu): RedirectResponse
    {
        DB::transaction(function () use ($request, $menu) {
            foreach ($request->validated('order') as $index => $uuid) {
                MenuItem::query()->where('menu_key', $menu->key)->where('uuid', $uuid)->update(['weight' => $index]);
            }
        });

        return back()->with('success', __('Menu item order updated.'));
    }

    /**
     * Drops `target` and `is_active` when the form left them blank, so the stored (or default) value stays.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function withoutBlankDefaults(array $data): array
    {
        foreach (['target', 'is_active'] as $key) {
            if (($data[$key] ?? null) === null) {
                unset($data[$key]);
            }
        }

        return $data;
    }

    /** @return array{key: string, name: string, max_depth: int|null} */
    private function menuProps(Menu $menu): array
    {
        return ['key' => $menu->key, 'name' => $menu->name, 'max_depth' => $menu->maxDepth()];
    }

    /**
     * Items that can be chosen as the parent, in tree order. For an existing item its own subtree is left out, and
     * an item is offered only when the item with its whole subtree still fits into the maximum depth below it.
     *
     * @return list<array{uuid: string, name: string|null, depth: int}>
     *
     * @example
     *   // max depth 2, new item: only roots are offered
     *   [['uuid' => '0190…', 'name' => 'Catalog', 'depth' => 1]]
     */
    private function parentOptions(Menu $menu, ?MenuItem $item = null): array
    {
        $excluded = $item === null ? [] : $this->tree->descendantUuids([$item->uuid]);
        $height = $item === null ? 1 : $this->tree->height($item);
        $max = $menu->maxDepth();

        return collect($this->menuTree->adminTree($menu))
            ->reject(fn (array $row) => in_array($row['uuid'], $excluded, true))
            ->reject(fn (array $row) => $max !== null && $max < $row['depth'] + $height)
            ->map(fn (array $row) => ['uuid' => $row['uuid'], 'name' => $row['title'], 'depth' => $row['depth']])
            ->values()
            ->all();
    }

    /** The weight that puts a new item after its siblings. */
    private function nextWeight(Menu $menu, ?string $parentUuid): int
    {
        $max = MenuItem::query()->where('menu_key', $menu->key)->where('parent_uuid', $parentUuid)->max('weight');

        return $max === null ? 0 : $max + 1;
    }

    /**
     * What the form shows for the stored image: the file record and the URL built from the value.
     *
     * @return array{uuid: string, name: string, description: string|null, mime: string, size: int, url: string, preview: string|null}|null
     */
    private function imageInfo(?string $value): ?array
    {
        if ($value === null) {
            return null;
        }

        $file = File::query()->where('uuid', substr($value, 0, 32))->first();

        return [
            'uuid' => substr($value, 0, 32),
            'name' => $file?->original_name ?? $value,
            'description' => $file?->description,
            'mime' => $file?->mime ?? '',
            'size' => $file?->size ?? 0,
            'url' => $this->files->urlForValue('image', $value),
            'preview' => $this->files->previewForValue('image', $value, $file?->mime ?? ''),
        ];
    }
}
