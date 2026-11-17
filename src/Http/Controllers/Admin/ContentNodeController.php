<?php

namespace Webtack\Ecms\Http\Controllers\Admin;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Webtack\Ecms\Content\ContentLanguages;
use Webtack\Ecms\Content\ContentNodeCreator;
use Webtack\Ecms\Content\ContentNodeQuery;
use Webtack\Ecms\Content\ContentValueReader;
use Webtack\Ecms\Content\ContentValueWriter;
use Webtack\Ecms\Content\FieldTypeRegistry;
use Webtack\Ecms\Content\FieldTypes\AbstractFileFieldType;
use Webtack\Ecms\Content\FieldTypes\TracksFiles;
use Webtack\Ecms\Content\FieldTypes\YoutubeType;
use Webtack\Ecms\Content\HtmlSanitizer;
use Webtack\Ecms\Content\NodeListSettings;
use Webtack\Ecms\Content\NodeQueryParams;
use Webtack\Ecms\Content\SystemFields;
use Webtack\Ecms\Content\TranslatedLanguages;
use Webtack\Ecms\Content\TranslationSaver;
use Webtack\Ecms\Content\ValueOwner;
use Webtack\Ecms\Files\AttachedFileGuard;
use Webtack\Ecms\Files\FileDescriptions;
use Webtack\Ecms\Files\FileStorage;
use Webtack\Ecms\Http\Controllers\Admin\Concerns\PresentsFormFields;
use Webtack\Ecms\Http\Controllers\Admin\Concerns\ResolvesTranslationLanguage;
use Webtack\Ecms\Http\Controllers\Admin\Concerns\ResolvesTypeSection;
use Webtack\Ecms\Http\Controllers\Controller;
use Webtack\Ecms\Http\Requests\Admin\SaveNodeTranslationRequest;
use Webtack\Ecms\Http\Requests\Admin\StoreContentNodeRequest;
use Webtack\Ecms\Http\Requests\Admin\UpdateContentNodeRequest;
use Webtack\Ecms\Models\AppLanguage;
use Webtack\Ecms\Models\ContentField;
use Webtack\Ecms\Models\ContentNode;
use Webtack\Ecms\Models\ContentType;
use Webtack\Ecms\Models\File;
use Webtack\Ecms\Models\UserFavoriteType;
use Webtack\Ecms\Reference\ReferenceLabels;
use Webtack\Ecms\Reference\ReferenceUsage;
use Webtack\Ecms\Support\Table\TablePage;
use Webtack\Ecms\Support\Table\TableRequest;

/**
 * Admin pages of the nodes of a type of any kind (`content`, `page`, `block`). The forms write the base language
 * (`default`); translations are edited one language at a time in the translation mode (`?lang=` on the edit page,
 * `saveTranslation` / `clearTranslation`, see `docs/admin-translation-mode.md`).
 *
 * The `{contentType}` route parameter is bound by `key` within the kind of the section (the route's `kind`
 * default) and `{node}` by the pair (type, `uuid`) (see `routes/web.php`), so types of other kinds, foreign nodes
 * and unknown uuids answer 404; redirects go back to the node list of the same section. A node is
 * never addressed by `slug`: that is the public identifier of the site and may change.
 *
 * Unlike `ContentTypeController`, the list is not an in-memory collection: search, sorting and paging run in
 * the database through {@see ContentNodeQuery}; `TableRequest` only parses the address and `TablePage::make()`
 * wraps the fetched page.
 *
 * @example
 *   // GET /admin/content-types/news/nodes?q=lamp&sort=price&dir=desc&page=2
 */
class ContentNodeController extends Controller
{
    use PresentsFormFields, ResolvesTranslationLanguage, ResolvesTypeSection;

    /** Longest visible text of a `richtext` value shown in a table cell. */
    private const RICHTEXT_CELL_LIMIT = 200;

    /** Error keys of the service attributes: they live beside `values`, not inside it. */
    private const SERVICE_KEYS = ['slug', 'is_active', 'published_at', 'unpublished_at'];

    /** Table column type per field type; unknown (newly registered) types fall back to `text`. */
    private const COLUMN_TYPES = [
        'string' => 'text',
        'text' => 'text',
        'integer' => 'number',
        'decimal' => 'number',
        'boolean' => 'boolean',
        'date' => 'date',
        'datetime' => 'date',
        // Only the client renders these two (a thumbnail, a link); the table sees them as text.
        'image' => 'image',
        'file' => 'file',
    ];

    public function __construct(
        private readonly FieldTypeRegistry $registry,
        private readonly ContentNodeQuery $query,
        private readonly ContentNodeCreator $creator,
        private readonly ContentValueWriter $writer,
        private readonly ContentValueReader $reader,
        private readonly FileStorage $files,
        private readonly HtmlSanitizer $html,
        private readonly SystemFields $systemFields,
        private readonly NodeListSettings $listSettings,
        private readonly ReferenceLabels $references,
        private readonly TranslationSaver $translations,
        private readonly TranslatedLanguages $translatedLanguages,
        private readonly FileDescriptions $fileDescriptions,
        private readonly AttachedFileGuard $attachedFiles,
    ) {}

    /**
     * The list of the type's nodes.
     *
     * Columns are `title` (always first) and then `settings.list.visible` in its order: fields that still exist
     * and the service keys (`slug`, `status`, `is_active`, `created_at`, `updated_at`, `published_at`,
     * `unpublished_at`, `views`, `author`); without `visible` the default is `title`, slug, state, publication date and author. Stale keys in
     * the type's list settings (a deleted field) are dropped, never an error. Each row carries its `uuid`, the
     * service values and a value per visible field (a multiple field is joined with commas).
     */
    public function index(Request $request, ContentType $contentType): Response
    {
        Gate::authorize('viewAny', [ContentNode::class, $contentType]);

        $fields = $this->nodeFields($contentType);
        $list = $this->listSettings->clean($contentType, $fields);
        $visible = $list['visible'];
        // Only fields hold node values; the service keys are read from the node itself.
        $fieldKeys = $visible->reject(fn (string $key) => $this->listSettings->isServiceKey($key))->values();

        // The Translations column and its filter exist only while additional languages are active.
        $activeLanguages = app(ContentLanguages::class)->active();
        $table = TableRequest::from($request, [
            ...$this->tableColumns($visible, $fields),
            ...($activeLanguages === [] ? [] : [['key' => 'translations', 'type' => 'select', 'filterable' => true, 'options' => $activeLanguages]]),
        ]);
        $untranslated = collect($table->filters)->firstWhere('column', 'translations')['value'] ?? null;

        // ContentNodeQuery falls back to the type's own list settings, so it must see the cleaned ones.
        $type = clone $contentType;
        $type->settings = [...($contentType->settings ?? []), 'list' => $list];

        $page = $this->paginate($type, $fieldKeys, $table, $untranslated);
        $translatedByNode = $activeLanguages === [] ? [] : $this->translatedLanguages->forNodes(collect($page->items())->pluck('id')->all());
        // One query for the authors of the page, and only when the column is shown.
        if ($visible->contains('author')) {
            $page->getCollection()->load('author:id,name,avatar');
        }
        $pageValues = collect($page->items())->map(fn (ContentNode $node) => $node->values ?? []);
        $visibleFields = $fieldKeys->map(fn (string $key) => $fields[$key]);
        // Files are keyed by `{uuid}.{ext}` and terms by their hyphenated uuid, so one map serves both.
        $files = $this->fileData($pageValues, $visibleFields);
        $names = $this->termNames($pageValues, $visibleFields);
        // Targets of `type_ref` / `node_ref` values: the label and the edit address shown in the cell.
        $refs = $this->references->forValues($visibleFields, $pageValues);

        $kind = $this->kind($request);

        return Inertia::render('Admin/ContentNodes/Index', [
            'type' => $contentType->navProps(),
            'section' => $this->section($request),
            // Whether the type is in the user's "Favorites" (the star of the page title).
            'isFavorite' => UserFavoriteType::query()->where('user_id', $request->user()->id)->where('type_key', $contentType->key)->exists(),
            'columns' => [
                ...$visible->map(fn (string $key) => $this->column($key, $fields))->values(),
                ...($activeLanguages === [] ? [] : [['key' => 'translations', 'name' => 'Translations', 'type' => 'text', 'sortable' => false, 'service' => true]]),
            ],
            'table' => TablePage::make(
                collect($page->items())->map(fn (ContentNode $node) => [
                    ...$this->row($node, $fieldKeys, $fields, $files, $names, $refs, $this->publicUrl($kind, $contentType, $node)),
                    ...($activeLanguages === [] ? [] : ['translations' => $translatedByNode[$node->id] ?? []]),
                ]),
                $page->total(),
                $table,
            ),
            ...($activeLanguages === [] ? [] : ['translationLanguages' => $this->languageOptions($activeLanguages)]),
        ]);
    }

    /**
     * The author of a node as `{name, avatar}` (`avatar` is the picture URL or null), or null. Read only when the
     * page loaded the authors, so a hidden column costs no queries.
     *
     * @return array{name: string, avatar: string|null}|null
     */
    private function authorProps(ContentNode $node): ?array
    {
        return $node->relationLoaded('author') && $node->author
            ? ['name' => $node->author->name, 'avatar' => $node->author->avatarUrl()]
            : null;
    }

    /**
     * The fixed slug prefix of the section's nodes shown by the form: `block-` for blocks, empty otherwise.
     *
     * @example
     *   // GET /admin/block-types/hero/nodes/create → 'block-'
     */
    private function slugPrefix(Request $request): string
    {
        return $this->kind($request) === 'block' ? ContentNode::BLOCK_PREFIX : '';
    }

    /**
     * Relative public address of a stored node, built from the saved slugs (see docs/public-site.md):
     * `/{slug}` for a page, `/{type slug}/{slug}` for a `content` node, `null` for a block (no own address)
     * and for a `content` type without a public slug. Shared by the edit form (`publicUrl` prop) and the table rows
     * (`public_url`), so the clients never rebuild the rule.
     *
     * @param  string  $kind  Kind of the section: `content`, `page` or `block`.
     *
     * @example
     *   // page `about` → '/about'; `hello` of a type with slug `news` → '/news/hello'; a block → null
     */
    private function publicUrl(string $kind, ContentType $contentType, ContentNode $node): ?string
    {
        return match ($kind) {
            'page' => '/'.$node->slug,
            'content' => $contentType->slug ? '/'.$contentType->slug.'/'.$node->slug : null,
            default => null,
        };
    }

    /** The create form: the type's fields, each with an empty value. */
    public function create(Request $request, ContentType $contentType): Response
    {
        Gate::authorize('create', [ContentNode::class, $contentType]);

        return Inertia::render('Admin/ContentNodes/Create', [
            'type' => $contentType->navProps(),
            'section' => $this->section($request),
            'slugPrefix' => $this->slugPrefix($request),
            'node' => [
                'slug' => '',
                'is_active' => true,
                'published_at' => now()->format(StoreContentNodeRequest::PUBLISHED_AT_FORMAT),
                'unpublished_at' => null,
                'updated_at' => null,
                'author' => ['name' => $request->user()->name, 'avatar' => $request->user()->avatarUrl()],
            ],
            'fields' => $this->formFields($contentType),
        ]);
    }

    /**
     * Creates a node. The author is the current user; `is_active` defaults to true and an empty `published_at` to now. An empty slug is generated from the title; the values are validated and written by
     * `ContentNodeCreator` in the base language, and its errors come back keyed like the form (`values.title`).
     */
    public function store(StoreContentNodeRequest $request, ContentType $contentType): RedirectResponse
    {
        try {
            DB::transaction(function () use ($request, $contentType) {
                $node = $this->creator->create(
                    $contentType,
                    $request->input('values', []),
                    $request->validated('slug'),
                    authorId: $request->user()->id,
                    publishedAt: $this->publishedAt($request->validated('published_at')),
                    isActive: $request->boolean('is_active', true),
                    unpublishedAt: $this->publishedAt($request->validated('unpublished_at')),
                );

                $this->applyFileMeta($node, $contentType, $request->validated('file_meta') ?? []);
                // Every file of a new node is new: refuse one the user may not attach (rolls the creation back).
                $this->attachedFiles->assertAllowed($request->user(), ValueOwner::forNode($node), []);
            });
        } catch (ValidationException $e) {
            throw $this->forForm($e);
        }

        return to_route($this->routeName($request, 'nodes.index'), $contentType->key)->with('success', __('Node created.'));
    }

    /**
     * The edit form, filled with the node's `slug` and its values in the base language.
     *
     * With `?lang=` of an active additional language the page is the translation mode: `fields` holds every field, each
     * flagged `translatable`, `values` the prefilled values of the translatable ones (base values for the rest) and
     * `translation.values` the prefilled values of the translatable ones (the translation of a field, else its base
     * value); `files` and `labels` then describe those values, and the `description` of every file are the translated
     * ones (else the base ones) for the file inputs of the translation form. The `translation` prop is `null` when no
     * switcher is shown.
     */
    public function edit(Request $request, ContentType $contentType, ContentNode $node): Response
    {
        Gate::authorize('update', $node);

        $lang = $this->resolveTranslationLang($request);
        $stored = $this->reader->load([$node], null)[$node->id];
        $fields = $this->formFields($contentType);
        $node->loadMissing('author:id,name,avatar');

        $translation = null;
        if ($lang !== null) {
            $translatable = $this->nodeFields($contentType)->filter(fn (ContentField $field) => $field->is_translatable);
            // The form keeps its layout: every field stays, the non-translatable ones are flagged and shown disabled.
            $fields = array_map(fn (array $field) => [...$field, 'translatable' => $translatable->has($field['key'])], $fields);
            $own = $this->reader->load([$node], $translatable->keys()->all(), $lang)[$node->id];
            $prefilled = $this->prefilled($translatable, $stored, $own);
            $translation = [
                'values' => collect($fields)->filter(fn (array $field) => $field['translatable'])->mapWithKeys(fn (array $field) => [$field['key'] => $this->formValue($field, $prefilled[$field['key']] ?? null)])->all(),
                'exists' => $translatable->contains(fn (ContentField $field) => $this->hasValue($field, $own[$field->key] ?? null)),
            ];
            $stored = [...$stored, ...$prefilled];
        }

        return Inertia::render('Admin/ContentNodes/Edit', [
            'type' => $contentType->navProps(),
            'section' => $this->section($request),
            'slugPrefix' => $this->slugPrefix($request),
            'publicUrl' => $this->publicUrl($this->kind($request), $contentType, $node),
            'node' => [
                'id' => $node->id,
                'uuid' => $node->uuid,
                'slug' => $node->slug,
                'is_active' => $node->is_active,
                'published_at' => $node->published_at->format(StoreContentNodeRequest::PUBLISHED_AT_FORMAT),
                'unpublished_at' => $node->unpublished_at?->format(StoreContentNodeRequest::PUBLISHED_AT_FORMAT),
                'updated_at' => $node->updated_at?->format(StoreContentNodeRequest::PUBLISHED_AT_FORMAT),
                'author' => $this->authorProps($node),
            ],
            'fields' => $fields,
            'values' => collect($fields)->mapWithKeys(fn (array $field) => [
                $field['key'] => $this->formValue($field, $stored[$field['key']] ?? null),
            ]),
            'files' => (object) $this->withTranslatedFileData($this->fileInfo($stored, $contentType->fields), $lang),
            'labels' => (object) $this->referenceLabels($stored, $contentType->fields),
            'translation' => $this->translationProp(
                $lang,
                $this->translatedLanguages->forNodes([$node->id])[$node->id],
                $translation['values'] ?? null,
                $translation['exists'] ?? false,
            ),
        ]);
    }

    /**
     * Saves the translated values of one language (the translation mode): only translatable fields, required is not
     * enforced, a value equal to the base is not stored and an emptied field loses its translation. `file_meta` carries
     * the translated descriptions of the node's files (any `image` or `file` field, translatable or not; a file that is
     * not the node's is an error). The node is touched, so `updated_at` moves. Stays on the translation form. Errors come back keyed like the form (`values.title`).
     */
    public function saveTranslation(SaveNodeTranslationRequest $request, ContentType $contentType, ContentNode $node, string $lang): RedirectResponse
    {
        $lang = $this->activeTranslationLang($lang) ?? abort(404);

        try {
            DB::transaction(function () use ($request, $node, $lang) {
                $owner = ValueOwner::forNode($node);
                $before = $this->attachedFiles->snapshot($owner);

                $this->translations->save($node, $lang, $request->input('values', []));
                $this->translations->saveFileDescriptions($node, $lang, $request->validated('file_meta') ?? []);
                $this->attachedFiles->assertAllowed($request->user(), $owner, $before);
                $node->touch();
            });
        } catch (ValidationException $e) {
            throw $this->forForm($e);
        }

        return to_route($this->routeName($request, 'nodes.edit'), [$contentType->key, $node->uuid, 'lang' => $lang])->with('success', __('Translation saved.'));
    }

    /** Removes every value of one language from the node (the "Clear translation" action) and returns to the same form. */
    public function clearTranslation(Request $request, ContentType $contentType, ContentNode $node, string $lang): RedirectResponse
    {
        Gate::authorize('update', $node);

        $lang = $this->activeTranslationLang($lang) ?? abort(404);

        DB::transaction(function () use ($node, $lang) {
            $this->translations->clear($node, $lang);
            $node->touch();
        });

        return to_route($this->routeName($request, 'nodes.edit'), [$contentType->key, $node->uuid, 'lang' => $lang])->with('success', __('Translation cleared.'));
    }

    /**
     * The starting values of a translation form: for each translatable field its own stored value, else the base value.
     *
     * @param  Collection<string, ContentField>  $translatable
     * @param  array<string, mixed>  $base  Base values by field key.
     * @param  array<string, mixed>  $own  Values of the language by field key.
     * @return array<string, mixed>
     */
    private function prefilled(Collection $translatable, array $base, array $own): array
    {
        return $translatable->map(fn (ContentField $field) => $this->hasValue($field, $own[$field->key] ?? null)
            ? $own[$field->key]
            : ($base[$field->key] ?? null))->all();
    }

    /** Whether a read value is a value at all (not `null`, not an empty list, not blank by its field type). */
    private function hasValue(ContentField $field, mixed $value): bool
    {
        $type = $this->registry->get($field->type);

        return $field->is_multiple
            ? collect((array) $value)->contains(fn ($item) => ! $type->isBlank($item))
            : $value !== null && ! $type->isBlank($value);
    }

    /**
     * Saves the slug (when it changed), `is_active`, `published_at` and `unpublished_at` (cleared by an empty value), and replaces the values of every field the
     * form sent, in one transaction: a cleared field loses its value, other languages are untouched and `uuid` and
     * the author never change. The node is touched last, so `updated_at` moves even when only values changed.
     * Stays on the edit form (`back()`) with a success flash.
     */
    public function update(UpdateContentNodeRequest $request, ContentType $contentType, ContentNode $node): RedirectResponse
    {
        try {
            DB::transaction(function () use ($request, $contentType, $node) {
                $node->fill([
                    'slug' => $request->validated('slug'),
                    'is_active' => $request->boolean('is_active', $node->is_active),
                    'published_at' => $this->publishedAt($request->validated('published_at')),
                ]);

                // An empty value clears the end date; an absent key leaves it untouched.
                if ($request->has('unpublished_at')) {
                    $node->unpublished_at = $this->publishedAt($request->validated('unpublished_at'));
                }

                $node->save();

                // A file new in the values must be one the user may attach; a refusal rolls the whole save back.
                $owner = ValueOwner::forNode($node);
                $before = $this->attachedFiles->snapshot($owner);

                $this->writer->write($node, $request->input('values', []));
                $this->applyFileMeta($node, $contentType, $request->validated('file_meta') ?? []);
                $this->attachedFiles->assertAllowed($request->user(), $owner, $before);
                $node->touch();
            });
        } catch (ValidationException $e) {
            throw $this->forForm($e);
        }

        return back()->with('success', __('Node updated.'));
    }

    /**
     * Sets only `is_active` of the node (the one-click badge in the list). The dates and values stay as they are, so
     * enabling a node shows the state that follows from its dates. Authorized like editing; `updated_at` moves.
     * Answers with `back()` and a success flash.
     *
     * @example
     *   // PATCH /admin/content-types/article/nodes/0190…/active  is_active=0
     */
    public function setActive(Request $request, ContentType $contentType, ContentNode $node): RedirectResponse
    {
        Gate::authorize('update', $node);

        $active = $request->validate(['is_active' => ['required', 'boolean']])['is_active'];

        $node->forceFill(['is_active' => $active])->save();
        $node->touch();

        return back()->with('success', $active ? __('Node enabled.') : __('Node disabled.'));
    }

    /**
     * Writes the name and description the form sent into the files the node's stored values refer to.
     *
     * Only files of the node count (the uuids come from its saved values, not from the request), so a foreign
     * uuid in `file_meta` is ignored. The name is cleaned like an uploaded one (`basename`, 255 chars) and an
     * empty description becomes `NULL`. The file, its path and its extension are not touched.
     *
     * @param  array<string, array{name: string, description?: string|null}>  $meta  By file uuid.
     *
     * @example
     *   $this->applyFileMeta($node, $type, ['0190a1b27c3e7f0a8b1c2d3e4f5a6b7c' => ['name' => 'Cover', 'description' => null]]);
     */
    private function applyFileMeta(ContentNode $node, ContentType $type, array $meta): void
    {
        if ($meta === []) {
            return;
        }

        $stored = $this->reader->load([$node], null)[$node->id];
        $uuids = [];

        foreach ($type->fields as $field) {
            $fieldType = $this->registry->get($field->type);

            if ($fieldType instanceof TracksFiles) {
                array_push($uuids, ...$fieldType->fileUuids($stored[$field->key] ?? null));
            }
        }

        $own = array_intersect_key($meta, array_flip($uuids));

        foreach (File::query()->whereIn('uuid', array_keys($own))->get() as $file) {
            $description = trim((string) ($own[$file->uuid]['description'] ?? ''));

            $file->update([
                'original_name' => Str::limit(basename(str_replace('\\', '/', $own[$file->uuid]['name'])), 255, ''),
                'description' => $description === '' ? null : $description,
            ]);
        }
    }

    /**
     * Deletes the node; the database cascades its values in every language. A node that other nodes or types
     * reference through `node_ref` values is kept, with the places named in the message.
     */
    public function destroy(Request $request, ContentType $contentType, ContentNode $node): RedirectResponse
    {
        Gate::authorize('delete', $node);

        $places = app(ReferenceUsage::class)->forNode($node);

        if ($places->isNotEmpty()) {
            return back()->with('error', __('Cannot delete a node that is referenced: :places.', [
                'places' => $places->map(fn (array $place) => "{$place['field_key']} ({$place['content_type']}): {$place['count']}")->implode(', '),
            ]));
        }

        $node->delete();

        return to_route($this->routeName($request, 'nodes.index'), $contentType->key)->with('success', __('Node deleted.'));
    }

    /**
     * The type's fields in the shape the form needs, ordered by weight. `settings` carries `min`/`max` for input
     * attributes and, for a file field, the effective `mimes` and `max_size` (its profile narrowed by the field);
     * the server stays the judge of the values.
     *
     * @return list<array{key: string, name: string, description: string|null, type: string, is_multiple: bool, max_items: int, is_required: bool, panel?: string, settings: array<string, mixed>}>
     */
    private function formFields(ContentType $type): array
    {
        return $this->nodeFields($type)->map(fn (ContentField $field) => [
            ...$this->formFieldData($field),
            // Metadata fields are shown in their own panel (`seo`) instead of among the main fields.
            ...($field->is_system && $this->systemFields->panel($field->key) === 'seo' ? ['panel' => 'seo'] : []),
        ])->values()->all();
    }

    /**
     * The fields whose values belong to nodes, by key and in weight order. Fields that only the type
     * itself holds (`section_*`) are left out: nodes have no values for them.
     *
     * @return Collection<string, ContentField>
     */
    private function nodeFields(ContentType $type): Collection
    {
        return ValueOwner::forNodesOfType($type->key)->fields($type->fields->keyBy('key'));
    }

    /**
     * What the edit form shows for the files in the stored values: one query for the whole page, and the URL of
     * each is built from the value itself. A file missing in `media` shows its stored string.
     *
     * @param  array<string, mixed>  $stored  Stored values by field key.
     * @param  Collection<int, ContentField>  $fields
     * @return array<string, array{uuid: string, name: string, description: string|null, mime: string, size: int, url: string, preview: string|null}>
     */
    private function fileInfo(array $stored, Collection $fields): array
    {
        // [profile, value] of every file: the value of a file field, the screenshot of a `youtube` value.
        $values = $fields->flatMap(function (ContentField $field) use ($stored) {
            $type = $this->registry->get($field->type);

            if ($type instanceof AbstractFileFieldType) {
                return array_map(fn (string $value) => [$type->profile(), $value], array_filter((array) ($stored[$field->key] ?? []), 'is_string'));
            }

            if ($type instanceof YoutubeType) {
                return array_map(
                    fn (string $value) => [$type->uploadProfile(), $value],
                    array_filter(array_map(fn ($item) => is_array($item) ? ($item['screen'] ?? null) : null, $this->composedItems($field, $stored[$field->key] ?? null)), 'is_string'),
                );
            }

            return [];
        })->values();

        $uuids = $values->map(fn (array $pair) => substr($pair[1], 0, 32))->unique()->all();
        $known = $uuids === [] ? collect() : File::query()->whereIn('uuid', $uuids)->get()->keyBy('uuid');

        $info = [];

        foreach ($values as [$profile, $value]) {
            $file = $known[substr($value, 0, 32)] ?? null;

            $info[$value] = [
                'uuid' => substr($value, 0, 32),
                'name' => $file?->original_name ?? $value,
                'description' => $file?->description,
                'mime' => $file?->mime ?? '',
                'size' => $file?->size ?? 0,
                'url' => $this->files->urlForValue($profile, $value),
                'preview' => $this->files->previewForValue($profile, $value, $file?->mime ?? ''),
            ];
        }

        return $info;
    }

    /**
     * In the translation mode the name and the description of each file are the translated ones, else the base ones (the
     * prefill of the inputs; the original name is only replaced in what is shown); the base mode keeps the base values.
     *
     * @param  array<string, array<string, mixed>>  $info  File info by stored value, see {@see self::fileInfo()}.
     * @return array<string, array<string, mixed>>
     */
    private function withTranslatedFileData(array $info, ?string $lang): array
    {
        if ($lang === null || $info === []) {
            return $info;
        }

        $uuids = array_values(array_unique(array_column($info, 'uuid')));
        $descriptions = $this->fileDescriptions->forFiles($uuids, $lang);
        $names = $this->fileDescriptions->namesForFiles($uuids, $lang);

        return array_map(fn (array $file) => [
            ...$file,
            'name' => $names[$file['uuid']] ?? $file['name'],
            'description' => $descriptions[$file['uuid']] ?? $file['description'],
        ], $info);
    }

    /**
     * The composed values of a field as a list: one value, the list of a multiple field, or none.
     *
     * @return list<mixed>
     */
    private function composedItems(ContentField $field, mixed $stored): array
    {
        if ($stored === null) {
            return [];
        }

        return $field->is_multiple ? array_values((array) $stored) : [$stored];
    }

    /**
     * What the list shows for the files in a page, by stored `{uuid}.{ext}` value, in one query: the URL, the
     * original name and the lowercase extension. A value whose file is missing in `media` is absent from the map.
     *
     * @param  Collection<int, array<string, mixed>>  $pageValues  Values by field key, per node.
     * @param  Collection<int, ContentField>  $visibleFields
     * @return array<string, array{url: string, name: string, ext: string, preview: string|null}>
     *
     * @example
     *   $this->fileData($pageValues, $visibleFields); // ['0190….pdf' => ['url' => '/storage/0190….pdf', 'name' => 'Report.pdf', 'ext' => 'pdf', 'preview' => null]]
     */
    private function fileData(Collection $pageValues, Collection $visibleFields): array
    {
        $fields = $visibleFields->filter(fn (ContentField $field) => $this->isFileField($field));

        // [profile, value] of every file shown.
        $pairs = $pageValues->flatMap(fn (array $nodeValues) => $fields->flatMap(function (ContentField $field) use ($nodeValues) {
            $profile = $this->registry->get($field->type)->profile();

            return array_map(fn (string $value) => [$profile, $value], array_filter((array) ($nodeValues[$field->key] ?? []), 'is_string'));
        }));

        if ($pairs->isEmpty()) {
            return [];
        }

        $known = File::query()->whereIn('uuid', $pairs->map(fn (array $pair) => substr($pair[1], 0, 32))->unique()->all())->get(['uuid', 'original_name', 'mime'])->keyBy('uuid');
        $data = [];

        foreach ($pairs as [$profile, $value]) {
            $file = $known[substr($value, 0, 32)] ?? null;

            if ($file !== null) {
                $data[$value] = [
                    'url' => $this->files->urlForValue($profile, $value),
                    'name' => $file->original_name,
                    'ext' => strtolower(pathinfo($value, PATHINFO_EXTENSION)),
                    'preview' => $this->files->previewForValue($profile, $value, $file->mime),
                ];
            }
        }

        return $data;
    }

    /**
     * Base-language names of the terms shown in a page of the list, by term uuid, in one query.
     *
     * @param  Collection<int, array<string, mixed>>  $pageValues  Values by field key, per node.
     * @param  Collection<int, ContentField>  $visibleFields
     * @return array<string, string>
     *
     * @example
     *   $this->termNames($pageValues, $visibleFields); // ['0190…' => 'Jackets']
     */
    private function termNames(Collection $pageValues, Collection $visibleFields): array
    {
        $keys = $visibleFields->filter(fn (ContentField $field) => $field->type === 'term')->pluck('key');

        $uuids = $pageValues
            ->flatMap(fn (array $nodeValues) => $keys->flatMap(fn (string $key) => array_filter((array) ($nodeValues[$key] ?? []), 'is_string')))
            ->unique()
            ->values();

        if ($uuids->isEmpty()) {
            return [];
        }

        return DB::table('taxonomy_term_translations')
            ->whereIn('term_uuid', $uuids->all())
            ->where('lang', config('content.default_lang'))
            ->pluck('name', 'term_uuid')
            ->all();
    }

    /**
     * Labels of the targets in the stored values of the edit form, by stored value (a type key or a node uuid), so
     * that a `type_ref` / `node_ref` widget shows the name or title before any search. A gone target has no entry.
     *
     * @param  array<string, mixed>  $stored  Stored values by field key.
     * @param  Collection<int, ContentField>  $fields
     * @return array<string, string>
     *
     * @example
     *   $this->referenceLabels(['source' => 'hero'], $fields); // ['hero' => 'Hero banner']
     */
    private function referenceLabels(array $stored, Collection $fields): array
    {
        $targets = $this->references->forValues($fields, [$stored]);

        return array_map(fn (array $target) => $target['label'], [...$targets['type_ref'], ...$targets['node_ref']]);
    }

    /**
     * A stored value in the form of its HTML input: `date` as `Y-m-d`, `datetime` as `Y-m-d\TH:i`
     * (`datetime-local`), a multiple field as a list, a missing single value as `null`.
     *
     * @param  array{type: string, is_multiple: bool}  $field
     */
    private function formValue(array $field, mixed $value): mixed
    {
        $one = fn (mixed $item) => $item instanceof CarbonInterface
            ? $item->format($field['type'] === 'date' ? 'Y-m-d' : 'Y-m-d\TH:i')
            : $item;

        return $field['is_multiple'] ? array_map($one, $value ?? []) : ($value === null ? null : $one($value));
    }

    /** Parses the `published_at` of a form (UTC, no conversion); `null` for an empty value. */
    private function publishedAt(?string $value): ?CarbonImmutable
    {
        return $value === null ? null : CarbonImmutable::createFromFormat(StoreContentNodeRequest::PUBLISHED_AT_FORMAT, $value, 'UTC');
    }

    /**
     * Re-keys errors of the writer (`title`, `scores.1`) to the form's nesting (`values.title`,
     * `values.scores.1`); the service attributes (`slug`, `is_active`, `published_at`, `unpublished_at`) already match.
     */
    private function forForm(ValidationException $e): ValidationException
    {
        $errors = [];

        foreach ($e->errors() as $key => $messages) {
            $errors[in_array($key, self::SERVICE_KEYS, true) || str_starts_with($key, 'file_meta.') ? $key : "values.$key"] = $messages;
        }

        return ValidationException::withMessages($errors);
    }

    /**
     * One column description for the client, in the order of `visible`.
     *
     * @param  Collection<string, ContentField>  $fields
     * @return array{key: string, name: string, type: string, sortable: bool, service?: bool}
     */
    private function column(string $key, Collection $fields): array
    {
        if ($this->listSettings->isServiceKey($key)) {
            [$name, $type] = NodeListSettings::SERVICE_COLUMNS[$key];

            return [
                'key' => $key,
                'name' => $name,
                'type' => $type,
                'sortable' => in_array($key, ContentType::LIST_SORT_SERVICE_KEYS, true),
                'service' => true,
            ];
        }

        return [
            'key' => $key,
            'name' => $fields[$key]->name,
            'type' => self::COLUMN_TYPES[$fields[$key]->type] ?? 'text',
            'sortable' => $this->listSettings->sortable($fields[$key]),
        ];
    }

    /**
     * Server-side column description used to validate `sort`, in the order of `visible`: the same keys, types and
     * sortability as the `columns` prop (file, `youtube`, `url` and `term` fields and the slug are not sortable).
     *
     * @param  Collection<int, string>  $visible
     * @param  Collection<string, ContentField>  $fields
     * @return list<array<string, mixed>>
     */
    private function tableColumns(Collection $visible, Collection $fields): array
    {
        return $visible->map(fn (string $key) => [
            ...Arr::only($this->column($key, $fields), ['key', 'sortable']),
            'type' => in_array($this->column($key, $fields)['type'], ['image', 'file'], true) ? 'text' : $this->column($key, $fields)['type'],
            // Searching is done by ContentNodeQuery over the type's search fields, not by the table.
            'searchable' => false,
        ])->all();
    }

    /**
     * The active languages with their names (the options of the "Without translation in" filter).
     *
     * @param  list<string>  $codes
     * @return list<array{code: string, name: string}>
     */
    private function languageOptions(array $codes): array
    {
        $names = AppLanguage::query()->whereIn('code', $codes)->pluck('name', 'code');

        return array_map(fn (string $code) => ['code' => $code, 'name' => $names[$code] ?? $code], $codes);
    }

    /**
     * Runs the query for the requested page; a page past the end is answered with the last one.
     *
     * @param  Collection<int, string>  $visible
     * @return LengthAwarePaginator<int, ContentNode>
     */
    private function paginate(ContentType $type, Collection $visible, TableRequest $table, ?string $untranslated = null): LengthAwarePaginator
    {
        $params = fn (int $page) => new NodeQueryParams(
            fields: $visible->all(),
            search: $table->search === '' ? null : $table->search,
            sort: $table->sort === null ? null : ['field' => $table->sort, 'direction' => $table->dir ?? 'asc'],
            page: $page,
            perPage: $table->perPage,
            untranslated: $untranslated,
        );

        $page = $this->query->paginate($type, $params($table->page));

        if ($page->total() > 0 && $table->page > $page->lastPage()) {
            $page = $this->query->paginate($type, $params($page->lastPage()));
        }

        return $page;
    }

    /**
     * A table row: the node's `uuid`, the service values (`slug`, derived `status`, boolean `is_active`, the dates as
     * `Y-m-d H:i` in UTC and `null` for an empty `unpublished_at`, `author` as `{name}` or `null`, and `public_url`, the relative public address or `null`) plus the
     * display value of every visible field.
     *
     * @param  Collection<int, string>  $fieldKeys  Visible keys that are fields (not service columns).
     * @param  Collection<string, ContentField>  $fields
     * @param  array<string, array{url: string, name: string, ext: string, preview: string|null}>  $files  File data by stored `{uuid}.{ext}` value.
     * @param  array<string, string>  $names  Term names by term uuid.
     * @param  array{type_ref: array<string, array{label: string, href: string}>, node_ref: array<string, array{label: string, href: string}>}  $refs  Targets of reference values (see {@see ReferenceLabels::forValues()}).
     * @param  string|null  $publicUrl  Public address of the node (see {@see self::publicUrl()}).
     * @return array<string, mixed>
     */
    private function row(ContentNode $node, Collection $fieldKeys, Collection $fields, array $files, array $names, array $refs = ['type_ref' => [], 'node_ref' => []], ?string $publicUrl = null): array
    {
        $row = [];

        foreach ($fieldKeys as $key) {
            $row[$key] = $this->cell($fields[$key], $node->values[$key] ?? null, $files, $names, $refs);
        }

        // Service values come last so that a legacy field with a service key never shadows them.
        return [
            ...$row,
            'uuid' => $node->uuid,
            // Decided on the server (`own` level: only the author's nodes), so the client never repeats the rule.
            'can_edit' => Gate::allows('update', $node),
            'slug' => $node->slug,
            'public_url' => $publicUrl,
            'status' => $node->status(),
            'is_active' => (bool) $node->is_active,
            'created_at' => $node->created_at?->format('Y-m-d H:i'),
            'updated_at' => $node->updated_at?->format('Y-m-d H:i'),
            'published_at' => $node->published_at->format('Y-m-d H:i'),
            'unpublished_at' => $node->unpublished_at?->format('Y-m-d H:i'),
            'views' => (int) $node->views,
            'author' => $this->authorProps($node),
        ];
    }

    /**
     * The display value of a stored one. A multiple field is a list of such values (an empty one is `null`), a
     * missing value is `null`; a boolean stays boolean (the client prints «Yes»/«No»); `image` is `{url, name, preview}`,
     * `file` is `{url, name, ext}`, or the stored string when the file is gone from `media`; `url` is `{url, title}`;
     * `type_ref` / `node_ref` is `{label, href}` of the target, or the stored string when the target is gone; everything else
     * is text.
     *
     * @param  array<string, array{url: string, name: string, ext: string, preview: string|null}>  $files
     * @param  array<string, string>  $names  Term names by term uuid.
     * @param  array{type_ref?: array<string, array{label: string, href: string}>, node_ref?: array<string, array{label: string, href: string}>}  $refs
     *
     * @example
     *   // multiple `tags` ['a', 'b'] → ['a', 'b']; `file` → ['url' => '/storage/….pdf', 'name' => 'Report.pdf', 'ext' => 'pdf']
     */
    private function cell(ContentField $field, mixed $value, array $files = [], array $names = [], array $refs = []): mixed
    {
        if ($value === null || $value === []) {
            return null;
        }

        if ($field->is_multiple) {
            return array_values(array_map(fn ($item) => $this->item($field, $item, $files, $names, $refs), (array) $value));
        }

        return $this->item($field, $value, $files, $names, $refs);
    }

    /**
     * One stored value of a field in its display form (see {@see self::cell()}).
     *
     * @param  array<string, array{url: string, name: string, ext: string, preview: string|null}>  $files
     * @param  array<string, string>  $names
     * @param  array{type_ref?: array<string, array{label: string, href: string}>, node_ref?: array<string, array{label: string, href: string}>}  $refs
     */
    private function item(ContentField $field, mixed $value, array $files, array $names, array $refs = []): mixed
    {
        if ($this->isFileField($field)) {
            $file = $files[$value] ?? null;

            return match (true) {
                $file === null => (string) $value,
                $field->type === 'image' => Arr::only($file, ['url', 'name', 'preview']),
                default => $file,
            };
        }

        if ($field->type === 'url') {
            return ['url' => (string) ($value['url'] ?? ''), 'title' => $value['title'] ?? null];
        }

        if (in_array($field->type, ['type_ref', 'node_ref'], true)) {
            return $refs[$field->type][$value] ?? (string) $value;
        }

        return is_bool($value) ? $value : $this->text($field, $value, $names);
    }

    /**
     * One stored value as text; a term shows its name (or the stored uuid when it is gone), and rich text its
     * visible text without tags, shortened.
     *
     * @param  array<string, string>  $names
     */
    private function text(ContentField $field, mixed $value, array $names = []): string
    {
        return match (true) {
            $field->type === 'term' => $names[$value] ?? (string) $value,
            in_array($field->type, ['youtube', 'url'], true) => (string) (($value['title'] ?? '') !== '' ? $value['title'] : ($value['url'] ?? '')),
            $field->type === 'richtext' => Str::limit($this->html->text((string) $value), self::RICHTEXT_CELL_LIMIT),
            $value instanceof CarbonInterface => $value->format($field->type === 'date' ? 'Y-m-d' : 'Y-m-d H:i'),
            default => (string) $value,
        };
    }
}
