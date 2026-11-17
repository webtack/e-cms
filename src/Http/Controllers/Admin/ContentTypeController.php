<?php

namespace Webtack\Ecms\Http\Controllers\Admin;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Webtack\Ecms\Access\AccessResolver;
use Webtack\Ecms\Content\ContentTypeCloner;
use Webtack\Ecms\Content\ContentValueReader;
use Webtack\Ecms\Content\FieldTypeRegistry;
use Webtack\Ecms\Content\NodeListSettings;
use Webtack\Ecms\Content\PublicDisplaySettings;
use Webtack\Ecms\Content\SystemFields;
use Webtack\Ecms\Content\ThemeTemplateCopyException;
use Webtack\Ecms\Content\TranslatedLanguages;
use Webtack\Ecms\Content\ValueOwner;
use Webtack\Ecms\Files\FileDescriptions;
use Webtack\Ecms\Files\FileStorage;
use Webtack\Ecms\Http\Controllers\Admin\Concerns\PresentsFormFields;
use Webtack\Ecms\Http\Controllers\Admin\Concerns\ResolvesTranslationLanguage;
use Webtack\Ecms\Http\Controllers\Admin\Concerns\ResolvesTypeSection;
use Webtack\Ecms\Http\Controllers\Controller;
use Webtack\Ecms\Http\Requests\Admin\CloneContentTypeRequest;
use Webtack\Ecms\Http\Requests\Admin\StoreContentTypeRequest;
use Webtack\Ecms\Http\Requests\Admin\UpdateContentTypeRequest;
use Webtack\Ecms\Models\ContentField;
use Webtack\Ecms\Models\ContentType;
use Webtack\Ecms\Models\File;
use Webtack\Ecms\Models\UserFavoriteType;
use Webtack\Ecms\Reference\ReferenceUsage;
use Webtack\Ecms\Support\Table\TablePage;
use Webtack\Ecms\Support\Table\TableRequest;

/**
 * Admin pages of content types of one kind (`content`, `page` or `block`): list, create, edit and delete.
 *
 * The kind comes from the route (`/admin/content-types/**`, `/admin/page-types/**` or `/admin/block-types/**`, see
 * `routes/web.php`). The `{contentType}` route parameter is bound by `key` and only within that
 * kind, so other kinds answer 404 on every route here. Types of kinds `page` and `block` have no slug.
 *
 * Only types of the section's kind are listed loaded with
 * their field, node and child-type counts in one query and handed to `TablePage::fromCollection()`,
 * so search, sorting and paging follow the same rules as the client table. No column is
 * filterable, so filters in the address are ignored. Search covers name, description, slug and key.
 * Without an explicit `sort` the rows are ordered by name.
 *
 * @example
 *   // GET /admin/content-types?q=art&sort=nodes_count&dir=desc
 */
class ContentTypeController extends Controller
{
    use PresentsFormFields, ResolvesTranslationLanguage, ResolvesTypeSection;

    public function __construct(
        private readonly FieldTypeRegistry $registry,
        private readonly ContentValueReader $reader,
        private readonly SystemFields $systemFields,
        private readonly FileStorage $files,
        private readonly NodeListSettings $listSettings,
        private readonly PublicDisplaySettings $publicSettings,
        private readonly TranslatedLanguages $translatedLanguages,
        private readonly FileDescriptions $fileDescriptions,
    ) {}

    public function index(Request $request): Response
    {
        $kind = $this->kind($request);
        $access = app(AccessResolver::class);
        $manage = $access->allows($request->user(), 'types');
        $table = TableRequest::from($request, self::columns($kind, $manage));

        $types = ContentType::query()
            ->ofKind($kind)
            ->withCount(['fields', 'nodes', 'children'])
            ->get();
        // Parent names come from the whole kind, also when the list below is narrowed to the user's types.
        $names = $types->pluck('name', 'key');

        // Without `types` the list only leads to the nodes: it holds the types the user may write, and is
        // closed (403) when there are none.
        if (! $manage) {
            $types = $types->filter(fn (ContentType $type) => $access->nodeLevel($request->user(), $type) !== AccessResolver::NONE);
            abort_if($types->isEmpty(), 403);
        }

        return $this->typesPage($request, $kind, $table, $this->rows($request, $types, $names), $manage);
    }

    /**
     * The "Child types" tab: the types table restricted to the **direct** children of a type, with the same
     * columns, row menu and favorites as {@see self::index()}. Routed behind `can:types`, so management is always on.
     *
     * @example
     *   // GET /admin/content-types/common/children?q=inst
     */
    public function children(Request $request, ContentType $contentType): Response
    {
        $kind = $this->kind($request);
        $table = TableRequest::from($request, self::columns($kind, true));
        $children = $contentType->children()->withCount(['fields', 'nodes', 'children'])->get();

        return $this->typesPage(
            $request,
            $kind,
            $table,
            $this->rows($request, $children, collect([$contentType->key => $contentType->name])),
            true,
            ['parent' => $contentType->only(['key', 'name', 'slug']), 'type' => $contentType->navProps()],
        );
    }

    /**
     * Renders the types table page (shared by the list and the "Child types" tab).
     *
     * @param  Collection<int, array<string, mixed>>  $rows
     * @param  array<string, mixed>  $extra  Extra props (`parent` and `type` on the "Child types" tab).
     */
    private function typesPage(Request $request, string $kind, TableRequest $table, Collection $rows, bool $manage, array $extra = []): Response
    {
        return Inertia::render('Admin/ContentTypes/Index', [
            'table' => TablePage::fromCollection($rows, $table),
            'section' => $this->section($request),
            'canManage' => $manage,
            // Parent select of the clone dialog (same shape as on the create form); only for those who can clone.
            'parentOptions' => $manage ? self::parentOptions($kind) : [],
            // Keys of the user's favorite types; the star in each row reads it.
            'favoriteKeys' => UserFavoriteType::query()->where('user_id', $request->user()->id)->pluck('type_key')->all(),
            ...$extra,
        ]);
    }

    /**
     * Table rows of the given types (loaded with the field, node and child counts), ordered by name.
     *
     * @param  Collection<int, ContentType>  $types
     * @param  Collection<string, string>  $names  Type names by key, to show the parent name without a query per row.
     * @return Collection<int, array<string, mixed>>
     */
    private function rows(Request $request, Collection $types, Collection $names): Collection
    {
        $access = app(AccessResolver::class);

        return $types
            ->map(fn (ContentType $type) => [
                'key' => $type->key,
                'name' => $type->name,
                'slug' => $type->slug,
                // The key links to the parent; the name is the sortable, displayed column value.
                'parent_type' => $type->parent_type,
                'parent_name' => $type->parent_type === null ? null : ($names[$type->parent_type] ?? $type->parent_type),
                'fields_count' => $type->fields_count,
                'nodes_count' => $type->nodes_count,
                // A column for those who manage types; also used by the delete dialog.
                'children_count' => $type->children_count,
                // Service value for the star: a type without node access cannot be put into the favorites.
                'can_favorite' => $access->nodeLevel($request->user(), $type) !== AccessResolver::NONE,
                // Server-only column: searched, shown under the name by the client.
                'description' => $type->description,
            ])
            // The collection sort is stable, so this is the order when no `sort` is requested.
            ->sortBy(fn (array $row) => mb_strtolower($row['name']))
            ->values();
    }

    /**
     * The create form. `?parent_type=` preselects the parent (the "Create" button of the "Child types" tab); it
     * is honored only for a type offered in the parent list of the kind, otherwise the form opens with no parent.
     */
    public function create(Request $request): Response
    {
        $options = self::parentOptions($this->kind($request));
        $parent = $request->query('parent_type');

        return Inertia::render('Admin/ContentTypes/Create', [
            'parentOptions' => $options,
            'defaultParent' => is_string($parent) && in_array($parent, array_column($options, 'key'), true) ? $parent : null,
            'section' => $this->section($request),
        ]);
    }

    /** Creates a type of the section's kind (its system `title` field is added by the model). */
    public function store(StoreContentTypeRequest $request): RedirectResponse
    {
        $data = $request->validated();
        unset($data['settings']);

        $settings = self::mergeSettings(null, $request->validated());

        if ($settings !== null) {
            $data['settings'] = $settings;
        }

        ContentType::create([...$data, 'kind' => $this->kind($request)]);

        return to_route($this->routeName($request, 'index'))->with('success', __('Content type created.'));
    }

    /**
     * The edit form; the type itself and its descendants are not offered as parents. `listOptions` feeds the
     * "Node list" section (see {@see NodeListSettings::formOptions()}).
     */
    public function edit(Request $request, ContentType $contentType): Response
    {
        $nodeFields = ValueOwner::forNodesOfType($contentType->key)->fields($contentType->fields->keyBy('key'));

        return Inertia::render('Admin/ContentTypes/Edit', [
            'type' => [...$contentType->navProps(), ...$contentType->only(['parent_type', 'description'])],
            'listOptions' => $this->listSettings->formOptions($contentType, $nodeFields),
            'parentOptions' => self::parentOptions($contentType->kind, [$contentType->key, ...$contentType->descendantKeys()]),
            'section' => $this->section($request),
        ]);
    }

    /**
     * The "Display" tab of a type of any kind: the public display settings (`publicOptions`) and, for kind
     * `content`, the form of the `section_*` values of the type itself. For `page` and `block` the value
     * lists are empty (page types hold `meta_*` values on the "SEO" tab).
     *
     * With `?lang=` of an active additional language the page is the translation mode of the `section_*` values; a type
     * that holds none of them (every `page` and `block`) answers 404 for `lang`.
     *
     * @example
     *   // GET /admin/content-types/news/display
     *   // GET /admin/content-types/news/display?lang=en
     */
    public function displayTab(Request $request, ContentType $contentType): Response
    {
        $nodeFields = ValueOwner::forNodesOfType($contentType->key)->fields($contentType->fields->keyBy('key'));

        return Inertia::render('Admin/ContentTypes/Display', [
            'type' => $contentType->navProps(),
            'section' => $this->section($request),
            'publicOptions' => $this->publicSettings->formOptions($contentType, $nodeFields),
            ...$this->typeValuesProps($contentType, 'section', $this->resolveTranslationLang($request)),
        ]);
    }

    /**
     * The "SEO" tab of a content or page type: the form of the `meta_*` values of the type itself and the
     * "Meta data" block (`metaOptions`: which fields feed the page meta data).
     * Routed for kinds `content` and `page` only, so `block` answers 404. `?lang=` opens the translation mode of the
     * `meta_*` values.
     *
     * @example
     *   // GET /admin/content-types/news/seo
     *   // GET /admin/content-types/news/seo?lang=en
     */
    public function seoTab(Request $request, ContentType $contentType): Response
    {
        $nodeFields = ValueOwner::forNodesOfType($contentType->key)->fields($contentType->fields->keyBy('key'));

        return $this->valuesPage($request, $contentType, 'seo', 'Admin/ContentTypes/Seo', [
            'metaOptions' => $this->publicSettings->metaOptions($contentType, $nodeFields),
        ]);
    }

    /**
     * Renders a values tab with the props of its panel only, plus any extra props.
     *
     * @param  array<string, mixed>  $extra
     */
    private function valuesPage(Request $request, ContentType $contentType, string $panel, string $component, array $extra = []): Response
    {
        return Inertia::render($component, [
            'type' => $contentType->navProps(),
            'section' => $this->section($request),
            ...$this->typeValuesProps($contentType, $panel, $this->resolveTranslationLang($request)),
            ...$extra,
        ]);
    }

    /**
     * Props of one panel (`section` or `seo`): the definitions of its fields (each with its `panel`),
     * their base-language values and the known files among them. The fields come from the owner of the
     * type's values: kind `content` has both panels, `page` has `seo` only, `block` has none (empty lists).
     *
     * With `$lang` (an active additional language) the prefilled values of the panel go to `translation.values` (the
     * translation of a field, else its base value) and `typeFiles` describes them; `translation` is `null` when the
     * type holds no values in the panel (there is nothing to translate; `$lang` then answers 404).
     *
     * @return array{typeFields: list<array<string, mixed>>, typeValues: object, typeFiles: object, translation: array<string, mixed>|null}
     */
    private function typeValuesProps(ContentType $type, string $panel, ?string $lang = null): array
    {
        $owner = ValueOwner::forType($type);
        // The owner decides which fields the type holds values for (`content`: `meta_*` and `section_*`,
        // `page`: `meta_*`, `block`: none), so no kind check is needed here.
        $fields = $owner->fields($type->fields->keyBy('key'))
            ->filter(fn (ContentField $field) => $this->systemFields->panel($field->key) === $panel);

        abort_if($lang !== null && $fields->isEmpty(), 404);

        $stored = $fields->isEmpty() ? [] : $this->reader->loadForType($type, $fields->keys()->all());
        $translation = null;
        // The values the page shows: the base ones, or in the translation mode the prefilled ones.
        $shown = $stored;

        if ($lang !== null) {
            $own = $this->reader->loadForType($type, $fields->keys()->all(), $lang);
            $prefilled = $fields->map(fn (ContentField $field) => $this->hasStoredValue($field, $own[$field->key] ?? null) ? $own[$field->key] : ($stored[$field->key] ?? null))->all();
            $translation = ['values' => $prefilled, 'exists' => $fields->contains(fn (ContentField $field) => $this->hasStoredValue($field, $own[$field->key] ?? null))];
            $shown = $prefilled;
        }

        $fileValues = $fields
            ->filter(fn (ContentField $field) => $this->isFileField($field))
            ->map(fn (ContentField $field) => [$this->registry->get($field->type)->profile(), $shown[$field->key] ?? null])
            ->filter(fn (array $pair) => is_string($pair[1]));
        $known = File::query()->whereIn('uuid', $fileValues->map(fn (array $pair) => substr($pair[1], 0, 32))->all())->get()->keyBy('uuid');
        // In the translation mode a name and a description are the translated ones, else the base ones.
        $translatedDescriptions = $lang === null ? [] : $this->fileDescriptions->forFiles($known->keys()->all(), $lang);
        $translatedNames = $lang === null ? [] : $this->fileDescriptions->namesForFiles($known->keys()->all(), $lang);

        return [
            'typeFields' => $fields->map(fn (ContentField $field) => [
                ...$this->formFieldData($field),
                'panel' => $this->systemFields->panel($field->key),
            ])->values()->all(),
            'typeValues' => (object) $stored,
            'typeFiles' => (object) $fileValues->mapWithKeys(fn (array $pair) => [$pair[1] => [
                'uuid' => substr($pair[1], 0, 32),
                'name' => $translatedNames[substr($pair[1], 0, 32)] ?? $known[substr($pair[1], 0, 32)]?->original_name ?? $pair[1],
                'description' => $translatedDescriptions[substr($pair[1], 0, 32)] ?? $known[substr($pair[1], 0, 32)]?->description,
                'mime' => $known[substr($pair[1], 0, 32)]?->mime ?? '',
                'size' => $known[substr($pair[1], 0, 32)]?->size ?? 0,
                'url' => $this->files->urlForValue($pair[0], $pair[1]),
            ]])->all(),
            'translation' => $fields->isEmpty() ? null : $this->translationProp(
                $lang,
                $this->translatedLanguages->forType($type),
                $translation['values'] ?? null,
                $translation['exists'] ?? false,
            ),
        ];
    }

    /** Whether a value read for a field is a stored one (not `null`, not an empty list, not blank by its field type). */
    private function hasStoredValue(ContentField $field, mixed $value): bool
    {
        $type = $this->registry->get($field->type);

        return $field->is_multiple
            ? collect((array) $value)->contains(fn ($item) => ! $type->isBlank($item))
            : $value !== null && ! $type->isBlank($value);
    }

    /**
     * Updates name, slug, parent, description and the list settings; `key` and `kind` never change. A request
     * without `settings.list` leaves `settings` untouched; `settings.public` has its own tab
     * ({@see ContentTypeDisplayController}). Stays on the edit form (`back()`) with a success flash.
     */
    public function update(UpdateContentTypeRequest $request, ContentType $contentType): RedirectResponse
    {
        $data = $request->validated();
        unset($data['settings']);

        if (Arr::has($request->validated(), 'settings.list')) {
            $data['settings'] = self::mergeSettings($contentType->settings, $request->validated());
        }

        $contentType->update($data);

        return back()->with('success', __('Content type updated.'));
    }

    /**
     * Clones a type with its field definitions and settings ({@see ContentTypeCloner}) under the entered name,
     * key, slug and parent, then opens the fields page of the new type.
     *
     * @example
     *   // POST /admin/content-types/news/clone  name=Press&key=press&slug=press
     */
    public function clone(CloneContentTypeRequest $request, ContentType $contentType, ContentTypeCloner $cloner): RedirectResponse
    {
        try {
            $new = $cloner->clone($contentType, $request->validated());
        } catch (ThemeTemplateCopyException $e) {
            return back()->withInput()->with('error', __('The type was not cloned: the theme template :path in the theme :theme already exists or cannot be written.', [
                'path' => $e->path,
                'theme' => $e->theme,
            ]));
        }

        return to_route($this->routeName($request, 'fields.index'), $new->key)->with('success', __('Content type cloned.'));
    }

    /**
     * Deletes a type together with its fields. A type that has nodes or child types is kept:
     * this guard lives in the admin, not in the model, whose cascade other code paths may use. The request flag
     * `from_children` (sent by the "Child types" tab) redirects to the tab of the parent type instead of the list.
     */
    public function destroy(Request $request, ContentType $contentType): RedirectResponse
    {
        if ($contentType->nodes()->exists()) {
            return back()->with('error', __('Cannot delete a content type that has nodes.'));
        }

        if ($contentType->children()->exists()) {
            return back()->with('error', __('Cannot delete a content type that has child types.'));
        }

        $places = app(ReferenceUsage::class)->forType($contentType);

        if ($places->isNotEmpty()) {
            return back()->with('error', __('Cannot delete a content type that is referenced: :places.', [
                'places' => $places->map(fn (array $place) => "{$place['field_key']} ({$place['content_type']}): {$place['count']}")->implode(', '),
            ]));
        }

        // One transaction: the type, its fields and the role levels of the type go together.
        DB::transaction(fn () => $contentType->delete());

        // A row deleted on the "Child types" tab (`from_children`) returns to the tab of its parent.
        if ($request->boolean('from_children') && $contentType->parent_type !== null) {
            return to_route($this->routeName($request, 'children'), $contentType->parent_type)->with('success', __('Content type deleted.'));
        }

        return to_route($this->routeName($request, 'index'))->with('success', __('Content type deleted.'));
    }

    /**
     * The type's `settings` with only the `list` key replaced by the submitted list settings, so other keys
     * survive. Defaults are not stored: a `visible` without columns besides `title`, empty `visible` and
     * `search`, and the sort "creation date, ascending" (which is the order without settings) are dropped; a `list`
     * left empty is removed, and empty `settings` become `null`.
     *
     * @param  array<string, mixed>|null  $settings  The stored settings.
     * @param  array<string, mixed>|null  $list  The submitted `settings.list`.
     * @return array<string, mixed>|null
     *
     * @example
     *   self::mergeListSettings(['x' => 1], ['visible' => ['price'], 'sort' => ['field' => 'created_at', 'direction' => 'asc']]);
     *   // ['x' => 1, 'list' => ['visible' => ['price']]]
     */
    private static function mergeListSettings(?array $settings, ?array $list): ?array
    {
        $clean = [];

        // `title` is stored only as a position marker beside other columns; alone it means the default.
        $visible = array_values($list['visible'] ?? []);

        if (array_diff($visible, ['title']) === []) {
            $visible = [];
        }

        $search = array_values($list['search'] ?? []);
        $sort = $list['sort'] ?? null;

        if ($visible !== []) {
            $clean['visible'] = $visible;
        }

        if ($search !== []) {
            $clean['search'] = $search;
        }

        if (is_array($sort) && ($sort['field'] ?? null) !== null) {
            $direction = $sort['direction'] ?? 'asc';

            if (! ($sort['field'] === 'created_at' && $direction === 'asc')) {
                $clean['sort'] = ['field' => $sort['field'], 'direction' => $direction];
            }
        }

        return self::replaceSetting($settings, 'list', $clean);
    }

    /**
     * Applies the submitted `settings.list` (only when present) to the stored settings.
     *
     * @param  array<string, mixed>|null  $settings  The stored settings.
     * @param  array<string, mixed>  $validated  The validated request data.
     * @return array<string, mixed>|null
     */
    private static function mergeSettings(?array $settings, array $validated): ?array
    {
        if (Arr::has($validated, 'settings.list')) {
            $settings = self::mergeListSettings($settings, Arr::get($validated, 'settings.list'));
        }

        return $settings;
    }

    /**
     * Replaces one key of `settings` with the cleaned value, or removes it when that is empty; empty settings become `null`.
     *
     * @param  array<string, mixed>|null  $settings
     * @param  array<string, mixed>  $clean
     * @return array<string, mixed>|null
     */
    public static function replaceSetting(?array $settings, string $key, array $clean): ?array
    {
        $settings ??= [];
        unset($settings[$key]);

        if ($clean !== []) {
            $settings[$key] = $clean;
        }

        return $settings === [] ? null : $settings;
    }

    /**
     * Types of the given kind that can be chosen as a parent, ordered by name.
     *
     * @param  list<string>  $exclude  Keys to leave out.
     * @return list<array{key: string, name: string, slug: string|null}> `slug` lets the form show the prefix a child slug needs
     *
     * @example
     *   self::parentOptions('content', ['news']); // [['key' => 'article', 'name' => 'Article', 'slug' => 'article']]
     */
    private static function parentOptions(string $kind, array $exclude = []): array
    {
        return ContentType::query()
            ->ofKind($kind)
            ->whereNotIn('key', $exclude)
            ->get(['key', 'name', 'slug'])
            ->sortBy(fn (ContentType $type) => mb_strtolower($type->name))
            ->map(fn (ContentType $type) => ['key' => $type->key, 'name' => $type->name, 'slug' => $type->slug])
            ->values()
            ->all();
    }

    /**
     * Table columns; the `slug` column exists only for kind `content` (types of other kinds have no slug), and the
     * child types column only for those who manage types (it links to a page that needs the `types` right).
     * `parent_name` is the displayed parent, so it sorts by what the user sees.
     *
     * @return list<array<string, mixed>>
     */
    private static function columns(string $kind, bool $manage): array
    {
        return [
            ['key' => 'name', 'type' => 'text', 'sortable' => true],
            ['key' => 'key', 'type' => 'text', 'sortable' => true],
            ...($kind === 'content' ? [['key' => 'slug', 'type' => 'text', 'sortable' => true]] : []),
            ['key' => 'parent_name', 'type' => 'text', 'sortable' => true, 'searchable' => false],
            ...($manage ? [['key' => 'children_count', 'type' => 'number', 'sortable' => true]] : []),
            ['key' => 'fields_count', 'type' => 'number', 'sortable' => true],
            ['key' => 'nodes_count', 'type' => 'number', 'sortable' => true],
            ['key' => 'description', 'type' => 'text'],
        ];
    }
}
