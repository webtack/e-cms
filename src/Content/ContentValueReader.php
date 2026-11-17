<?php

namespace Webtack\Ecms\Content;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Webtack\Ecms\Content\FieldTypes\ComposedValue;
use Webtack\Ecms\Models\ContentField;
use Webtack\Ecms\Models\ContentNode;
use Webtack\Ecms\Models\ContentType;

/**
 * Reads node values in batches: one query per value table (and per content type), never one per
 * node. A missing value is `null` (an empty list for multiple fields); by default the base language is
 * never substituted for a missing translation. With `fallback: true` (the public site) a field that is
 * blank in the requested language is read from the base language instead, per field; a multiple field
 * and a composed value (a video, a link) fall back as a whole, never mixing two languages. The fallback
 * costs one more query per value table, never one per node. The values of a content type itself are read
 * separately with {@see self::loadForType()} and never mix with the values of nodes.
 *
 * @example
 *   $values = app(ContentValueReader::class)->load($nodes, ['title', 'price'], 'en');
 *   $values[$node->id]['title']; // "Hello" or null
 *   app(ContentValueReader::class)->loadForType($type, ['section_title'], 'en'); // ['section_title' => null]
 *   app(ContentValueReader::class)->load($nodes, ['title'], 'en', fallback: true); // base title when English is blank
 */
class ContentValueReader
{
    public function __construct(
        private readonly FieldTypeRegistry $registry,
        private readonly ContentLanguages $languages,
    ) {}

    /**
     * Load values for a set of nodes.
     *
     * @param  iterable<ContentNode>  $nodes
     * @param  array<int, string>|null  $fieldKeys  Fields to load; null = all fields of each type.
     * @param  bool  $fallback  Read the base language for every field that is blank in $lang (public site only).
     * @return array<int, array<string, mixed>> node id => field key => value | list | null
     *
     * @throws ValidationException For an unreadable language or an unknown field key.
     */
    public function load(iterable $nodes, ?array $fieldKeys = null, string $lang = 'default', bool $fallback = false): array
    {
        $lang = $this->languages->assertReadable($lang);
        $result = [];
        $idsByType = [];

        foreach ($nodes as $node) {
            $idsByType[$node->content_type][] = $node->id;
        }

        foreach ($idsByType as $contentType => $ids) {
            // Fields that only the type holds (`section_*`) have no node values and are not offered.
            $fields = ValueOwner::forNodesOfType($contentType)
                ->fields(ContentField::query()->where('content_type', $contentType)->get()->keyBy('key'));

            if ($fieldKeys !== null && $unknown = array_diff($fieldKeys, $fields->keys()->all())) {
                throw ValidationException::withMessages([
                    'fields' => 'Unknown fields for the content type: '.implode(', ', $unknown).'.',
                ]);
            }

            // Requested order is kept; a plain collection avoids Eloquent's primary-key based only().
            $wanted = $fieldKeys === null
                ? $fields->toBase()
                : collect($fieldKeys)->unique()->mapWithKeys(fn (string $key) => [$key => $fields[$key]]);

            foreach ($ids as $id) {
                $result[$id] = $wanted->map(fn (ContentField $f) => $f->is_multiple ? [] : null)->all();
            }

            foreach ($wanted->groupBy(fn (ContentField $f) => $this->registry->get($f->type)->table(), preserveKeys: true) as $table => $tableFields) {
                $rowsIn = fn (string $rowLang) => DB::table($table)
                    ->whereIn('content_node_id', $ids)
                    ->where('entity', SystemFields::OWNER_NODE)
                    ->where('content_type', $contentType)
                    ->whereIn('field_key', $tableFields->keys()->all())
                    ->where('lang', $rowLang)
                    ->orderBy('weight')
                    ->get();

                $own = $this->collect($tableFields, $rowsIn($lang), fn ($row) => $row->content_node_id);

                if ($fallback && $lang !== config('content.default_lang')) {
                    $base = $this->collect($tableFields, $rowsIn(config('content.default_lang')), fn ($row) => $row->content_node_id);

                    foreach ($ids as $id) {
                        foreach ($tableFields as $key => $field) {
                            if ($this->isBlankValue($field, $own[$id][$key] ?? null)) {
                                $own[$id][$key] = $base[$id][$key] ?? null;
                            }
                        }
                    }
                }

                foreach ($own as $id => $byField) {
                    foreach ($byField as $key => $value) {
                        if ($value !== null) {
                            $result[$id][$key] = $value;
                        }
                    }
                }
            }
        }

        return $result;
    }

    /**
     * Load the values of a content type itself for one language.
     *
     * A missing value is `null` (an empty list for a multiple field); the base language is substituted for a
     * blank value only with `$fallback` (per field, a multiple or composed value as a whole).
     *
     * @param  array<int, string>|null  $fieldKeys  Fields to load; null = every field the type holds values for.
     * @param  bool  $fallback  Read the base language for every field that is blank in $lang (public site only).
     * @return array<string, mixed> field key => value | list | null
     *
     * @throws ValidationException For an unreadable language, or a key that is unknown or whose field
     *                             does not hold values of the type (for example `title`).
     *
     * @example
     *   app(ContentValueReader::class)->loadForType($news, null, 'en'); // ['meta_title' => 'News', 'section_cover' => null, ...]
     */
    public function loadForType(ContentType $type, ?array $fieldKeys = null, string $lang = 'default', bool $fallback = false): array
    {
        $lang = $this->languages->assertReadable($lang);
        $owner = ValueOwner::forType($type);
        $fields = $owner->fields(ContentField::query()->where('content_type', $type->key)->orderBy('weight')->orderBy('id')->get()->keyBy('key'));

        if ($fieldKeys !== null && $unknown = array_diff($fieldKeys, $fields->keys()->all())) {
            throw ValidationException::withMessages([
                'fields' => 'Unknown or not type-owned fields for the content type: '.implode(', ', $unknown).'.',
            ]);
        }

        $wanted = $fieldKeys === null
            ? $fields->toBase()
            : collect($fieldKeys)->unique()->mapWithKeys(fn (string $key) => [$key => $fields[$key]]);

        $result = $wanted->map(fn (ContentField $f) => $f->is_multiple ? [] : null)->all();

        foreach ($wanted->groupBy(fn (ContentField $f) => $this->registry->get($f->type)->table(), preserveKeys: true) as $table => $tableFields) {
            $rowsIn = fn (string $rowLang) => $owner->scope(DB::table($table))
                ->whereIn('field_key', $tableFields->keys()->all())
                ->where('lang', $rowLang)
                ->orderBy('weight')
                ->get();

            $own = $this->collect($tableFields, $rowsIn($lang), fn () => 0)[0] ?? [];
            $base = $fallback && $lang !== config('content.default_lang')
                ? ($this->collect($tableFields, $rowsIn(config('content.default_lang')), fn () => 0)[0] ?? [])
                : [];

            foreach ($tableFields as $key => $field) {
                $value = $own[$key] ?? null;

                if ($this->isBlankValue($field, $value)) {
                    $value = $base[$key] ?? $value;
                }

                if ($value !== null) {
                    $result[$key] = $value;
                }
            }
        }

        return $result;
    }

    /**
     * Group stored rows into owner => field key => value (a list for a multiple field).
     *
     * @param  Collection<string, ContentField>  $fields
     * @param  \Closure(object): int  $ownerOf
     * @return array<int, array<string, mixed>>
     */
    private function collect($fields, $rows, \Closure $ownerOf): array
    {
        $grouped = [];

        foreach ($rows as $row) {
            $field = $fields[$row->field_key];
            $value = $this->castRow($field, $row);

            if ($field->is_multiple) {
                $grouped[$ownerOf($row)][$row->field_key][] = $value;
            } else {
                $grouped[$ownerOf($row)][$row->field_key] = $value;
            }
        }

        return $grouped;
    }

    /** Whether a read value counts as missing for the fallback: null, or a list whose items are all blank. */
    private function isBlankValue(ContentField $field, mixed $value): bool
    {
        $type = $this->registry->get($field->type);

        if ($field->is_multiple) {
            return $value === null || array_filter((array) $value, fn ($item) => ! $type->isBlank($item)) === [];
        }

        return $value === null || $type->isBlank($value);
    }

    /**
     * Turn a stored row into the value of its field type.
     */
    private function castRow(ContentField $field, object $row): mixed
    {
        $type = $this->registry->get($field->type);

        return $type instanceof ComposedValue
            ? $type->compose($row->value, $row->options === null ? null : json_decode($row->options, true))
            : $type->cast($row->value);
    }
}
