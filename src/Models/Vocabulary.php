<?php

namespace Webtack\Ecms\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Webtack\Ecms\Database\Factories\VocabularyFactory;
use Webtack\Ecms\Taxonomy\TermTree;

/**
 * A vocabulary: a global set of terms that fields of type `term` can point at.
 *
 * `key` is unique and immutable; it is the identifier terms and fields refer to (no `uuid`). `settings.max_depth`
 * (optional integer >= 1) bounds the depth of the term tree; a root term has depth 1.
 * A vocabulary with terms, or one named by a field's `settings.vocabulary`, cannot be deleted.
 *
 * @property int $id
 * @property string $key
 * @property string $name
 * @property string|null $description
 * @property array<string, mixed>|null $settings
 *
 * @example
 *   $vocabulary = Vocabulary::create(['key' => 'categories', 'name' => 'Categories', 'settings' => ['max_depth' => 3]]);
 *   $vocabulary->terms; // the terms of the vocabulary
 *   Vocabulary::findByKey('categories');
 */
#[UseFactory(VocabularyFactory::class)]
#[Fillable(['key', 'name', 'description', 'settings'])]
class Vocabulary extends Model
{
    /** @use HasFactory<VocabularyFactory> */
    use HasFactory;

    /** Format of the vocabulary key (same as type and field keys). */
    public const KEY_PATTERN = ContentType::KEY_PATTERN;

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['settings' => 'array'];
    }

    protected $table = 'taxonomy_vocabularies';

    protected static function booted(): void
    {
        static::saving(function (self $vocabulary) {
            $vocabulary->assertValid();
        });

        static::deleting(function (self $vocabulary) {
            // The foreign key also refuses this, but the model gives a readable error.
            if (Term::query()->where('vocabulary_key', $vocabulary->key)->exists()) {
                throw ValidationException::withMessages(['key' => 'A vocabulary that has terms cannot be deleted.']);
            }

            if ($vocabulary->referencingFields()->exists()) {
                throw ValidationException::withMessages(['key' => 'A vocabulary used by a field cannot be deleted.']);
            }
        });
    }

    /**
     * Find a vocabulary by its key.
     *
     * @example Vocabulary::findByKey('categories') // Vocabulary|null
     */
    public static function findByKey(string $key): ?self
    {
        return static::query()->where('key', $key)->first();
    }

    /** @return HasMany<Term, $this> */
    public function terms(): HasMany
    {
        return $this->hasMany(Term::class, 'vocabulary_key', 'key');
    }

    /**
     * The deepest level a term may have, or null when the depth is not limited.
     */
    public function maxDepth(): ?int
    {
        $depth = $this->settings['max_depth'] ?? null;

        return $depth === null ? null : (int) $depth;
    }

    /**
     * The fields of type `term` that name this vocabulary.
     *
     * @return Builder<ContentField>
     */
    public function referencingFields(): Builder
    {
        return ContentField::query()->where('type', 'term')->where('settings->vocabulary', $this->getOriginal('key') ?? $this->key);
    }

    /**
     * @throws ValidationException
     */
    private function assertValid(): void
    {
        if ($this->exists) {
            if ($this->isDirty('key')) {
                throw ValidationException::withMessages(['key' => 'The vocabulary key cannot be changed.']);
            }
        }

        Validator::make($this->getAttributes(), [
            'key' => ['required', 'string', 'max:64', 'regex:'.self::KEY_PATTERN, ...($this->exists ? [] : ['unique:taxonomy_vocabularies,key'])],
            'name' => ['required', 'string', 'max:255'],
        ])->validate();

        $this->assertValidDepth();
    }

    /**
     * `max_depth` is an integer >= 1 and not below the height of the existing tree.
     *
     * @throws ValidationException
     */
    private function assertValidDepth(): void
    {
        $depth = $this->settings['max_depth'] ?? null;

        if ($depth === null) {
            return;
        }

        if (! is_int($depth) || $depth < 1) {
            throw ValidationException::withMessages(['settings.max_depth' => 'The maximum depth must be an integer of at least 1.']);
        }

        if ($this->exists && $this->isDirty('settings') && app(TermTree::class)->vocabularyHeight($this) > $depth) {
            throw ValidationException::withMessages(['settings.max_depth' => 'The maximum depth is lower than the depth of the existing terms.']);
        }
    }

    /**
     * Save the vocabulary.
     *
     * @param  array<string, mixed>  $options
     */
    public function save(array $options = []): bool
    {
        return DB::transaction(fn () => parent::save($options));
    }
}
