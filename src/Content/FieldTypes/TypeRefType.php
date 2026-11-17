<?php

namespace Webtack\Ecms\Content\FieldTypes;

use Closure;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Webtack\Ecms\Content\PresentContext;
use Webtack\Ecms\Models\ContentField;
use Webtack\Ecms\Reference\ReferenceUsage;

/**
 * A reference to a content type: the value is the type's `key`, stored in `content_values_string`.
 *
 * The field names the kind of the types it may point at in `settings.kind` (one of `content.kinds`).
 * A value must be the key of an existing type of that kind. Filters (`eq`, `in`) only check that
 * the type exists.
 *
 * `content_values_string` has no foreign key to `content_types`, so a write locks the referenced
 * types ({@see LocksReferences}) and deleting a type checks its uses ({@see ReferenceUsage}).
 *
 * @example
 *   // field `list_source` with settings ['kind' => 'content']
 *   app(ContentValueWriter::class)->write($node, ['list_source' => 'article']);
 */
class TypeRefType extends AbstractFieldType implements LocksReferences, PreloadsReferences, ValidatesSettings
{
    public function key(): string
    {
        return 'type_ref';
    }

    public function table(): string
    {
        return 'content_values_string';
    }

    public function rules(ContentField $field): array
    {
        return ['string', 'max:64', function (string $attribute, mixed $value, Closure $fail) use ($field): void {
            $query = DB::table('content_types')->where('key', (string) $value);

            // Filters validate with an empty field (see ContentNodeQuery): any existing type then.
            if ($field->type !== null) {
                $query->where('kind', (string) ($field->settings['kind'] ?? ''));
            }

            if (! $query->exists()) {
                $fail('The :attribute must be an existing content type'.($field->type !== null ? ' of the field\'s kind' : '').'.');
            }
        }];
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
        $kind = $settings['kind'] ?? null;

        if (array_diff(array_keys($settings), ['kind']) !== []) {
            return ['kind' => 'Only the kind can be set.'];
        }

        if (! is_string($kind) || $kind === '') {
            return ['kind' => 'The kind is required.'];
        }

        if (! in_array($kind, config('content.kinds'), true)) {
            return ['kind' => 'The kind does not exist.'];
        }

        if ($original !== null && $hasValues && ($original['kind'] ?? null) !== $kind) {
            return ['kind' => 'The kind of a field that has values cannot be changed.'];
        }

        return [];
    }

    public function lockReferences(array $prepared): void
    {
        $keys = array_values(array_unique($prepared));
        $found = DB::table('content_types')->whereIn('key', $keys)->sharedLock()->pluck('key');

        if ($found->count() !== count($keys)) {
            throw ValidationException::withMessages(['value' => 'The content type no longer exists.']);
        }
    }

    /**
     * The key, name and list address of the type; `null` when the type no longer exists. `url` is set only for
     * a `content` type (the others have no public list).
     *
     * @return array{key: string, name: string, url: string|null}|null
     *
     * @example $type->present('news', $field, $context); // ['key' => 'news', 'name' => 'News', 'url' => '/news']
     */
    public function present(mixed $value, ContentField $field, PresentContext $context): ?array
    {
        return is_string($value) ? $context->type($value) : null;
    }

    public function preload(array $values, ContentField $field, PresentContext $context): void
    {
        $context->preloadTypes(array_values(array_filter($values, 'is_string')));
    }

    protected function baseRules(): array
    {
        return ['string', 'max:64'];
    }
}
