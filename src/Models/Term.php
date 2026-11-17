<?php

namespace Webtack\Ecms\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Webtack\Ecms\Database\Factories\TermFactory;
use Webtack\Ecms\Files\FileRelations;
use Webtack\Ecms\Taxonomy\TermTree;
use Webtack\Ecms\Taxonomy\TermUsage;
use Webtack\Ecms\Taxonomy\TermWriter;

/**
 * A term of a vocabulary: a node of a tree with one parent.
 *
 * Terms are linked by `uuid` (immutable, UUID v7). The vocabulary of a term is fixed at creation;
 * the parent must be in the same vocabulary, may not make a cycle and may not push the tree below
 * the vocabulary's `max_depth`. A term with children or with uses by field values cannot be
 * deleted. Names and descriptions live in {@see TermTranslation}; use {@see TermWriter}
 * to write a term together with them.
 *
 * @property int $id
 * @property string $uuid
 * @property string $vocabulary_key
 * @property string|null $parent_uuid
 * @property string $slug
 * @property int $weight
 * @property string|null $image `{uuid}.{ext}` of a file of the `image` profile.
 *
 * @example
 *   $term = Term::create(['vocabulary_key' => $vocabulary->key, 'slug' => 'clothes']);
 *   $term->children; // ordered by weight, then id
 */
#[UseFactory(TermFactory::class)]
#[Fillable(['uuid', 'vocabulary_key', 'parent_uuid', 'slug', 'weight', 'image'])]
class Term extends Model
{
    /** @use HasFactory<TermFactory> */
    use HasFactory;

    /** Format of the term slug (same as the node slug). */
    public const SLUG_PATTERN = ContentNode::SLUG_PATTERN;

    /** `media_relations.owner_type` of a term (see `Webtack\Ecms\Files\FileRelations`). */
    public const MEDIA_OWNER = 'taxonomy_term';

    protected $table = 'taxonomy_terms';

    protected $attributes = ['weight' => 0];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['weight' => 'integer'];
    }

    protected static function booted(): void
    {
        static::saving(function (self $term) {
            $term->assertValid();
        });

        static::deleting(function (self $term) {
            // Runs in the transaction opened by delete(); the row lock serialises with value writes.
            $locked = static::query()->where('uuid', $term->uuid)->lockForUpdate()->first();

            if ($locked === null) {
                return;
            }

            if (static::query()->where('parent_uuid', $term->uuid)->exists()) {
                throw ValidationException::withMessages(['uuid' => 'A term that has child terms cannot be deleted.']);
            }

            if (app(TermUsage::class)->forTerm($term)->isNotEmpty()) {
                throw ValidationException::withMessages(['uuid' => 'A term that is used by content cannot be deleted.']);
            }

            app(FileRelations::class)->forgetOwner(self::MEDIA_OWNER, $term->id);
        });
    }

    /**
     * Delete the term and its translations atomically.
     */
    public function delete(): ?bool
    {
        return DB::transaction(fn () => parent::delete());
    }

    /**
     * Save the term.
     *
     * @param  array<string, mixed>  $options
     */
    public function save(array $options = []): bool
    {
        return DB::transaction(fn () => parent::save($options));
    }

    /**
     * Find a term by its stable uuid.
     *
     * @example Term::findByUuid($uuid) // Term|null
     */
    public static function findByUuid(string $uuid): ?self
    {
        return static::query()->where('uuid', $uuid)->first();
    }

    /** @return BelongsTo<Vocabulary, $this> */
    public function vocabulary(): BelongsTo
    {
        return $this->belongsTo(Vocabulary::class, 'vocabulary_key', 'key');
    }

    /** @return BelongsTo<Term, $this> */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_uuid', 'uuid');
    }

    /** @return HasMany<Term, $this> */
    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_uuid', 'uuid')->orderBy('weight')->orderBy('id');
    }

    /** @return HasMany<TermTranslation, $this> */
    public function translations(): HasMany
    {
        return $this->hasMany(TermTranslation::class, 'term_uuid', 'uuid');
    }

    /**
     * @throws ValidationException
     */
    private function assertValid(): void
    {
        if ($this->exists) {
            foreach (['uuid', 'vocabulary_key'] as $immutable) {
                if ($this->isDirty($immutable)) {
                    throw ValidationException::withMessages([$immutable => "The term {$immutable} cannot be changed."]);
                }
            }
        } elseif (empty($this->uuid)) {
            $this->uuid = (string) Str::uuid7();
        } else {
            Validator::make(['uuid' => $this->uuid], ['uuid' => ['uuid', 'unique:taxonomy_terms,uuid']])->validate();
        }

        $vocabulary = Vocabulary::query()->where('key', $this->vocabulary_key)->first();

        if ($vocabulary === null) {
            throw ValidationException::withMessages(['vocabulary_key' => 'The vocabulary does not exist.']);
        }

        if (! $this->exists || $this->isDirty('slug')) {
            Validator::make(['slug' => $this->slug], [
                'slug' => [
                    'required', 'string', 'max:190', 'regex:'.self::SLUG_PATTERN,
                    function (string $attribute, mixed $value, \Closure $fail) {
                        $taken = static::query()
                            ->where('vocabulary_key', $this->vocabulary_key)
                            ->where('slug', $value)
                            ->when($this->exists, fn ($query) => $query->whereKeyNot($this->getKey()))
                            ->exists();

                        if ($taken) {
                            $fail('The slug is already used in this vocabulary.');
                        }
                    },
                ],
            ])->validate();
        }

        if (! $this->exists || $this->isDirty('parent_uuid')) {
            $this->assertValidParent($vocabulary);
        }
    }

    /**
     * The parent must be in the same vocabulary, must not be the term or its descendant, and the
     * term with its whole subtree must fit into the maximum depth of the vocabulary.
     *
     * @throws ValidationException
     */
    private function assertValidParent(Vocabulary $vocabulary): void
    {
        if ($this->parent_uuid === null) {
            return;
        }

        $parent = static::query()->where('uuid', $this->parent_uuid)->first();

        if ($parent === null || $parent->vocabulary_key !== $this->vocabulary_key) {
            throw ValidationException::withMessages(['parent_uuid' => 'The parent term must exist in the same vocabulary.']);
        }

        $tree = app(TermTree::class);
        $ancestors = $tree->ancestorUuids($parent);

        if ($parent->uuid === $this->uuid || in_array($this->uuid, $ancestors, true)) {
            throw ValidationException::withMessages(['parent_uuid' => 'A term cannot be its own ancestor.']);
        }

        $max = $vocabulary->maxDepth();

        // Depth of the parent + this term + the levels below this term (a new term has none).
        if ($max !== null && count($ancestors) + 2 + ($this->exists ? $tree->height($this) - 1 : 0) > $max) {
            throw ValidationException::withMessages(['parent_uuid' => "The term would exceed the maximum depth of {$max}."]);
        }
    }
}
