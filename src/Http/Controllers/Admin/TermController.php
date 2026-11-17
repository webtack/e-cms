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
use Webtack\Ecms\Http\Requests\Admin\ReorderTermsRequest;
use Webtack\Ecms\Http\Requests\Admin\SaveTermTranslationRequest;
use Webtack\Ecms\Http\Requests\Admin\StoreTermRequest;
use Webtack\Ecms\Http\Requests\Admin\UpdateTermRequest;
use Webtack\Ecms\Models\File;
use Webtack\Ecms\Models\Term;
use Webtack\Ecms\Models\TermTranslation;
use Webtack\Ecms\Models\Vocabulary;
use Webtack\Ecms\Taxonomy\TermTree;
use Webtack\Ecms\Taxonomy\TermUsage;
use Webtack\Ecms\Taxonomy\TermWriter;

/**
 * Admin pages of the terms of a vocabulary (`/admin/taxonomy/{vocabulary}/terms/**`): the tree table, create, edit,
 * delete and reorder.
 *
 * `{vocabulary}` is bound by `key` and `{term}` by `uuid` within that vocabulary (see `routes/web.php`), so
 * a foreign term answers 404. Writes go through `TermWriter` (term, translations and image in one transaction);
 * its validation errors come back to the form as errors per field. The page hands **all** terms of the vocabulary
 * to the client table in tree order, each with its `depth`, `children_count` and `usages`.
 *
 * Dragging a row only changes the order: the weights become `0…n-1` by row position and the parents stay as they
 * are, so the order among siblings follows the order of their rows. The parent is changed in the term form.
 *
 * Translations are edited one language at a time (`?lang=` on the edit page, see `docs/admin-translation-mode.md`):
 * `saveTranslation` and `clearTranslation` change only that language; the base form never touches translations.
 *
 * @example
 *   // GET /admin/taxonomy/categories/terms
 *   // PUT /admin/taxonomy/categories/terms/order  order[]=0190…&order[]=0190…
 */
class TermController extends Controller
{
    use ResolvesTranslationLanguage;

    public function __construct(
        private readonly TermTree $tree,
        private readonly TermUsage $usage,
        private readonly TermWriter $writer,
        private readonly FileStorage $files,
    ) {}

    /** The tree table of the vocabulary. */
    public function index(Vocabulary $vocabulary): Response
    {
        $usages = $this->usage->forVocabulary($vocabulary);

        return Inertia::render('Admin/Taxonomy/Terms/Index', [
            'vocabulary' => $this->vocabularyProps($vocabulary),
            'terms' => collect($this->flatten($vocabulary))->map(fn (array $row) => [
                'uuid' => $row['uuid'],
                'name' => $row['name'],
                'slug' => $row['slug'],
                'weight' => $row['weight'],
                'depth' => $row['depth'],
                'children_count' => $row['children_count'],
                // Service value for the delete dialog; not a column.
                'usages' => $usages[$row['uuid']] ?? [],
            ])->values(),
        ]);
    }

    /** The create form; the weight is prefilled so that the new term goes last among its siblings. */
    public function create(Vocabulary $vocabulary): Response
    {
        return Inertia::render('Admin/Taxonomy/Terms/Create', [
            'vocabulary' => $this->vocabularyProps($vocabulary),
            'parentOptions' => $this->parentOptions($vocabulary),
            'defaultWeight' => $this->nextWeight($vocabulary, null),
        ]);
    }

    /** Creates a term and returns to the term table. */
    public function store(StoreTermRequest $request, Vocabulary $vocabulary): RedirectResponse
    {
        $data = $request->validated();
        $data['weight'] ??= $this->nextWeight($vocabulary, $data['parent_uuid'] ?? null);

        $this->writer->create($vocabulary, $data);

        return to_route('admin.taxonomy.terms.index', $vocabulary->key)->with('success', __('Term created.'));
    }

    /**
     * The edit form; the term and its descendants are not offered as parents. With `?lang=` of an active additional
     * language the page is the translation mode: the name and description prefilled from the translation, else from
     * the base language.
     */
    public function edit(Request $request, Vocabulary $vocabulary, Term $term): Response
    {
        $lang = $this->resolveTranslationLang($request);
        $translations = $term->translations->keyBy('lang');
        $base = $translations->get(config('content.default_lang'));
        $current = $lang === null ? null : $translations->get($lang);

        $values = $lang === null ? null : [
            'name' => $current?->name ?? $base?->name ?? '',
            'description' => $current?->description ?? $base?->description ?? '',
        ];

        return Inertia::render('Admin/Taxonomy/Terms/Edit', [
            'vocabulary' => $this->vocabularyProps($vocabulary),
            'term' => [
                'uuid' => $term->uuid,
                'slug' => $term->slug,
                'weight' => $term->weight,
                'parent_uuid' => $term->parent_uuid,
                'image' => $term->image,
                'name' => $base?->name ?? '',
                'description' => $base?->description ?? '',
            ],
            'imageFile' => $this->imageInfo($term->image),
            'parentOptions' => $this->parentOptions($vocabulary, $term),
            'translation' => $this->translationProp($lang, $this->translatedLanguages($term), $values, $current !== null),
        ]);
    }

    /**
     * Saves the name and description of one language (the translation mode). Values equal to the base are not
     * stored and an empty field removes its translation; other languages and the base are unchanged.
     */
    public function saveTranslation(SaveTermTranslationRequest $request, Vocabulary $vocabulary, Term $term, string $lang): RedirectResponse
    {
        $lang = $this->activeTranslationLang($lang) ?? abort(404);

        $this->writer->saveTranslation($term, $lang, $request->validated());

        return to_route('admin.taxonomy.terms.edit', [$vocabulary->key, $term->uuid, 'lang' => $lang])->with('success', __('Translation saved.'));
    }

    /** Deletes the translation of one language (the "Clear translation" action). */
    public function clearTranslation(Vocabulary $vocabulary, Term $term, string $lang): RedirectResponse
    {
        $lang = $this->activeTranslationLang($lang) ?? abort(404);

        $this->writer->clearTranslation($term, $lang);

        return to_route('admin.taxonomy.terms.edit', [$vocabulary->key, $term->uuid, 'lang' => $lang])->with('success', __('Translation cleared.'));
    }

    /**
     * The languages the term is translated into: those with a stored row that holds at least one value (rows only
     * keep values that differ from the base, see `TermWriter::saveTranslation()`).
     *
     * @return list<string>
     */
    private function translatedLanguages(Term $term): array
    {
        return $term->translations
            ->where('lang', '!=', config('content.default_lang'))
            ->filter(fn (TermTranslation $row) => $row->name !== null || $row->description !== null)
            ->pluck('lang')->values()->all();
    }

    /**
     * Updates a term. A blank `slug` or `weight` keeps the stored one; a blank `parent_uuid` makes the term a root
     * and a blank `image` clears the image. Stays on the edit form.
     */
    public function update(UpdateTermRequest $request, Vocabulary $vocabulary, Term $term): RedirectResponse
    {
        $data = $request->validated();

        foreach (['slug', 'weight'] as $key) {
            if (($data[$key] ?? null) === null) {
                unset($data[$key]);
            }
        }

        $this->writer->update($term, $data);

        return back()->with('success', __('Term updated.'));
    }

    /**
     * Deletes a term. One with children or one used by field values is kept with a message that says why (and
     * where it is used). The model enforces the same rules; the checks here only give the user a plain message.
     */
    public function destroy(Vocabulary $vocabulary, Term $term): RedirectResponse
    {
        if ($term->children()->exists()) {
            return back()->with('error', __('Cannot delete a term that has child terms. Move or delete them first.'));
        }

        $places = $this->usage->forTerm($term);

        if ($places->isNotEmpty()) {
            return back()->with('error', __('Cannot delete a term that is used: :places.', [
                'places' => $places->map(fn (array $place) => "{$place['field_key']} ({$place['content_type']}): {$place['count']}")->implode(', '),
            ]));
        }

        try {
            $term->delete();
        } catch (ValidationException $e) {
            return back()->with('error', collect($e->errors())->flatten()->first());
        }

        return to_route('admin.taxonomy.terms.index', $vocabulary->key)->with('success', __('Term deleted.'));
    }

    /**
     * Saves a new order: the weights become 0…n-1 in the given order. Only the weight changes and the uuid set was
     * validated, so the rows are updated directly, without the model's checks.
     */
    public function reorder(ReorderTermsRequest $request, Vocabulary $vocabulary): RedirectResponse
    {
        DB::transaction(function () use ($request, $vocabulary) {
            foreach ($request->validated('order') as $index => $uuid) {
                Term::query()->where('vocabulary_key', $vocabulary->key)->where('uuid', $uuid)->update(['weight' => $index]);
            }
        });

        return back()->with('success', __('Term order updated.'));
    }

    /** @return array{key: string, name: string, max_depth: int|null} */
    private function vocabularyProps(Vocabulary $vocabulary): array
    {
        return ['key' => $vocabulary->key, 'name' => $vocabulary->name, 'max_depth' => $vocabulary->maxDepth()];
    }

    /**
     * The terms of the vocabulary in tree order (parents before their children, siblings by weight), flat, with the
     * depth (a root has 1) and the number of direct children.
     *
     * @return list<array{uuid: string, name: string|null, slug: string, weight: int, depth: int, children_count: int}>
     */
    private function flatten(Vocabulary $vocabulary): array
    {
        $rows = [];
        $walk = function (array $nodes, int $depth) use (&$walk, &$rows) {
            foreach ($nodes as $node) {
                $rows[] = [
                    'uuid' => $node['uuid'],
                    'name' => $node['name'],
                    'slug' => $node['slug'],
                    'weight' => $node['weight'],
                    'depth' => $depth,
                    'children_count' => count($node['children']),
                ];
                $walk($node['children'], $depth + 1);
            }
        };
        $walk($this->tree->tree($vocabulary), 1);

        return $rows;
    }

    /**
     * Terms that can be chosen as the parent, in tree order. For an existing term its own subtree is left out, and
     * a term is offered only when the term with its whole subtree still fits into the maximum depth below it.
     *
     * @return list<array{uuid: string, name: string|null, depth: int}>
     *
     * @example
     *   // max depth 2, new term: only roots are offered
     *   [['uuid' => '0190…', 'name' => 'Clothes', 'depth' => 1]]
     */
    private function parentOptions(Vocabulary $vocabulary, ?Term $term = null): array
    {
        $excluded = $term === null ? [] : $this->tree->descendantUuids([$term->uuid]);
        $height = $term === null ? 1 : $this->tree->height($term);
        $max = $vocabulary->maxDepth();

        return collect($this->flatten($vocabulary))
            ->reject(fn (array $row) => in_array($row['uuid'], $excluded, true))
            ->reject(fn (array $row) => $max !== null && $max < $row['depth'] + $height)
            ->map(fn (array $row) => ['uuid' => $row['uuid'], 'name' => $row['name'], 'depth' => $row['depth']])
            ->values()
            ->all();
    }

    /** The weight that puts a new term after its siblings. */
    private function nextWeight(Vocabulary $vocabulary, ?string $parentUuid): int
    {
        $max = Term::query()->where('vocabulary_key', $vocabulary->key)->where('parent_uuid', $parentUuid)->max('weight');

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
