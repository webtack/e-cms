<?php

namespace Webtack\Ecms\Content\FieldTypes;

use Closure;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Webtack\Ecms\Content\PresentContext;
use Webtack\Ecms\Models\ContentField;
use Webtack\Ecms\Models\Vocabulary;
use Webtack\Ecms\Taxonomy\TermTree;
use Webtack\Ecms\Taxonomy\TermUsage;

/**
 * A taxonomy term: the value is the term's `uuid` (lower case), stored in `content_values_string`.
 *
 * The field names its vocabulary in `settings.vocabulary` (the vocabulary key). A value must be the
 * uuid of an existing term of that vocabulary. A filter by `eq` or `in` matches the given terms
 * and all their descendants.
 *
 * `content_values_string` has no foreign key to the terms, so a write locks the referenced terms
 * ({@see LocksReferences}) and deleting a term checks its uses ({@see TermUsage}).
 *
 * @example
 *   // field `category` with settings ['vocabulary' => 'categories']
 *   app(ContentValueWriter::class)->write($node, ['category' => $term->uuid]);
 */
class TermType extends AbstractFieldType implements ExpandsFilterValues, LocksReferences, PreloadsReferences, ValidatesSettings
{
    public function key(): string
    {
        return 'term';
    }

    public function table(): string
    {
        return 'content_values_string';
    }

    public function rules(ContentField $field): array
    {
        return ['string', 'uuid', function (string $attribute, mixed $value, Closure $fail) use ($field): void {
            $query = DB::table('taxonomy_terms')->where('uuid', strtolower((string) $value));

            // Filters validate with an empty field (see ContentNodeQuery): any existing term then.
            if ($field->type !== null) {
                $query->where('vocabulary_key', (string) ($field->settings['vocabulary'] ?? ''));
            }

            if (! $query->exists()) {
                $fail('The :attribute must be an existing term'.($field->type !== null ? ' of the field\'s vocabulary' : '').'.');
            }
        }];
    }

    public function prepare(mixed $value): mixed
    {
        return strtolower((string) $value);
    }

    public function cast(mixed $value): mixed
    {
        return (string) $value;
    }

    public function operators(): array
    {
        return ['eq', 'in'];
    }

    public function settingsErrors(array $settings, ?array $original, bool $hasValues): array
    {
        $key = $settings['vocabulary'] ?? null;

        if (! is_string($key) || $key === '') {
            return ['vocabulary' => 'The vocabulary is required.'];
        }

        if (Vocabulary::findByKey($key) === null) {
            return ['vocabulary' => 'The vocabulary does not exist.'];
        }

        if ($original !== null && $hasValues && ($original['vocabulary'] ?? null) !== $key) {
            return ['vocabulary' => 'The vocabulary of a field that has values cannot be changed.'];
        }

        return [];
    }

    public function lockReferences(array $prepared): void
    {
        $uuids = array_values(array_unique($prepared));
        $found = DB::table('taxonomy_terms')->whereIn('uuid', $uuids)->sharedLock()->pluck('uuid');

        if ($found->count() !== count($uuids)) {
            throw ValidationException::withMessages(['value' => 'The term no longer exists.']);
        }
    }

    /**
     * The name of the term in the reading language; `null` when the term has no translation in it
     * (another language is never substituted).
     *
     * @example $type->present($term->uuid, $field, $context); // "Red"
     */
    public function present(mixed $value, ContentField $field, PresentContext $context): ?string
    {
        return is_string($value) ? $context->term($value) : null;
    }

    public function preload(array $values, ContentField $field, PresentContext $context): void
    {
        $context->preloadTerms(array_values(array_filter($values, 'is_string')));
    }

    public function expandFilterValues(array $prepared): array
    {
        return app(TermTree::class)->descendantUuids(array_values($prepared));
    }

    protected function baseRules(): array
    {
        return ['string', 'uuid'];
    }
}
