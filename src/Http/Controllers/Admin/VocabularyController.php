<?php

namespace Webtack\Ecms\Http\Controllers\Admin;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Webtack\Ecms\Http\Controllers\Controller;
use Webtack\Ecms\Http\Requests\Admin\StoreVocabularyRequest;
use Webtack\Ecms\Http\Requests\Admin\UpdateVocabularyRequest;
use Webtack\Ecms\Models\Vocabulary;
use Webtack\Ecms\Support\Table\TablePage;
use Webtack\Ecms\Support\Table\TableRequest;

/**
 * Admin pages of the vocabularies (`/admin/taxonomy/**`): list, create, edit and delete.
 *
 * The `{vocabulary}` route parameter is bound by `key` (see `routes/web.php`), so an unknown key answers 404.
 * The list hands every vocabulary with its term count to `TablePage::fromCollection()`, so search, sorting and
 * paging follow the same rules as the other server tables. No column is filterable. Search covers name, key and
 * description. Without an explicit `sort` the rows are ordered by name.
 *
 * @example
 *   // GET /admin/taxonomy?q=cat&sort=terms_count&dir=desc
 */
class VocabularyController extends Controller
{
    /** The list of vocabularies. */
    public function index(Request $request): Response
    {
        $table = TableRequest::from($request, self::columns());

        $rows = Vocabulary::query()
            ->withCount('terms')
            ->get()
            ->map(fn (Vocabulary $vocabulary) => [
                'key' => $vocabulary->key,
                'name' => $vocabulary->name,
                'terms_count' => $vocabulary->terms_count,
                'max_depth' => $vocabulary->maxDepth(),
                // Server-only column: searched, shown under the name by the client.
                'description' => $vocabulary->description,
            ])
            // The collection sort is stable, so this is the order when no `sort` is requested.
            ->sortBy(fn (array $row) => mb_strtolower($row['name']))
            ->values();

        return Inertia::render('Admin/Taxonomy/Index', ['table' => TablePage::fromCollection($rows, $table)]);
    }

    /** The create form. */
    public function create(): Response
    {
        return Inertia::render('Admin/Taxonomy/Create');
    }

    /** Creates a vocabulary and opens its term page. */
    public function store(StoreVocabularyRequest $request): RedirectResponse
    {
        $vocabulary = Vocabulary::create(StoreVocabularyRequest::modelAttributes($request->validated()));

        return to_route('admin.taxonomy.terms.index', $vocabulary->key)->with('success', __('Vocabulary created.'));
    }

    /** The edit form; the key is shown read-only. */
    public function edit(Vocabulary $vocabulary): Response
    {
        return Inertia::render('Admin/Taxonomy/Edit', [
            'vocabulary' => [
                'key' => $vocabulary->key,
                'name' => $vocabulary->name,
                'description' => $vocabulary->description,
                'max_depth' => $vocabulary->maxDepth(),
            ],
        ]);
    }

    /** Updates name, description and maximum depth; `key` never changes. Stays on the edit form. */
    public function update(UpdateVocabularyRequest $request, Vocabulary $vocabulary): RedirectResponse
    {
        $vocabulary->update(StoreVocabularyRequest::modelAttributes($request->validated(), $vocabulary->settings ?? []));

        return back()->with('success', __('Vocabulary updated.'));
    }

    /**
     * Deletes a vocabulary. One that has terms or is named by a field is kept with a message that says why.
     * The model enforces the same rules; the checks here only give the user a plain message.
     */
    public function destroy(Vocabulary $vocabulary): RedirectResponse
    {
        if ($vocabulary->terms()->exists()) {
            return back()->with('error', __('Cannot delete a vocabulary that has terms.'));
        }

        if ($vocabulary->referencingFields()->exists()) {
            return back()->with('error', __('Cannot delete a vocabulary that is used by a field.'));
        }

        try {
            $vocabulary->delete();
        } catch (ValidationException $e) {
            return back()->with('error', collect($e->errors())->flatten()->first());
        }

        return to_route('admin.taxonomy.index')->with('success', __('Vocabulary deleted.'));
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
            ['key' => 'terms_count', 'type' => 'number', 'sortable' => true],
            ['key' => 'max_depth', 'type' => 'number', 'sortable' => true, 'searchable' => false],
            ['key' => 'description', 'type' => 'text'],
        ];
    }
}
