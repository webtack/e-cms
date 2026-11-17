<?php

namespace Webtack\Ecms\Http\Controllers\Admin;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Webtack\Ecms\Http\Controllers\Controller;
use Webtack\Ecms\Models\AdminMenuEntry;
use Webtack\Ecms\Models\ContentNode;
use Webtack\Ecms\Models\ContentType;
use Webtack\Ecms\Navigation\AdminMenu;
use Webtack\Ecms\Navigation\NodeTitles;
use Webtack\Ecms\Support\AppSettings;

/**
 * The page where an administrator builds the admin menu shown to everyone (`/admin/settings/navigation`).
 *
 * All routes sit behind `can:settings` (see `routes/web.php`). The page lists the whole saved structure,
 * unfiltered by access, and the types that are not in the menu yet. Writes go through {@see AdminMenu}.
 *
 * @example
 *   // PUT /admin/settings/navigation/order  items[0][id]=1&items[0][children][]=2
 */
class NavigationMenuController extends Controller
{
    /** The {@see AppSettings} key of the ordered list of type keys of the dashboard "Create" menu. */
    public const CREATE_TYPES_KEY = 'dashboard.create_types';

    public function __construct(private readonly AdminMenu $menu, private readonly NodeTitles $nodeTitles, private readonly AppSettings $settings) {}

    /** The editable tree, the types still free to add, the types whose nodes can be added and the dashboard Create menu list. */
    public function index(): Response
    {
        $types = ContentType::query()->orderBy('name')->get(['key', 'name', 'kind'])->keyBy('key');
        $entries = AdminMenuEntry::query()->orderBy('position')->orderBy('id')->get();
        $byParent = $entries->groupBy('parent_id');

        // Node links: the node type and the title, each read once for all of them.
        $uuids = $entries->pluck('node_uuid')->filter()->values()->all();
        $nodeTypes = ContentNode::query()->whereIn('uuid', $uuids)->pluck('content_type', 'uuid');
        $nodeTitles = $this->nodeTitles->for($uuids);

        $present = function (AdminMenuEntry $entry) use ($types, $nodeTypes, $nodeTitles) {
            // For a type link `name` is the type name; for a node link it is the name of the node type.
            $typeKey = $entry->isNodeLink() ? ($nodeTypes[$entry->node_uuid] ?? null) : $entry->type_key;

            return [
                'id' => $entry->id,
                'type_key' => $entry->type_key,
                'node_uuid' => $entry->node_uuid,
                'node_title' => $entry->isNodeLink() ? ($nodeTitles[$entry->node_uuid] ?? '') : null,
                'name' => $entry->isGroup() ? null : ($types[$typeKey]->name ?? $typeKey),
                'kind' => $entry->isGroup() ? null : ($types[$typeKey]->kind ?? 'content'),
                'title' => $entry->isGroup() ? ($entry->title[AdminMenuEntry::LANG] ?? '') : null,
            ];
        };

        $tree = $byParent->get(null, collect())->map(fn (AdminMenuEntry $entry) => [
            ...$present($entry),
            'children' => $entry->isGroup() ? $byParent->get($entry->id, collect())->map($present)->values()->all() : [],
        ])->values()->all();

        $used = $entries->pluck('type_key')->filter()->all();

        // The dashboard "Create" menu: saved keys in order; keys of deleted types are dropped (the next save clears them).
        $createKeys = collect($this->settings->get(self::CREATE_TYPES_KEY, []))->filter(fn ($key) => is_string($key) && $types->has($key))->unique()->values();
        $summary = fn (ContentType $type) => ['key' => $type->key, 'name' => $type->name, 'kind' => $type->kind];

        return Inertia::render('Admin/Settings/Navigation', [
            'tree' => $tree,
            'availableTypes' => $types->reject(fn (ContentType $type) => in_array($type->key, $used, true))
                ->map(fn (ContentType $type) => ['key' => $type->key, 'name' => $type->name, 'kind' => $type->kind])
                ->values(),
            // Every type: a node of a type that is already linked may still be added.
            'nodeTypes' => $types->map(fn (ContentType $type) => ['key' => $type->key, 'name' => $type->name, 'kind' => $type->kind])->values(),
            'createTypes' => $createKeys->map(fn (string $key) => $summary($types[$key]))->values()->all(),
            'createAvailableTypes' => $types->reject(fn (ContentType $type) => $createKeys->contains($type->key))->map($summary)->values()->all(),
            'titleMax' => AdminMenu::TITLE_MAX,
        ]);
    }

    /** Creates a group at the end of the root. */
    public function storeGroup(Request $request): RedirectResponse
    {
        $this->menu->addGroup((string) $request->input('title', ''));

        return back()->with('success', __('Group added.'));
    }

    /** Renames a group (base language). */
    public function updateGroup(Request $request, AdminMenuEntry $entry): RedirectResponse
    {
        $this->menu->renameGroup($entry, (string) $request->input('title', ''));

        return back()->with('success', __('Group renamed.'));
    }

    /** Adds a link to a type (`type_key`) or to a node (`node_uuid`), exactly one of them, into a group (`parent_id`) or the root. */
    public function storeLink(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'type_key' => ['required_without:node_uuid', 'prohibits:node_uuid', 'nullable', 'string'],
            'node_uuid' => ['required_without:type_key', 'prohibits:type_key', 'nullable', 'string'],
            'parent_id' => ['nullable', 'integer', 'exists:admin_menu_entries,id'],
        ]);

        $group = isset($validated['parent_id']) ? AdminMenuEntry::findOrFail($validated['parent_id']) : null;

        isset($validated['node_uuid'])
            ? $this->menu->addNodeLink($validated['node_uuid'], $group)
            : $this->menu->addLink($validated['type_key'], $group);

        return back()->with('success', __('Link added.'));
    }

    /** Deletes a link, or a group with its links. */
    public function destroy(AdminMenuEntry $entry): RedirectResponse
    {
        $this->menu->delete($entry);

        return back()->with('success', __('Menu entry deleted.'));
    }

    /** Saves the whole structure: the root order and the order inside groups. */
    public function reorder(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'items' => ['present', 'array'],
            'items.*.id' => ['required', 'integer'],
            'items.*.children' => ['nullable', 'array'],
            'items.*.children.*' => ['integer'],
        ]);

        $this->menu->save($validated['items']);

        return back();
    }

    /**
     * Saves the whole ordered list of the dashboard "Create" menu; an empty list removes the setting (the default applies).
     *
     * @example
     *   // PUT /admin/settings/navigation/create-types  types[0]=page&types[1]=news
     */
    public function updateCreateTypes(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'types' => ['present', 'array'],
            'types.*' => ['required', 'string', 'distinct', 'exists:content_types,key'],
        ]);

        $types = array_values($validated['types']);

        $types === []
            ? $this->settings->forget(self::CREATE_TYPES_KEY)
            : $this->settings->set(self::CREATE_TYPES_KEY, $types);

        return back();
    }
}
