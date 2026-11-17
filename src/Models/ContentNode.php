<?php

namespace Webtack\Ecms\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use Webtack\Ecms\Content\PublicPaths;
use Webtack\Ecms\Contracts\EcmsUser;
use Webtack\Ecms\Database\Factories\ContentNodeFactory;
use Webtack\Ecms\Files\FileRelations;
use Webtack\Ecms\Reference\ReferenceUsage;
use Webtack\Ecms\Support\UserModel;

/**
 * A content node: one record of a content type.
 *
 * `uuid` is the stable external identifier (UUID v7, immutable) used to match nodes between
 * environments; `slug` is a globally unique, language-independent identifier. Values live in
 * the `content_values_*` tables, not on the model.
 *
 * @property int $id
 * @property string $uuid
 * @property string $content_type Key of the owning content type.
 * @property string $slug
 * @property Carbon $published_at When the node becomes visible (UTC, minute precision in the admin form).
 * @property Carbon|null $unpublished_at When the node stops being visible (exclusive); null means no end.
 * @property bool $is_active Whether the node may be shown on the site at all.
 * @property int $user_id Author; set by the server on creation, never mass-assigned from HTTP.
 * @property int $views View counter (non-negative); never mass-assigned from HTTP, bumped with `incrementViews()`.
 *
 * A node is visible when `is_active`, `published_at <= now()` and `unpublished_at` is null or
 * `> now()` (see `scopeVisible()`); the derived state is returned by `status()`.
 *
 * @example
 *   $node = ContentNode::findByUuid('0190a1b2-...'); // ContentNode|null
 *   ContentNode::query()->visible()->get(); // only nodes shown on the site
 *   $node->status(); // 'disabled' | 'scheduled' | 'expired' | 'published'
 *   $node->incrementViews(); // views + 1, `updated_at` untouched
 */
#[UseFactory(ContentNodeFactory::class)]
#[Fillable(['uuid', 'content_type', 'slug', 'published_at', 'unpublished_at', 'is_active'])]
class ContentNode extends Model
{
    /** @use HasFactory<ContentNodeFactory> */
    use HasFactory;

    /** Lowercase latin letters and digits separated by single hyphens. */
    public const SLUG_PATTERN = '/^[a-z0-9]+(-[a-z0-9]+)*$/';

    /**
     * The looser slug format of nodes imported from the legacy site: lowercase latin letters, digits, hyphens and
     * underscores in any order (`a---b`, `jam-session-`, `etno_wave`). Accepted only after {@see self::allowLegacySlug()}.
     */
    public const LEGACY_SLUG_PATTERN = '/^[a-z0-9_-]+$/';

    /** `media_relations.owner_type` of a node (see `Webtack\Ecms\Files\FileRelations`). */
    public const MEDIA_OWNER = 'content_node';

    /** Reserved slug prefix of block nodes. */
    public const BLOCK_PREFIX = 'block-';

    /**
     * Whether the next save validates the slug against {@see self::LEGACY_SLUG_PATTERN}. Transient: it is not an
     * attribute, is never stored and lives only as long as this instance.
     */
    private bool $legacySlug = false;

    /**
     * Let this instance be saved with a legacy slug (the import of the legacy site). Every other slug rule
     * (uniqueness, the `block-` prefix, page restrictions, the type-list collision) still applies.
     *
     * @example
     *   $node = new ContentNode([...,'slug' => 'jam-session-']);
     *   $node->allowLegacySlug()->save(); // saved; without the call a ValidationException is thrown
     */
    public function allowLegacySlug(bool $allow = true): static
    {
        $this->legacySlug = $allow;

        return $this;
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'published_at' => 'datetime',
            'unpublished_at' => 'datetime',
            'is_active' => 'boolean',
            'views' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (self $node) {
            $node->assertValid();
        });

        static::deleting(function (self $node) {
            if (app(ReferenceUsage::class)->forNode($node)->isNotEmpty()) {
                throw ValidationException::withMessages(['uuid' => 'A node that is referenced by field values cannot be deleted.']);
            }
        });

        // A deleted node no longer uses any file; the files stay in the media library.
        static::deleted(function (self $node) {
            app(FileRelations::class)->forgetOwner(self::MEDIA_OWNER, $node->id);
        });
    }

    /**
     * Find a node by its stable uuid.
     *
     * @example ContentNode::findByUuid($uuid) // ContentNode|null
     */
    public static function findByUuid(string $uuid): ?self
    {
        return static::query()->where('uuid', $uuid)->first();
    }

    /**
     * Find a node by its slug, regardless of type and kind.
     *
     * @param  bool  $onlyVisible  Return the node only if it is shown on the site (active, already published and not yet unpublished).
     *
     * @example ContentNode::findBySlug('pro-nas')?->contentType->kind
     * @example ContentNode::findBySlug('pro-nas', onlyVisible: true) // null for a disabled, scheduled or expired node
     */
    public static function findBySlug(string $slug, bool $onlyVisible = false): ?self
    {
        return static::query()
            ->when($onlyVisible, fn (Builder $query) => $query->visible())
            ->where('slug', $slug)
            ->first();
    }

    /**
     * Nodes shown on the site: active, published at or before now and not yet unpublished.
     * The interval is half-open: at the exact `unpublished_at` the node is already hidden.
     *
     * @param  Builder<static>  $query
     *
     * @example ContentNode::query()->visible()->count()
     */
    public function scopeVisible(Builder $query): void
    {
        $now = now();

        $query->where('content_nodes.is_active', true)
            ->where('content_nodes.published_at', '<=', $now)
            ->where(fn (Builder $q) => $q->whereNull('content_nodes.unpublished_at')
                ->orWhere('content_nodes.unpublished_at', '>', $now));
    }

    /**
     * The derived state of the node: `disabled` wins over the dates, then `scheduled` (not yet
     * published), `expired` (already unpublished), else `published`.
     *
     * @param  CarbonInterface|null  $now  Moment to evaluate at; defaults to now (inject it in tests).
     *
     * @example $node->status(now()->setDate(2026, 1, 1)) // 'scheduled'
     */
    public function status(?CarbonInterface $now = null): string
    {
        $now ??= now();

        return match (true) {
            ! $this->is_active => 'disabled',
            $this->published_at->greaterThan($now) => 'scheduled',
            $this->unpublished_at !== null && $this->unpublished_at->lessThanOrEqualTo($now) => 'expired',
            default => 'published',
        };
    }

    /**
     * Increase the view counter atomically (a single `UPDATE ... SET views = views + n`).
     *
     * Runs on the base query builder, so `updated_at` and the model events are not touched: a view
     * must not look like an edit. The in-memory attribute is synced, so the model stays clean.
     *
     * @param  int  $by  Amount to add; must not be negative.
     *
     * @throws InvalidArgumentException When `$by` is negative.
     *
     * @example
     *   $node->incrementViews();   // views 5 -> 6
     *   $node->incrementViews(10); // views 6 -> 16
     */
    public function incrementViews(int $by = 1): void
    {
        if ($by < 0) {
            throw new InvalidArgumentException('The view counter cannot be decreased.');
        }

        static::query()->whereKey($this->getKey())->toBase()->increment('views', $by);

        $this->setAttribute('views', (int) $this->getAttribute('views') + $by);
        $this->syncOriginalAttribute('views');
    }

    /** @return BelongsTo<EcmsUser, $this> */
    public function author(): BelongsTo
    {
        return $this->belongsTo(UserModel::class(), 'user_id');
    }

    /** @return BelongsTo<ContentType, $this> */
    public function contentType(): BelongsTo
    {
        return $this->belongsTo(ContentType::class, 'content_type', 'key');
    }

    /**
     * Fill the uuid on creation, keep it and the type immutable, and validate the slug.
     *
     * @throws ValidationException
     */
    private function assertValid(): void
    {
        if ($this->exists) {
            foreach (['uuid', 'content_type'] as $immutable) {
                if ($this->isDirty($immutable)) {
                    throw ValidationException::withMessages([$immutable => "The node {$immutable} cannot be changed."]);
                }
            }
        } elseif (empty($this->uuid)) {
            $this->uuid = (string) Str::uuid7();
        } else {
            Validator::make(['uuid' => $this->uuid], [
                'uuid' => ['uuid', 'unique:content_nodes,uuid'],
            ])->validate();
        }

        if (! $this->exists || $this->isDirty('slug')) {
            $this->assertValidSlug();
        }

        if ($this->unpublished_at !== null && $this->published_at !== null
            && ! $this->unpublished_at->greaterThan($this->published_at)) {
            throw ValidationException::withMessages(['unpublished_at' => 'The unpublish date must be after the publish date.']);
        }

        if ($this->views !== null && $this->views < 0) {
            throw ValidationException::withMessages(['views' => 'The view counter cannot be negative.']);
        }
    }

    /**
     * The slug must match the slug format; blocks require the `block-` prefix, pages must not collide with a content
     * type slug or a reserved path ({@see PublicPaths}), `content` nodes must not take an address that is a type slug.
     * Uniqueness is left to the unique index (the final arbiter).
     *
     * @throws ValidationException
     */
    private function assertValidSlug(): void
    {
        Validator::make(['slug' => $this->slug], [
            'slug' => ['required', 'string', 'max:190', 'regex:'.($this->legacySlug ? self::LEGACY_SLUG_PATTERN : self::SLUG_PATTERN)],
        ])->validate();

        $kind = ContentType::findByKey($this->content_type)?->kind;

        if ($kind === 'block' && ! str_starts_with($this->slug, self::BLOCK_PREFIX)) {
            throw ValidationException::withMessages(['slug' => 'A block slug must start with "'.self::BLOCK_PREFIX.'".']);
        }

        // A page stands on the first URL segment, shared with content type slugs and reserved paths.
        if ($kind === 'page' && $reason = app(PublicPaths::class)->pageSlugConflict($this->slug)) {
            throw ValidationException::withMessages(['slug' => PublicPaths::message($reason)]);
        }

        // A `content` node address equal to a type slug is hidden by that type's list ({@see PublicPaths::nodeAddressConflict()}).
        // This method runs only when the node is new or its slug changed, so a legacy collision never blocks other edits.
        if ($kind === 'content'
            && ($type = ContentType::findByKey($this->content_type)) !== null
            && app(PublicPaths::class)->nodeAddressConflict($type, $this->slug)) {
            throw ValidationException::withMessages(['slug' => __('This slug would hide the list of a content type.')]);
        }
    }
}
