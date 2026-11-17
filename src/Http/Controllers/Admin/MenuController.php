<?php

namespace Webtack\Ecms\Http\Controllers\Admin;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Webtack\Ecms\Http\Controllers\Controller;
use Webtack\Ecms\Http\Requests\Admin\StoreMenuRequest;
use Webtack\Ecms\Http\Requests\Admin\UpdateMenuRequest;
use Webtack\Ecms\Models\Menu;
use Webtack\Ecms\Support\Table\TablePage;
use Webtack\Ecms\Support\Table\TableRequest;

/**
 * Admin pages of the menus (`/admin/menus/**`): list, create, edit and delete.
 *
 * The `{menu}` route parameter is bound by `key` (see `routes/web.php`), so an unknown key answers 404.
 * The list hands every menu with its item count to `TablePage::fromCollection()`, so
 * search, sorting and paging follow the same rules as the other server tables. No column is filterable. Search
 * covers name, key and description. Without an explicit `sort` the rows are ordered by name.
 *
 * @example
 *   // GET /admin/menus?q=main&sort=items_count&dir=desc
 */
class MenuController extends Controller
{
    /** The list of menus. */
    public function index(Request $request): Response
    {
        $table = TableRequest::from($request, self::columns());

        $rows = Menu::query()
            ->withCount('items')
            ->get()
            ->map(fn (Menu $menu) => [
                'key' => $menu->key,
                'name' => $menu->name,
                'items_count' => $menu->items_count,
                'max_depth' => $menu->maxDepth(),
                // Server-only column: searched, shown under the name by the client.
                'description' => $menu->description,
            ])
            // The collection sort is stable, so this is the order when no `sort` is requested.
            ->sortBy(fn (array $row) => mb_strtolower($row['name']))
            ->values();

        return Inertia::render('Admin/Menus/Index', ['table' => TablePage::fromCollection($rows, $table)]);
    }

    /** The create form. */
    public function create(): Response
    {
        return Inertia::render('Admin/Menus/Create');
    }

    /** Creates a menu and opens its item page. */
    public function store(StoreMenuRequest $request): RedirectResponse
    {
        $menu = Menu::create(StoreMenuRequest::modelAttributes($request->validated()));

        return to_route('admin.menus.items.index', $menu->key)->with('success', __('Menu created.'));
    }

    /** The edit form; the key is shown read-only. */
    public function edit(Menu $menu): Response
    {
        return Inertia::render('Admin/Menus/Edit', [
            'menu' => [
                'key' => $menu->key,
                'name' => $menu->name,
                'description' => $menu->description,
                'max_depth' => $menu->maxDepth(),
            ],
        ]);
    }

    /** Updates name, description and maximum depth; `key` never changes. Stays on the edit form. */
    public function update(UpdateMenuRequest $request, Menu $menu): RedirectResponse
    {
        $menu->update(StoreMenuRequest::modelAttributes($request->validated(), $menu->settings ?? []));

        return back()->with('success', __('Menu updated.'));
    }

    /**
     * Deletes a menu. One that has items is kept with a message that says why. The model enforces the same
     * rule; the check here only gives the user a plain message.
     */
    public function destroy(Menu $menu): RedirectResponse
    {
        if ($menu->items()->exists()) {
            return back()->with('error', __('Cannot delete a menu that has items.'));
        }

        try {
            $menu->delete();
        } catch (ValidationException $e) {
            return back()->with('error', collect($e->errors())->flatten()->first());
        }

        return to_route('admin.menus.index')->with('success', __('Menu deleted.'));
    }

    /**
     * Table columns; `description` is searched but not shown as a column.
     *
     * @return list<array<string, mixed>>
     */
    private static function columns(): array
    {
        return [
            ['key' => 'name', 'type' => 'text', 'sortable' => true],
            ['key' => 'key', 'type' => 'text', 'sortable' => true],
            ['key' => 'items_count', 'type' => 'number', 'sortable' => true],
            ['key' => 'max_depth', 'type' => 'number', 'sortable' => true, 'searchable' => false],
            ['key' => 'description', 'type' => 'text'],
        ];
    }
}
