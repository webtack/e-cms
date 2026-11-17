<?php

namespace Webtack\Ecms\Models;

use Closure;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Webtack\Ecms\Content\NodeListSettings;
use Webtack\Ecms\Content\PublicDisplaySettings;
use Webtack\Ecms\Content\PublicPaths;
use Webtack\Ecms\Content\SystemFields;
use Webtack\Ecms\Content\TypeSlugs;
use Webtack\Ecms\Content\ValueOwner;
use Webtack\Ecms\Database\Factories\ContentTypeFactory;
use Webtack\Ecms\Files\FileRelations;
use Webtack\Ecms\Reference\ReferenceUsage;

/**
 * A content type: a named set of fields that content nodes are created from.
 *
 * The `key` is the stable identifier of a type: unique, immutable and referenced by
 * fields, nodes and child types (never the numeric `id`). The `kind` classifies the type
 * (content, page, block) and cannot change after creation. Creating a type also creates
 * its system fields (`title`, and the metadata and section fields its kind gets, see
 * {@see SystemFields}) in the same transaction.
 *
 * @property int $id
 * @property string $key
 * @property string $name
 * @property string|null $slug Public site path of the type's node list; only for kind `content`, unique.
 * @property string|null $description
 * @property string|null $parent_type Key of the parent type (organisational tree only).
 * @property string $kind
 * @property array<string, mixed>|null $settings
 *
 * @example
 *   $type = ContentType::create(['key' => 'article', 'name' => 'Article']);
 *   $type->fields; // contains the system "title" field
 */
#[UseFactory(ContentTypeFactory::class)]
#[Fillable(['key', 'name', 'slug', 'description', 'parent_type', 'kind', 'settings'])]
class ContentType extends Model
{
    /** @use HasFactory<ContentTypeFactory> */
    use HasFactory;

    /** Format of type and field keys. */
    public const KEY_PATTERN = '/^[a-z][a-z0-9_]*$/';

    /**
     * Type keys that cannot be used for a new type: they are the names of service folders of a site theme
     * (a type key becomes the name of its template folder). Only new types are checked, the key is immutable.
     *
     * @example
     *   in_array('pages', ContentType::RESERVED_KEYS, true);      // true: rejected
     *   in_array('pages_list', ContentType::RESERVED_KEYS, true); // false: only a full match counts
     */
    public const RESERVED_KEYS = ['content', 'pages', 'blocks', 'fields', 'menus', 'errors'];

    /** `media_relations.owner_type` of a type (see `Webtack\Ecms\Files\FileRelations`). */
    public const MEDIA_OWNER = 'content_type';

    /**
     * Format of the public type slug: one or more segments separated by `/`, each in the node slug format.
     *
     * @example `news`, `common/institutions`; not `/news`, `news/`, `a//b`, `a/-b`
     */
    public const SLUG_PATTERN = '/^[a-z0-9]+(-[a-z0-9]+)*(\/[a-z0-9]+(-[a-z0-9]+)*)*$/';

    /** Values of `settings.sitemap.xml.changefreq` (the sitemaps.org protocol). */
    public const SITEMAP_CHANGEFREQ = ['always', 'hourly', 'daily', 'weekly', 'monthly', 'yearly', 'never'];

    /** Service attributes of a node a list can show as columns, besides the type's fields (`settings.list.visible`); `author` is shown but not sortable. */
    public const LIST_SERVICE_KEYS = ['slug', 'status', 'is_active', 'created_at', 'updated_at', 'published_at', 'unpublished_at', 'views', 'author'];

    /** Service attributes a list can be sorted by (`settings.list.sort.field`): all of the above except the slug. */
    public const LIST_SORT_SERVICE_KEYS = ['status', 'is_active', 'created_at', 'updated_at', 'published_at', 'unpublished_at', 'views'];

    protected $attributes = ['kind' => 'content'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['settings' => 'array'];
    }

    protected static function booted(): void
    {
        static::saving(function (self $type) {
            $type->assertValid();
        });

        // The type's database cascade (fields, nodes, values) fires no model events, so the file
        // relations of the type's own values are forgotten here, while the type still exists.
        static::deleting(function (self $type) {
            if (app(ReferenceUsage::class)->forType($type)->isNotEmpty()) {
                throw ValidationException::withMessages(['key' => 'A content type that is referenced by field values cannot be deleted.']);
            }

            app(FileRelations::class)->forgetOwner(self::MEDIA_OWNER, $type->id);

            // Role levels of the type go with it, so a later type with the same key starts at `none`.
            // The caller wraps delete() in a transaction (ContentTypeController::destroy).
            RolePermission::query()->where('scope', 'node:'.$type->key)->delete();
        });

        static::created(function (self $type) {
            // Runs inside the transaction opened by save(), so a failure rolls the type back.
            foreach (app(SystemFields::class)->forKind($type->kind) as $definition) {
                $type->fields()->getRelated()->forceFill([
                    'content_type' => $type->key,
                    'key' => $definition['key'],
                    'name' => $definition['name'],
                    'type' => $definition['type'],
                    'is_required' => $definition['required'],
                    'is_multiple' => false,
                    'is_translatable' => true,
                    'is_system' => true,
                    'weight' => $definition['weight'],
                ])->save();
            }
        });
    }

    /**
     * Save the type and, on creation, its system title field atomically.
     *
     * @param  array<string, mixed>  $options
     */
    public function save(array $options = []): bool
    {
        return DB::transaction(fn () => parent::save($options));
    }

    /**
     * Find a type by its key.
     *
     * @example ContentType::findByKey('article') // ContentType|null
     */
    public static function findByKey(string $key): ?self
    {
        return static::query()->where('key', $key)->first();
    }

    /** @return HasMany<ContentField, $this> */
    public function fields(): HasMany
    {
        return $this->hasMany(ContentField::class, 'content_type', 'key')->orderBy('weight')->orderBy('id');
    }

    /** @return HasMany<ContentNode, $this> */
    public function nodes(): HasMany
    {
        return $this->hasMany(ContentNode::class, 'content_type', 'key');
    }

    /** @return HasMany<ContentType, $this> */
    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_type', 'key');
    }

    /**
     * The `type` prop of the admin section menu (`ContentTypeNav`): the identity of the type, the number of its
     * direct child types and its ancestors from the root down to the direct parent. The ancestor walk stops when
     * a key repeats or a parent is missing, so corrupted data cannot loop it.
     *
     * @return array{key: string, name: string, slug: string|null, children_count: int, ancestors: list<array{key: string, name: string}>}
     *
     * @example
     *   $deep->navProps();
     *   // ['key' => 'deep', 'name' => 'Deep', 'slug' => null, 'children_count' => 0,
     *   //  'ancestors' => [['key' => 'common', 'name' => 'Common'], ['key' => 'institutions', 'name' => 'Institutions']]]
     */
    public function navProps(): array
    {
        $ancestors = [];
        $seen = [$this->key => true];

        for ($key = $this->parent_type; $key !== null && ! isset($seen[$key]);) {
            $seen[$key] = true;
            $parent = static::findByKey($key);

            if ($parent === null) {
                break;
            }

            array_unshift($ancestors, ['key' => $parent->key, 'name' => $parent->name]);
            $key = $parent->parent_type;
        }

        return [
            'key' => $this->key,
            'name' => $this->name,
            'slug' => $this->slug,
            'children_count' => $this->children()->count(),
            'ancestors' => $ancestors,
        ];
    }

    /**
     * Keys of all descendants of this type (children, grandchildren, ...), breadth first.
     *
     * @return list<string>
     *
     * @example
     *   $news->descendantKeys(); // ['news_sport', 'news_football']
     */
    public function descendantKeys(): array
    {
        $keys = [];
        $level = [$this->key];

        while ($level !== []) {
            $level = static::query()->whereIn('parent_type', $level)->pluck('key')->all();
            $keys = [...$keys, ...$level];
        }

        return $keys;
    }

    /**
     * Restrict a query to the types of one kind.
     *
     * @param  Builder<ContentType>  $query
     */
    public function scopeOfKind(Builder $query, string $kind): void
    {
        $query->where('kind', $kind);
    }

    /**
     * Restrict a query to the direct children of a parent type.
     *
     * @param  Builder<ContentType>  $query
     */
    public function scopeChildrenOf(Builder $query, string $parentKey): void
    {
        $query->where('parent_type', $parentKey);
    }

    /**
     * Restrict a query to root types (those without a parent).
     *
     * @param  Builder<ContentType>  $query
     */
    public function scopeRoots(Builder $query): void
    {
        $query->whereNull('parent_type');
    }

    /**
     * Validate the format and immutability rules before the type is saved.
     *
     * @throws ValidationException
     */
    private function assertValid(): void
    {
        // An empty slug means "no public list"; store it as NULL so the unique index ignores it.
        if ($this->slug === '') {
            $this->slug = null;
        }

        Validator::make($this->getAttributes(), [
            'key' => ['required', 'string', 'max:64', 'regex:'.self::KEY_PATTERN],
            'name' => ['required', 'string', 'max:255'],
            'kind' => ['required', 'in:'.implode(',', config('content.kinds'))],
            'slug' => [
                'nullable', 'string', 'max:190', 'regex:'.self::SLUG_PATTERN,
                Rule::unique('content_types', 'slug')->ignore($this->getKey()),
            ],
        ])->validate();

        if ($this->slug !== null && $this->kind !== 'content') {
            throw ValidationException::withMessages(['slug' => 'Only content types can have a slug.']);
        }

        // The first segment is reserved or shared with page slugs, and the slug may not equal a node address; checked only when the slug is new or changed.
        if ($this->slug !== null && (! $this->exists || $this->isDirty('slug'))
            && $reason = app(PublicPaths::class)->typeSlugConflict($this->slug, $this->exists ? $this : null)) {
            throw ValidationException::withMessages(['slug' => PublicPaths::message($reason)]);
        }

        if (! $this->exists && in_array($this->key, self::RESERVED_KEYS, true)) {
            throw ValidationException::withMessages(['key' => 'This key is reserved.']);
        }

        if ($this->exists) {
            foreach (['key', 'kind'] as $immutable) {
                if ($this->isDirty($immutable)) {
                    throw ValidationException::withMessages([$immutable => "The type {$immutable} cannot be changed."]);
                }
            }
        }

        if ($this->parent_type !== null) {
            $this->assertValidParent();
        }

        $this->assertValidSlugHierarchy();

        $this->assertValidListSettings();
        $this->assertValidPublicSettings();
        $this->assertValidSitemapSettings();
    }

    /**
     * The slug must start with the parent's slug (checked on creation and when the slug or the parent changes),
     * and the slug of a type cannot change while a direct child has a slug ({@see TypeSlugs}).
     *
     * @throws ValidationException
     */
    private function assertValidSlugHierarchy(): void
    {
        $slugs = app(TypeSlugs::class);

        if (! $this->exists || $this->isDirty('slug') || $this->isDirty('parent_type')) {
            if ($message = $slugs->hierarchyError($this->slug, $this->parent_type)) {
                throw ValidationException::withMessages(['slug' => $message]);
            }
        }

        if ($this->exists && $this->isDirty('slug') && $this->getOriginal('slug') !== null
            && $message = $slugs->childrenBlockSlugChange($this)) {
            throw ValidationException::withMessages(['slug' => $message]);
        }
    }

    /**
     * `settings.list` may only reference fields that belong to this type, plus the service
     * attributes of a node: {@see self::LIST_SERVICE_KEYS} as columns and
     * {@see self::LIST_SORT_SERVICE_KEYS} as the sort field. `search` takes fields only. A type being
     * created has only its automatic system fields.
     *
     * @throws ValidationException
     */
    private function assertValidListSettings(): void
    {
        $list = $this->settings['list'] ?? null;

        if ($list === null) {
            return;
        }

        $known = $this->exists
            ? $this->fields()->pluck('key')->all()
            : array_column(app(SystemFields::class)->forKind($this->kind), 'key');
        // Fields of the type itself hold no node values, so a list cannot show, search or sort by them.
        $known = array_filter($known, fn (string $key) => app(SystemFields::class)->owns(null, $key, SystemFields::OWNER_NODE));
        $referenced = [
            ...array_diff((array) ($list['visible'] ?? []), self::LIST_SERVICE_KEYS, ['title']),
            ...(array) ($list['search'] ?? []),
            ...(isset($list['sort']['field']) && ! in_array($list['sort']['field'], self::LIST_SORT_SERVICE_KEYS, true) ? [$list['sort']['field']] : []),
        ];

        if ($unknown = array_diff($referenced, $known)) {
            throw ValidationException::withMessages([
                'settings' => 'The list settings reference unknown fields: '.implode(', ', $unknown).'.',
            ]);
        }

        $direction = $list['sort']['direction'] ?? 'asc';

        if (! in_array($direction, ['asc', 'desc'], true)) {
            throw ValidationException::withMessages(['settings' => 'The list sort direction must be asc or desc.']);
        }
    }

    /**
     * `settings.public` may only reference "added" fields of this type (user fields, `is_system = false`, holding
     * node values, with no reserved key): `list_fields` and `page_fields` take them, `sort.field` takes a sortable
     * one or {@see self::LIST_SORT_SERVICE_KEYS}. `per_page` is an integer in `[0, content.max_per_page]` and
     * `pagination` a boolean. `list_fields`, `sort`, `per_page`, `pagination` and `archive` exist for kind `content` only.
     * A type being created has no added fields yet.
     *
     * @throws ValidationException
     *
     * @example
     *   // kind page: settings.public = ['per_page' => 5] → ValidationException on `settings`
     *   // kind content with field `price`: settings.public = ['list_fields' => ['price'], 'per_page' => 10] → valid
     */
    private function assertValidPublicSettings(): void
    {
        $public = $this->settings['public'] ?? null;

        if ($public === null) {
            return;
        }

        $fail = fn (string $message) => throw ValidationException::withMessages(['settings' => $message]);

        if (! is_array($public)) {
            $fail('The public settings must be an object.');
        }

        if ($unknownKeys = array_diff(array_keys($public), ['list_fields', 'page_fields', 'sort', 'per_page', 'pagination', 'archive', 'meta'])) {
            $fail('The public settings have unknown keys: '.implode(', ', $unknownKeys).'.');
        }

        if ($this->kind !== 'content' && $listKeys = array_intersect(array_keys($public), PublicDisplaySettings::LIST_KEYS)) {
            $fail('The public settings of a '.$this->kind.' cannot have: '.implode(', ', $listKeys).'.');
        }

        if ($this->kind === 'block' && array_key_exists('meta', $public)) {
            $fail('The public settings of a block cannot have: meta.');
        }

        $added = $this->exists
            ? app(PublicDisplaySettings::class)->addedFields(
                ValueOwner::forNodesOfType($this->key)->fields($this->fields()->get()->keyBy('key'))
            )
            : collect();

        foreach (['list_fields', 'page_fields'] as $key) {
            if (! array_key_exists($key, $public)) {
                continue;
            }

            if (! is_array($public[$key]) || ! array_is_list($public[$key]) || array_filter($public[$key], fn ($v) => ! is_string($v))) {
                $fail("The public {$key} must be a list of field keys.");
            }

            if ($unknown = array_diff($public[$key], $added->keys()->all())) {
                $fail("The public {$key} reference unknown or non-added fields: ".implode(', ', $unknown).'.');
            }
        }

        if (array_key_exists('sort', $public)) {
            $sort = $public['sort'];
            $field = is_array($sort) ? ($sort['field'] ?? null) : null;

            if (! is_string($field)) {
                $fail('The public sort needs a field.');
            }

            if (! in_array($field, self::LIST_SORT_SERVICE_KEYS, true)
                && ! ($added->has($field) && app(NodeListSettings::class)->sortable($added[$field]))) {
                $fail("The public sort references an unknown or unsortable field: {$field}.");
            }

            if (! in_array($sort['direction'] ?? 'desc', ['asc', 'desc'], true)) {
                $fail('The public sort direction must be asc or desc.');
            }
        }

        if (array_key_exists('archive', $public)) {
            $this->assertValidPublicArchive($public['archive'], $added, $fail);
        }

        if (array_key_exists('per_page', $public)
            && (! is_int($public['per_page']) || $public['per_page'] < 0 || $public['per_page'] > config('content.max_per_page'))) {
            $fail('The public per_page must be an integer from 0 to '.config('content.max_per_page').'.');
        }

        if (array_key_exists('pagination', $public) && ! is_bool($public['pagination'])) {
            $fail('The public pagination must be a boolean.');
        }

        if (array_key_exists('meta', $public)) {
            $this->assertValidPublicMeta($public['meta'], $added, $fail);
        }
    }

    /**
     * `settings.sitemap` is `{xml: {type?, nodes?, changefreq?, priority?}, web: {type?, nodes?}}` (see `docs/type-sitemap.md`).
     * A `page` type has no list page, so it cannot have `type`; a `block` has no public address, so it cannot have
     * `settings.sitemap` at all. `changefreq` / `priority` make sense only while an XML checkbox is on.
     *
     * @throws ValidationException
     *
     * @example
     *   // kind content: settings.sitemap = ['xml' => ['nodes' => true, 'changefreq' => 'monthly', 'priority' => '0.8']] → valid
     *   // kind page: settings.sitemap = ['web' => ['type' => true]] → ValidationException on `settings`
     */
    private function assertValidSitemapSettings(): void
    {
        $sitemap = $this->settings['sitemap'] ?? null;

        if ($sitemap === null) {
            return;
        }

        $fail = fn (string $message) => throw ValidationException::withMessages(['settings' => $message]);

        if ($this->kind === 'block') {
            $fail('A block cannot have sitemap settings.');
        }

        if (! is_array($sitemap) || ($sitemap !== [] && array_is_list($sitemap)) || array_diff(array_keys($sitemap), ['xml', 'web'])) {
            $fail('The sitemap settings must be an object with the keys xml and web.');
        }

        foreach ($sitemap as $group => $values) {
            $allowed = $group === 'xml' ? ['type', 'nodes', 'changefreq', 'priority'] : ['type', 'nodes'];

            if (! is_array($values) || ($values !== [] && array_is_list($values)) || array_diff(array_keys($values), $allowed)) {
                $fail("The sitemap {$group} settings must be an object with the keys: ".implode(', ', $allowed).'.');
            }

            foreach (['type', 'nodes'] as $flag) {
                if (array_key_exists($flag, $values) && ! is_bool($values[$flag])) {
                    $fail("The sitemap {$group} {$flag} must be a boolean.");
                }
            }

            if ($this->kind === 'page' && array_key_exists('type', $values)) {
                $fail('A page type has no list page, so its sitemap settings cannot have: type.');
            }

            if ($group !== 'xml') {
                continue;
            }

            if (array_key_exists('changefreq', $values) && ! in_array($values['changefreq'], self::SITEMAP_CHANGEFREQ, true)) {
                $fail('The sitemap changefreq must be one of: '.implode(', ', self::SITEMAP_CHANGEFREQ).'.');
            }

            if (array_key_exists('priority', $values) && (! is_string($values['priority']) || ! preg_match('/^(0\.\d|1\.0)$/', $values['priority']))) {
                $fail('The sitemap priority must be from 0.0 to 1.0 in steps of 0.1.');
            }

            if ((array_key_exists('changefreq', $values) || array_key_exists('priority', $values)) && empty($values['type']) && empty($values['nodes'])) {
                $fail('The sitemap changefreq and priority need the XML type or nodes checkbox.');
            }
        }
    }

    /**
     * `settings.public.archive` is `{field, sort?}`: `field` is an added `date` / `datetime` field or one of
     * {@see PublicDisplaySettings::ARCHIVE_SERVICE_KEYS}, `sort` is shaped and checked like the public `sort`.
     *
     * @param  Collection<string, ContentField>  $added
     * @param  Closure(string): never  $fail
     *
     * @example
     *   // archive = ['field' => 'date', 'sort' => ['field' => 'date', 'direction' => 'desc']] with `date` of type datetime → valid
     *   // archive = ['field' => 'published_at'] → fails; archive = ['sort' => [...]] without a field → fails
     */
    private function assertValidPublicArchive(mixed $archive, Collection $added, Closure $fail): void
    {
        if (! is_array($archive)) {
            $fail('The public archive must be an object.');
        }

        if ($unknownKeys = array_diff(array_keys($archive), ['field', 'sort'])) {
            $fail('The public archive has unknown keys: '.implode(', ', $unknownKeys).'.');
        }

        $field = $archive['field'] ?? null;

        if (! is_string($field)) {
            $fail('The public archive needs a field.');
        }

        if (! in_array($field, PublicDisplaySettings::ARCHIVE_SERVICE_KEYS, true)
            && ! ($added->has($field) && in_array($added[$field]->type, PublicDisplaySettings::ARCHIVE_TYPES, true))) {
            $fail("The public archive references an unknown field or one that is not a date: {$field}.");
        }

        if (! array_key_exists('sort', $archive)) {
            return;
        }

        $sort = $archive['sort'];
        $sortField = is_array($sort) ? ($sort['field'] ?? null) : null;

        if (! is_string($sortField)) {
            $fail('The public archive sort needs a field.');
        }

        if (! in_array($sortField, self::LIST_SORT_SERVICE_KEYS, true)
            && ! ($added->has($sortField) && app(NodeListSettings::class)->sortable($added[$sortField]))) {
            $fail("The public archive sort references an unknown or unsortable field: {$sortField}.");
        }

        if (! in_array($sort['direction'] ?? 'desc', ['asc', 'desc'], true)) {
            $fail('The public archive sort direction must be asc or desc.');
        }
    }

    /**
     * `settings.public.meta` maps `title`, `description` and `image` to the key of one added field whose type the
     * meta data allows ({@see PublicDisplaySettings::META_TYPES}).
     *
     * @param  Collection<string, ContentField>  $added
     * @param  Closure(string): never  $fail
     *
     * @example
     *   // meta = ['image' => 'cover'] with `cover` of type image → valid; with `cover` of type string → fails
     *   // meta = ['title' => 'title'] → fails (the node's own title is not an added field)
     */
    private function assertValidPublicMeta(mixed $meta, Collection $added, Closure $fail): void
    {
        if (! is_array($meta)) {
            $fail('The public meta must be an object.');
        }

        if ($unknown = array_diff(array_keys($meta), array_keys(PublicDisplaySettings::META_TYPES))) {
            $fail('The public meta has unknown keys: '.implode(', ', $unknown).'.');
        }

        foreach ($meta as $name => $key) {
            $types = PublicDisplaySettings::META_TYPES[$name];

            if (! is_string($key) || ! $added->has($key) || ! in_array($added[$key]->type, $types, true)) {
                $fail("The public meta {$name} needs an added field of type ".implode(' or ', $types).'.');
            }
        }
    }

    /**
     * The parent must exist, share the kind and must not create a cycle.
     *
     * @throws ValidationException
     */
    private function assertValidParent(): void
    {
        $parent = static::findByKey($this->parent_type);

        if ($parent === null) {
            throw ValidationException::withMessages(['parent_type' => 'The parent type does not exist.']);
        }

        if ($parent->kind !== $this->kind) {
            throw ValidationException::withMessages(['parent_type' => 'The parent type must have the same kind.']);
        }

        for ($ancestor = $parent; $ancestor !== null; $ancestor = $ancestor->parent_type ? static::findByKey($ancestor->parent_type) : null) {
            if ($ancestor->key === $this->key) {
                throw ValidationException::withMessages(['parent_type' => 'A type cannot be its own ancestor.']);
            }
        }
    }
}
