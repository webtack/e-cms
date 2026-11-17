<?php

namespace Webtack\Ecms\Http\Controllers\Admin;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Webtack\Ecms\Content\FieldTypeRegistry;
use Webtack\Ecms\Content\FieldTypes\AbstractFileFieldType;
use Webtack\Ecms\Content\SystemFields;
use Webtack\Ecms\Files\FileTypes;
use Webtack\Ecms\Files\UploadRules;
use Webtack\Ecms\Http\Controllers\Admin\Concerns\ResolvesTranslationLanguage;
use Webtack\Ecms\Http\Controllers\Admin\Concerns\ResolvesTypeSection;
use Webtack\Ecms\Http\Controllers\Controller;
use Webtack\Ecms\Http\Requests\Admin\ReorderContentFieldsRequest;
use Webtack\Ecms\Http\Requests\Admin\SaveContentFieldTranslationRequest;
use Webtack\Ecms\Http\Requests\Admin\StoreContentFieldRequest;
use Webtack\Ecms\Http\Requests\Admin\UpdateContentFieldRequest;
use Webtack\Ecms\Models\ContentField;
use Webtack\Ecms\Models\ContentType;
use Webtack\Ecms\Models\Vocabulary;

/**
 * Admin pages of the fields of a content type of kind `content`, `page` or `block`: list, create, edit, delete and reorder.
 *
 * The kind comes from the route (`/admin/content-types/**`, `/admin/page-types/**` or `/admin/block-types/**`). The `{contentType}`
 * route parameter is bound by `key` and only within that kind, and `{field}` by the pair (type, `key`)
 * (see `routes/web.php`), so other kinds and foreign keys answer 404.
 *
 * The list hands all fields of the type to the client table (ordered by `weight`, then `id`),
 * each with `has_values`: whether any value of the field is stored. The model guards the rest
 * (immutable key and type, locked system field, no deletion of a field that has values); its validation errors
 * come back to the form as errors per field.
 *
 * Panel fields (`meta_*`, `section_*`, see `SystemFields::panel()`) are configured by the system and are not offered
 * here: the list leaves them out, and their edit, update and delete answer 404. Their values are edited on the
 * "Section" and "SEO" tabs of the type and in the "SEO" section of a node.
 *
 * The label (`name`) of a field can be translated one language at a time (`?lang=` on the edit page,
 * `saveTranslation` / `clearTranslation`, see `docs/admin-translation-mode.md`); translations live in
 * `settings.labels` and the base form never changes them.
 *
 * @example
 *   // GET /admin/content-types/news/fields
 *   // GET /admin/page-types/home/fields
 *   // GET /admin/block-types/hero/fields
 *   // PUT /admin/content-types/news/fields/order  order[]=title&order[]=body
 */
class ContentFieldController extends Controller
{
    use ResolvesTranslationLanguage, ResolvesTypeSection;

    public function __construct(private readonly FieldTypeRegistry $registry, private readonly SystemFields $systemFields) {}

    /** The list of the type's fields, with `has_values` for the delete dialog. */
    public function index(Request $request, ContentType $contentType): Response
    {
        $valued = $this->valuedKeys($contentType);

        return Inertia::render('Admin/ContentFields/Index', [
            'type' => $contentType->navProps(),
            'section' => $this->section($request),
            'fields' => $contentType->fields->reject($this->isPanelField(...))->map(fn (ContentField $field) => [
                ...$this->row($field),
                'has_values' => in_array($field->key, $valued, true),
            ])->values(),
        ]);
    }

    /** The create form; the weight is prefilled so that the new field goes last. */
    public function create(Request $request, ContentType $contentType): Response
    {
        $max = $contentType->fields()->max('weight');

        return Inertia::render('Admin/ContentFields/Create', [
            'type' => $contentType->navProps(),
            'section' => $this->section($request),
            'typeOptions' => $this->typeOptions($request),
            'fileTypes' => $this->fileTypes(),
            'vocabularies' => $this->vocabularies(),
            'kinds' => config('content.kinds'),
            'contentTypes' => $this->contentTypes(),
            'defaultWeight' => $max === null ? 0 : $max + 1,
        ]);
    }

    /** Creates a field; `content_type` comes from the relation and the field is never a system one. */
    public function store(StoreContentFieldRequest $request, ContentType $contentType): RedirectResponse
    {
        $contentType->fields()->create($this->attributes($request->validated()));

        return to_route($this->routeName($request, 'fields.index'), $contentType->key)->with('success', __('Field created.'));
    }

    /**
     * The edit form; `has_values` and `is_system` tell it which controls to lock. With `?lang=` of an active additional
     * language the page is the translation mode of the label: `translation.values.name` is the stored label, else the base name.
     */
    public function edit(Request $request, ContentType $contentType, ContentField $field): Response
    {
        $this->abortForPanelField($field);

        $lang = $this->resolveTranslationLang($request);

        return Inertia::render('Admin/ContentFields/Edit', [
            'type' => $contentType->navProps(),
            'section' => $this->section($request),
            'field' => [
                ...$this->row($field),
                'description' => $field->description,
                'settings' => $field->settings,
                'has_values' => in_array($field->key, $this->valuedKeys($contentType), true),
            ],
            'typeOptions' => $this->typeOptions($request),
            'fileTypes' => $this->fileTypes(),
            'vocabularies' => $this->vocabularies(),
            'kinds' => config('content.kinds'),
            'contentTypes' => $this->contentTypes(),
            'translation' => $this->translationProp(
                $lang,
                array_keys($field->labels()),
                $lang === null ? null : ['name' => $field->label($lang) ?? $field->name],
                $lang !== null && $field->label($lang) !== null,
            ),
        ]);
    }

    /**
     * Saves the label of one language (the translation mode). A label equal to the base name is not stored and an
     * empty one removes the stored label; the description, flags, weight and settings never change. The right is the
     * type configuration right (route middleware `can:types`).
     */
    public function saveTranslation(SaveContentFieldTranslationRequest $request, ContentType $contentType, ContentField $field, string $lang): RedirectResponse
    {
        $this->abortForPanelField($field);
        $lang = $this->activeTranslationLang($lang) ?? abort(404);

        $label = $request->validated('name');
        $field->setLabel($lang, $label === $field->name ? null : $label);

        return to_route($this->routeName($request, 'fields.edit'), [$contentType->key, $field->key, 'lang' => $lang])->with('success', __('Translation saved.'));
    }

    /** Removes the label of one language (the "Clear translation" action). */
    public function clearTranslation(Request $request, ContentType $contentType, ContentField $field, string $lang): RedirectResponse
    {
        $this->abortForPanelField($field);
        $lang = $this->activeTranslationLang($lang) ?? abort(404);

        $field->setLabel($lang, null);

        return to_route($this->routeName($request, 'fields.edit'), [$contentType->key, $field->key, 'lang' => $lang])->with('success', __('Translation cleared.'));
    }

    /** Updates name, description, type, flags and weight; the model rejects forbidden changes with errors per field. Stays on the edit form. */
    public function update(UpdateContentFieldRequest $request, ContentType $contentType, ContentField $field): RedirectResponse
    {
        $this->abortForPanelField($field);

        $field->update($this->attributes($request->validated(), $field->labels()));

        return back()->with('success', __('Field updated.'));
    }

    /**
     * Deletes a field. A system field and a field that has values are kept. The model enforces
     * the same rule; this check only gives the user a plain message instead of a validation error.
     */
    public function destroy(Request $request, ContentType $contentType, ContentField $field): RedirectResponse
    {
        $this->abortForPanelField($field);

        if ($field->is_system) {
            return back()->with('error', __('A system field cannot be deleted.'));
        }

        if (in_array($field->key, $this->valuedKeys($contentType), true)) {
            return back()->with('error', __('Cannot delete a field that has values.'));
        }

        $field->delete();

        return to_route($this->routeName($request, 'fields.index'), $contentType->key)->with('success', __('Field deleted.'));
    }

    /**
     * Saves a new order: the weights become 0…n-1 in the given order. Only the weight changes and
     * the key set was validated, so the rows are updated directly, without the model's checks.
     */
    public function reorder(ReorderContentFieldsRequest $request, ContentType $contentType): RedirectResponse
    {
        DB::transaction(function () use ($request, $contentType) {
            foreach ($request->validated('order') as $index => $key) {
                ContentField::query()
                    ->where('content_type', $contentType->key)
                    ->where('key', $key)
                    ->update(['weight' => $index]);
            }
        });

        return back()->with('success', __('Field order updated.'));
    }

    /** Whether the field belongs to a panel (`meta_*`, `section_*`): configured by the system, hidden from this section. */
    private function isPanelField(ContentField $field): bool
    {
        return $this->systemFields->panel($field->key) !== null;
    }

    /** Answers 404 for a panel field, as if it did not exist here. */
    private function abortForPanelField(ContentField $field): void
    {
        abort_if($this->isPanelField($field), 404);
    }

    /**
     * The upload rules of the whole profile of every file-bearing type, which the form offers for narrowing.
     *
     * @return array<string, array{mimes: list<string>, labels: array<string, string>, max_size: int, max_width: int|null, max_height: int|null}>
     *
     * @example
     *   $this->fileTypes(); // ['image' => ['mimes' => ['image/jpeg', ...], 'labels' => ['image/jpeg' => 'jpg', ...], 'max_size' => 5120, 'max_width' => 1920, 'max_height' => 1920], 'file' => [...]]
     */
    private function fileTypes(): array
    {
        $types = [];

        foreach ($this->registry->keys() as $key) {
            $type = $this->registry->get($key);

            if ($type instanceof AbstractFileFieldType) {
                $rules = UploadRules::forProfile($type->profile());
                $types[$key] = [
                    'mimes' => $rules->mimes,
                    'labels' => array_combine($rules->mimes, array_map(FileTypes::label(...), $rules->mimes)),
                    'max_size' => $rules->maxSize,
                    'max_width' => $rules->maxWidth,
                    'max_height' => $rules->maxHeight,
                ];
            }
        }

        return $types;
    }

    /**
     * The validated attributes with `settings` reduced to what is set: `mimes`, `max_size`, `max_width` and
     * `max_height`, or `null` when all are empty (the whole profile applies). Without `settings` in the input the stored ones stay.
     * `max_items` becomes an integer and is reset to `0` for a non-multiple field.
     *
     * Translated labels (`settings.labels`) are never taken from the form: whatever it sends is dropped and
     * the stored `$labels` are put back, so saving the base form cannot change or lose them for any type.
     *
     * @param  array<string, mixed>  $validated
     * @param  array<string, string>  $labels  The labels stored for the field (none for a new one).
     * @return array<string, mixed>
     */
    private function attributes(array $validated, array $labels = []): array
    {
        if (isset($validated['settings']) && is_array($validated['settings'])) {
            unset($validated['settings']['labels']);
        }

        if (($validated['type'] ?? null) === 'term') {
            // The vocabulary key is the only setting of a term field.
            $validated['settings'] = ['vocabulary' => $validated['settings']['vocabulary']];
        } elseif (($validated['type'] ?? null) === 'type_ref') {
            $validated['settings'] = ['kind' => $validated['settings']['kind']];
        } elseif (($validated['type'] ?? null) === 'node_ref') {
            $validated['settings'] = ['content_type' => $validated['settings']['content_type']];
        } elseif (array_key_exists('settings', $validated)) {
            $mimes = array_values(array_unique($validated['settings']['mimes'] ?? []));
            $maxSize = $validated['settings']['max_size'] ?? null;
            $maxWidth = $validated['settings']['max_width'] ?? null;
            $maxHeight = $validated['settings']['max_height'] ?? null;

            $validated['settings'] = array_filter([
                'mimes' => $mimes ?: null,
                'max_size' => $maxSize === null ? null : (int) $maxSize,
                'max_width' => $maxWidth === null ? null : (int) $maxWidth,
                'max_height' => $maxHeight === null ? null : (int) $maxHeight,
            ], fn (mixed $value) => $value !== null) ?: null;
        }

        if (array_key_exists('settings', $validated) && $labels !== []) {
            $validated['settings'] = [...($validated['settings'] ?? []), 'labels' => $labels];
        }

        // The limit belongs to a multiple field only; an empty or inapplicable one means "no limit".
        if (array_key_exists('max_items', $validated) || array_key_exists('is_multiple', $validated)) {
            $validated['max_items'] = ($validated['is_multiple'] ?? false) ? (int) ($validated['max_items'] ?? 0) : 0;
        }

        return $validated;
    }

    /**
     * The attributes of a field the pages need.
     *
     * @return array<string, mixed>
     */
    private function row(ContentField $field): array
    {
        return $field->only(['key', 'name', 'type', 'is_multiple', 'max_items', 'is_required', 'is_translatable', 'hide_label', 'is_system', 'weight']);
    }

    /**
     * Keys of the type's fields that have at least one stored value in a node, with one query per
     * value table in use (not per field). Values of the type itself (`entity = 'type'`) do not
     * count: they are not filled in records. `ContentField::hasStoredValues()` still counts both
     * owners, so such a field is kept all the same.
     *
     * @return list<string>
     *
     * @example
     *   $this->valuedKeys($type); // ['title', 'body']
     */
    private function valuedKeys(ContentType $type): array
    {
        $valued = [];

        foreach ($type->fields->groupBy(fn (ContentField $field) => $this->registry->get($field->type)->table()) as $table => $fields) {
            $keys = DB::table($table)
                ->where('entity', SystemFields::OWNER_NODE)
                ->where('content_type', $type->key)
                ->whereIn('field_key', $fields->pluck('key'))
                ->distinct()
                ->pluck('field_key')
                ->all();

            array_push($valued, ...$keys);
        }

        return $valued;
    }

    /**
     * The vocabularies a field of type `term` can point at, by name.
     *
     * @return list<array{key: string, name: string}>
     *
     * @example
     *   $this->vocabularies(); // [['key' => 'categories', 'name' => 'Categories']]
     */
    private function vocabularies(): array
    {
        return Vocabulary::query()->get(['key', 'name'])
            ->sortBy(fn (Vocabulary $vocabulary) => mb_strtolower($vocabulary->name))
            ->map(fn (Vocabulary $vocabulary) => ['key' => $vocabulary->key, 'name' => $vocabulary->name])
            ->values()
            ->all();
    }

    /**
     * The content types a `node_ref` field can point at (all kinds), by name, for the form's type search.
     *
     * @return list<array{key: string, name: string, kind: string}>
     *
     * @example
     *   $this->contentTypes(); // [['key' => 'article', 'name' => 'Article', 'kind' => 'content']]
     */
    private function contentTypes(): array
    {
        return ContentType::query()->orderBy('name')->orderBy('key')->get(['key', 'name', 'kind'])
            ->map(fn (ContentType $type) => ['key' => $type->key, 'name' => $type->name, 'kind' => $type->kind])
            ->all();
    }

    /**
     * Field types the form offers: `term` only in the `content` section.
     *
     * @return list<string>
     *
     * @example
     *   $this->typeOptions($request); // page/block sections: no 'term'
     */
    private function typeOptions(Request $request): array
    {
        $keys = $this->registry->keys();

        return array_values($this->kind($request) === 'content' ? $keys : array_diff($keys, ['term']));
    }
}
