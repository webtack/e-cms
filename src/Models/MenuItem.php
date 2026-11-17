<?php

namespace Webtack\Ecms\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Webtack\Ecms\Database\Factories\MenuItemFactory;
use Webtack\Ecms\Files\FileRelations;
use Webtack\Ecms\Menus\MenuItemTree;
use Webtack\Ecms\Menus\MenuItemWriter;
use Webtack\Ecms\Menus\MenuUrl;

/**
 * An item of a menu: a node of a tree with one parent.
 *
 * Items are linked by `uuid` (immutable, UUID v7). The menu of an item is fixed at creation; the
 * parent must be in the same menu, may not make a cycle and may not push the tree below the menu's
 * `max_depth`. An item with children cannot be deleted. Titles and descriptions live in
 * {@see MenuItemTranslation}; use {@see MenuItemWriter} to write an item together with them.
 *
 * Visibility is computed, never stored: see {@see self::status()} and {@see self::scopeVisible()}.
 *
 * @property int $id
 * @property string $uuid
 * @property string $menu_key
 * @property string|null $parent_uuid
 * @property int $weight
 * @property string|null $url Relative or absolute address; null for a group heading.
 * @property string $target `_self` or `_blank`.
 * @property string|null $css_class
 * @property string|null $image `{uuid}.{ext}` of a file of the `image` profile.
 * @property bool $is_active
 * @property CarbonInterface|null $published_at
 * @property CarbonInterface|null $unpublished_at
 *
 * @example
 *   $item = MenuItem::create(['menu_key' => 'main', 'url' => '/about']);
 *   $item->status(); // 'published'
 *   MenuItem::query()->visible()->get();
 */
#[UseFactory(MenuItemFactory::class)]
#[Fillable(['uuid', 'menu_key', 'parent_uuid', 'weight', 'url', 'target', 'css_class', 'image', 'is_active', 'published_at', 'unpublished_at'])]
class MenuItem extends Model
{
    /** @use HasFactory<MenuItemFactory> */
    use HasFactory;

    /** `media_relations.owner_type` of a menu item (see `Webtack\Ecms\Files\FileRelations`). */
    public const MEDIA_OWNER = 'menu_item';

    /** Allowed `target` values. */
    public const TARGETS = ['_self', '_blank'];

    /** Statuses returned by {@see self::status()}. */
    public const STATUSES = ['published', 'scheduled', 'expired', 'disabled'];

    /** One or more class names separated by single spaces. */
    public const CSS_CLASS_PATTERN = '/^[A-Za-z0-9_-]+( [A-Za-z0-9_-]+)*$/';

    protected $table = 'menu_items';

    protected $attributes = ['weight' => 0, 'target' => '_self', 'is_active' => true];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'weight' => 'integer',
            'is_active' => 'boolean',
            'published_at' => 'datetime',
            'unpublished_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (self $item) {
            $item->assertValid();
        });

        static::deleting(function (self $item) {
            // Runs in the transaction opened by delete(); the row lock serialises with writes.
            $locked = static::query()->where('uuid', $item->uuid)->lockForUpdate()->first();

            if ($locked === null) {
                return;
            }

            if (static::query()->where('parent_uuid', $item->uuid)->exists()) {
                throw ValidationException::withMessages(['uuid' => 'A menu item that has child items cannot be deleted.']);
            }

            app(FileRelations::class)->forgetOwner(self::MEDIA_OWNER, $item->id);
        });
    }

    /**
     * Delete the item and its translations atomically.
     */
    public function delete(): ?bool
    {
        return DB::transaction(fn () => parent::delete());
    }

    /**
     * Save the item.
     *
     * @param  array<string, mixed>  $options
     */
    public function save(array $options = []): bool
    {
        return DB::transaction(fn () => parent::save($options));
    }

    /**
     * Find an item by its stable uuid.
     *
     * @example MenuItem::findByUuid($uuid) // MenuItem|null
     */
    public static function findByUuid(string $uuid): ?self
    {
        return static::query()->where('uuid', $uuid)->first();
    }

    /** @return BelongsTo<Menu, $this> */
    public function menu(): BelongsTo
    {
        return $this->belongsTo(Menu::class, 'menu_key', 'key');
    }

    /** @return BelongsTo<MenuItem, $this> */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_uuid', 'uuid');
    }

    /** @return HasMany<MenuItem, $this> */
    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_uuid', 'uuid')->orderBy('weight')->orderBy('id');
    }

    /** @return HasMany<MenuItemTranslation, $this> */
    public function translations(): HasMany
    {
        return $this->hasMany(MenuItemTranslation::class, 'item_uuid', 'uuid');
    }

    /**
     * The computed status of the item itself (ancestors are not considered): `disabled` wins over
     * the dates, then `scheduled` (not yet published), `expired` (already unpublished), else `published`.
     *
     * @example $item->status(now()->setDate(2026, 1, 1)) // 'scheduled'
     */
    public function status(?CarbonInterface $now = null): string
    {
        $now ??= now();

        return match (true) {
            ! $this->is_active => 'disabled',
            $this->published_at !== null && $this->published_at->greaterThan($now) => 'scheduled',
            $this->unpublished_at !== null && $this->unpublished_at->lessThanOrEqualTo($now) => 'expired',
            default => 'published',
        };
    }

    /**
     * Items that are visible on their own (the visibility of ancestors is not known to a flat query;
     * `Webtack\Ecms\Menus\MenuTree` prunes hidden branches).
     *
     * @param  Builder<MenuItem>  $query
     */
    public function scopeVisible(Builder $query, ?CarbonInterface $now = null): void
    {
        $now ??= now();

        $query->where('is_active', true)
            ->where(fn (Builder $q) => $q->whereNull('published_at')->orWhere('published_at', '<=', $now))
            ->where(fn (Builder $q) => $q->whereNull('unpublished_at')->orWhere('unpublished_at', '>', $now));
    }

    /**
     * @throws ValidationException
     */
    private function assertValid(): void
    {
        if ($this->exists) {
            foreach (['uuid', 'menu_key'] as $immutable) {
                if ($this->isDirty($immutable)) {
                    throw ValidationException::withMessages([$immutable => "The menu item {$immutable} cannot be changed."]);
                }
            }
        } elseif (empty($this->uuid)) {
            $this->uuid = (string) Str::uuid7();
        } else {
            Validator::make(['uuid' => $this->uuid], ['uuid' => ['uuid', 'unique:menu_items,uuid']])->validate();
        }

        $menu = Menu::query()->where('key', $this->menu_key)->first();

        if ($menu === null) {
            throw ValidationException::withMessages(['menu_key' => 'The menu does not exist.']);
        }

        // Blank values mean "not set".
        foreach (['url', 'css_class'] as $nullable) {
            if (is_string($this->{$nullable}) && trim($this->{$nullable}) === '') {
                $this->{$nullable} = null;
            }
        }

        $this->assertValidPresentation();
        $this->assertValidSchedule();

        if (! $this->exists || $this->isDirty('parent_uuid')) {
            $this->assertValidParent($menu);
        }
    }

    /**
     * @throws ValidationException
     */
    private function assertValidPresentation(): void
    {
        Validator::make($this->getAttributes(), [
            'url' => ['nullable', 'string', function (string $attribute, mixed $value, \Closure $fail) {
                if (! MenuUrl::isValid($value)) {
                    $fail('The url must be a relative address or an http, https, mailto or tel address.');
                }
            }],
            'target' => ['required', 'in:'.implode(',', self::TARGETS)],
            'css_class' => ['nullable', 'string', 'max:255', 'regex:'.self::CSS_CLASS_PATTERN],
        ])->validate();
    }

    /**
     * When both dates are set the item must be unpublished after it is published.
     *
     * @throws ValidationException
     */
    private function assertValidSchedule(): void
    {
        if ($this->published_at !== null && $this->unpublished_at !== null && $this->unpublished_at->lessThanOrEqualTo($this->published_at)) {
            throw ValidationException::withMessages(['unpublished_at' => 'The unpublish date must be later than the publish date.']);
        }
    }

    /**
     * The parent must be in the same menu, must not be the item or its descendant, and the item
     * with its whole subtree must fit into the maximum depth of the menu.
     *
     * @throws ValidationException
     */
    private function assertValidParent(Menu $menu): void
    {
        if ($this->parent_uuid === null) {
            return;
        }

        $parent = static::query()->where('uuid', $this->parent_uuid)->first();

        if ($parent === null || $parent->menu_key !== $this->menu_key) {
            throw ValidationException::withMessages(['parent_uuid' => 'The parent item must exist in the same menu.']);
        }

        $tree = app(MenuItemTree::class);
        $ancestors = $tree->ancestorUuids($parent);

        if ($parent->uuid === $this->uuid || in_array($this->uuid, $ancestors, true)) {
            throw ValidationException::withMessages(['parent_uuid' => 'An item cannot be its own ancestor.']);
        }

        $max = $menu->maxDepth();

        // Depth of the parent + this item + the levels below this item (a new item has none).
        if ($max !== null && count($ancestors) + 2 + ($this->exists ? $tree->height($this) - 1 : 0) > $max) {
            throw ValidationException::withMessages(['parent_uuid' => "The item would exceed the maximum depth of {$max}."]);
        }
    }
}
