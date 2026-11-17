<?php

namespace Webtack\Ecms\Content;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Webtack\Ecms\Content\FieldTypes\ComposedValue;
use Webtack\Ecms\Content\FieldTypes\FieldType;
use Webtack\Ecms\Content\FieldTypes\LocksReferences;
use Webtack\Ecms\Content\FieldTypes\TracksFiles;
use Webtack\Ecms\Files\FileRelations;
use Webtack\Ecms\Models\ContentField;
use Webtack\Ecms\Models\ContentNode;

/**
 * Writes the values of a node, or of a content type itself, according to the field configuration
 * of the type.
 *
 * The owner is a {@see ContentNode} (shorthand for {@see ValueOwner::forNode()}) or a
 * {@see ValueOwner}; it decides which fields may be written (`SystemFields::owns()`): a field the
 * owner does not hold is rejected with an error keyed by the field key.
 *
 * Only the fields listed in `$values` and only the given language are replaced; other fields
 * and other languages are left alone. `null`, `''` or `[]` clear a field. The whole write is one
 * transaction after validation succeeds; for a field type that holds files (`TracksFiles`) the
 * file relations of the owner are synchronised in that same transaction. The type and the node uuid
 * stored in each row come from the owner, never from the input.
 *
 * @example
 *   app(ContentValueWriter::class)->write($node, ['title' => 'Hello', 'tags' => ['a', 'b']]);
 *   app(ContentValueWriter::class)->write($node, ['title' => 'Привіт'], 'uk');
 *   app(ContentValueWriter::class)->write(ValueOwner::forType($type), ['section_title' => 'News']);
 */
class ContentValueWriter
{
    public function __construct(
        private readonly FieldTypeRegistry $registry,
        private readonly ContentLanguages $languages,
        private readonly FileRelations $fileRelations,
    ) {}

    /**
     * Validate and store values for a node or a type.
     *
     * @param  array<string, mixed>  $values  Field key => value (list for multiple fields).
     * @param  bool  $creating  Also require every required field (base language only).
     *
     * @throws ValidationException With errors keyed by field key (or `lang`).
     */
    public function write(ContentNode|ValueOwner $owner, array $values, string $lang = 'default', bool $creating = false): void
    {
        $owner = $owner instanceof ContentNode ? ValueOwner::forNode($owner) : $owner;

        [$lang, $fields, $values] = $this->validate($owner, $values, $lang, $creating);

        DB::transaction(function () use ($owner, $values, $lang, $fields) {
            foreach ($values as $key => $value) {
                $this->replace($owner, $fields[$key], $lang, $value);
            }
        });
    }

    /**
     * Validate values against the fields of an owner without storing anything.
     *
     * @param  string|ValueOwner  $owner  An owner, or the key of a type (the owner is then a node of that type).
     * @param  array<string, mixed>  $values
     * @return array{0: string, 1: Collection<string, ContentField>, 2: array<string, mixed>}
     *                                                                                        Normalised language, the fields the owner holds by key, and the normalised values.
     *
     * @throws ValidationException
     */
    public function validate(string|ValueOwner $owner, array $values, string $lang = 'default', bool $creating = false): array
    {
        $owner = is_string($owner) ? ValueOwner::forNodesOfType($owner) : $owner;
        $lang = $this->languages->assertWritable($lang);
        $isDefault = $lang === config('content.default_lang');
        $allFields = ContentField::query()->where('content_type', $owner->contentType)->get()->keyBy('key');
        $fields = $owner->fields($allFields);
        $values = $this->normalizeAll($values, $fields);

        $errors = [];
        foreach (array_keys($values) as $key) {
            if ($allFields->has($key) && ! $fields->has($key)) {
                $errors[$key] = ["The field [{$key}] does not hold values of a {$owner->entity}."];
            } elseif (! $fields->has($key)) {
                $errors[$key] = ["The field [{$key}] does not exist in this content type."];
            } elseif (! $isDefault && ! $fields[$key]->is_translatable) {
                $errors[$key] = ["The field [{$key}] is not translatable."];
            }
        }
        if ($errors) {
            throw ValidationException::withMessages($errors);
        }

        // Required fields are enforced in the base language: for every required field when the
        // node is created, and for any required field that is being written afterwards.
        $required = $fields->filter(fn (ContentField $f) => $f->is_required && $isDefault
            && ($creating || array_key_exists($f->key, $values)));

        $checked = $required->keys()->merge(array_keys($values))->unique();
        $data = [];
        $rules = [];

        foreach ($checked as $key) {
            $field = $fields[$key];
            $data[$key] = $values[$key] ?? null;
            $presence = $required->has($key) ? 'required' : 'nullable';
            $type = $this->registry->get($field->type);
            $elementRules = $type->rules($field);

            if ($field->is_multiple) {
                // Empty elements are already dropped, so only filled ones count against the limit.
                // A composed value is an associative array, so a multiple field must get a list of them.
                $rules[$key] = [$presence, 'array', ...($type instanceof ComposedValue ? ['list'] : []), ...($field->max_items > 0 ? ["max:{$field->max_items}"] : [])];
                $rules["{$key}.*"] = $elementRules;
            } else {
                $rules[$key] = ['bail', $presence, $this->singleValue($type), ...$elementRules];
            }
        }

        Validator::make($data, $rules)->validate();

        return [$lang, $fields, array_intersect_key($values, $fields->all())];
    }

    /**
     * Treat blank values as "no value" and drop blank elements of lists. What is blank is decided by the
     * type of the field (`FieldType::isBlank`); values of unknown fields are left for the field check.
     *
     * @param  array<string, mixed>  $values
     * @param  Collection<string, ContentField>  $fields
     * @return array<string, mixed>
     */
    private function normalizeAll(array $values, Collection $fields): array
    {
        foreach ($values as $key => $value) {
            $type = $fields->has($key) ? $this->registry->get($fields[$key]->type) : null;
            $isBlank = $type ? $type->isBlank(...) : fn ($item) => $item === null || $item === '';

            $values[$key] = $this->isList($type, $value)
                ? array_values(array_filter($value, fn ($item) => ! $isBlank($item)))
                : ($isBlank($value) ? null : $value);
        }

        return $values;
    }

    /**
     * A non-multiple field accepts one value, never a list.
     */
    private function singleValue(FieldType $type): \Closure
    {
        return function (string $attribute, mixed $value, \Closure $fail) use ($type): void {
            if ($this->isList($type, $value)) {
                $fail("The field [{$attribute}] accepts a single value, not a list.");
            }
        };
    }

    /**
     * Whether a value is a list of values. A composed value is an associative array, so for a
     * `ComposedValue` type only a list array counts as a list.
     */
    private function isList(?FieldType $type, mixed $value): bool
    {
        return is_array($value) && (! $type instanceof ComposedValue || array_is_list($value));
    }

    /**
     * Lock what the prepared values refer to; a vanished row is an error of the field.
     *
     * @throws ValidationException
     */
    private function lockReferences(LocksReferences&FieldType $type, ContentField $field, mixed $value): void
    {
        try {
            $type->lockReferences(array_map($type->prepare(...), $this->isList($type, $value) ? $value : [$value]));
        } catch (ValidationException $e) {
            throw ValidationException::withMessages([$field->key => $e->errors()['value'] ?? ['The referenced value no longer exists.']]);
        }
    }

    /**
     * Replace the stored values of one field of one owner in one language.
     */
    private function replace(ValueOwner $owner, ContentField $field, string $lang, mixed $value): void
    {
        $type = $this->registry->get($field->type);
        $table = $type->table();

        $owner->scope(DB::table($table))
            ->where('field_key', $field->key)
            ->where('lang', $lang)
            ->delete();

        if ($value !== null && $value !== []) {
            if ($type instanceof LocksReferences) {
                $this->lockReferences($type, $field, $value);
            }

            $rows = [];
            foreach ($this->isList($type, $value) ? $value : [$value] as $weight => $item) {
                [$scalar, $options] = $type instanceof ComposedValue ? $type->split($item) : [$type->prepare($item), null];

                $rows[] = [
                    ...$owner->row(),
                    'field_key' => $field->key,
                    'lang' => $lang,
                    'weight' => $weight,
                    'value' => $scalar,
                    'options' => $options === null ? null : json_encode($options, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                ];
            }

            DB::table($table)->insert($rows);
        }

        if ($type instanceof TracksFiles) {
            $this->fileRelations->sync($owner->mediaOwnerType, $owner->mediaOwnerId, $field->key, $lang, $type->fileUuids($value));
        }
    }
}
