<?php

namespace Webtack\Ecms\Content;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Webtack\Ecms\Content\FieldTypes\AbstractFileFieldType;
use Webtack\Ecms\Content\FieldTypes\ComposedValue;
use Webtack\Ecms\Files\FileDescriptions;
use Webtack\Ecms\Models\ContentField;
use Webtack\Ecms\Models\ContentNode;
use Webtack\Ecms\Models\ContentType;

/**
 * Saves the translated values of a node, or of a type's own values, in ONE additional language.
 *
 * It sits on top of {@see ContentValueWriter}, which stays strict and unchanged:
 *  - only translatable fields the owner holds are accepted (anything else is a validation error
 *    under its key, nothing is stored);
 *  - each submitted value is validated by its field type, but required fields are not enforced
 *    (an empty value means "use the base value");
 *  - a value equal to the base value of the same field is not stored, and an existing
 *    translation that now equals the base is removed (values are compared as the field type
 *    stores them; a multiple field is compared as a whole list, in order);
 *  - a field submitted empty loses its translation;
 *  - other fields and other languages are untouched, and the whole save is one transaction.
 *
 * The names and descriptions of the files the owner holds in `image` and `file` fields are translated through
 * {@see self::saveFileDescriptions()}, whether or not the field itself is translatable.
 *
 * @example
 *   $saver = app(TranslationSaver::class);
 *   $saver->save($node, 'en', ['title' => 'Hello', 'body' => '']);   // stores the title, removes the body
 *   $saver->save(ValueOwner::forType($type), 'en', ['section_title' => 'News']);
 *   $saver->clear($node, 'en');                                      // removes every English value
 */
class TranslationSaver
{
    public function __construct(
        private readonly ContentValueWriter $writer,
        private readonly ContentValueReader $reader,
        private readonly ContentLanguages $languages,
        private readonly FieldTypeRegistry $registry,
        private readonly FileDescriptions $descriptions,
    ) {}

    /**
     * Store the translations of the submitted fields in one language.
     *
     * @param  array<string, mixed>  $values  Field key => value (a list for multiple fields); only these fields change.
     *
     * @throws ValidationException Errors keyed by field key, or `lang`.
     */
    public function save(ContentNode|ValueOwner $owner, string $lang, array $values): void
    {
        $owner = $owner instanceof ContentNode ? ValueOwner::forNode($owner) : $owner;
        $lang = $this->additionalLanguage($lang);

        // Validates keys (translatable, held by the owner) and values; blank values come back as null.
        [, $fields, $normalized] = $this->writer->validate($owner, $values, $lang);

        $base = $this->baseValues($owner, array_keys($normalized));

        $toWrite = [];
        foreach ($normalized as $key => $value) {
            $toWrite[$key] = $this->equalsBase($fields[$key], $value, $base[$key] ?? null) ? null : $value;
        }

        if ($toWrite === []) {
            return;
        }

        DB::transaction(fn () => $this->writer->write($owner, $toWrite, $lang));
    }

    /**
     * Store the translated names and descriptions of files in one language. Only files the owner holds in `image` or
     * `file` fields count (in its base values or in the values of `$lang`), so one request cannot edit an
     * arbitrary file; a foreign uuid is an error under `file_meta.<uuid>.description` and nothing is stored. A
     * name or description equal to the base one or emptied removes that translation; the original name is never changed (see {@see FileDescriptions}).
     *
     * @param  array<string, array{description?: string|null, name?: string|null}>  $meta  File uuid => the submitted `file_meta` entry; a key that is absent leaves that stored value alone.
     * @param  array<int, string>|null  $keys  Field keys that count (a tab of a type); `null` = every field of the owner.
     *
     * @throws ValidationException Errors keyed `file_meta.<uuid>.description`, or `lang`.
     *
     * @example
     *   $saver->saveFileDescriptions($node, 'en', ['0190a1b27c3e7f0a8b1c2d3e4f5a6b7c' => ['description' => 'Front cover']]);
     */
    public function saveFileDescriptions(ContentNode|ValueOwner $owner, string $lang, array $meta, ?array $keys = null): void
    {
        $owner = $owner instanceof ContentNode ? ValueOwner::forNode($owner) : $owner;
        $lang = $this->additionalLanguage($lang);

        if ($meta === []) {
            return;
        }

        $owned = $this->fileUuids($owner, $lang, $keys);
        $foreign = array_diff(array_keys($meta), $owned);

        if ($foreign !== []) {
            throw ValidationException::withMessages(collect($foreign)->mapWithKeys(
                fn ($uuid) => ["file_meta.{$uuid}.description" => ['The file does not belong to the values being translated.']],
            )->all());
        }

        $this->descriptions->saveMany(array_map(fn ($entry) => array_intersect_key((array) $entry, ['name' => 1, 'description' => 1]), $meta), $lang);
    }

    /**
     * Uuids of the files the owner holds in `image` and `file` fields: those of the base values and of the
     * values in `$lang`.
     *
     * @param  array<int, string>|null  $keys  Field keys that count; `null` = every field of the owner.
     * @return array<int, string>
     */
    public function fileUuids(ContentNode|ValueOwner $owner, string $lang, ?array $keys = null): array
    {
        $owner = $owner instanceof ContentNode ? ValueOwner::forNode($owner) : $owner;
        $fields = $owner->fields(ContentField::query()->where('content_type', $owner->contentType)->get()->keyBy('key'))
            ->filter(fn (ContentField $field) => $this->registry->get($field->type) instanceof AbstractFileFieldType && ($keys === null || in_array($field->key, $keys, true)));

        if ($fields->isEmpty()) {
            return [];
        }

        $uuids = [];

        foreach (['default', $lang] as $language) {
            $values = $this->valuesOf($owner, $fields->keys()->all(), $language);

            foreach ($fields as $field) {
                array_push($uuids, ...$this->registry->get($field->type)->fileUuids($values[$field->key] ?? null));
            }
        }

        return array_values(array_unique($uuids));
    }

    /**
     * Remove the translated values of the owner in one language: every translatable field it holds, or only
     * the given ones (the "Display" and "SEO" tabs of a type clear their own fields).
     *
     * @param  array<int, string>|null  $keys  Field keys to clear; `null` = all translatable fields of the owner.
     *
     * @throws ValidationException With an error under `lang` for a language that is not an active additional one.
     */
    public function clear(ContentNode|ValueOwner $owner, string $lang, ?array $keys = null): void
    {
        $owner = $owner instanceof ContentNode ? ValueOwner::forNode($owner) : $owner;
        $lang = $this->additionalLanguage($lang);
        $translatable = $this->translatableKeys($owner);
        $keys = $keys === null ? $translatable : array_values(array_intersect($keys, $translatable));

        if ($keys !== []) {
            DB::transaction(fn () => $this->writer->write($owner, array_fill_keys($keys, null), $lang));
        }
    }

    /**
     * Keys of the translatable fields the owner holds.
     *
     * @return array<int, string>
     */
    public function translatableKeys(ValueOwner $owner): array
    {
        return $owner->fields(ContentField::query()->where('content_type', $owner->contentType)->get()->keyBy('key'))
            ->filter(fn ($field) => $field->is_translatable)
            ->keys()
            ->all();
    }

    /**
     * @throws ValidationException
     */
    private function additionalLanguage(string $lang): string
    {
        $lang = $this->languages->assertWritable($lang);

        if ($lang === config('content.default_lang')) {
            throw ValidationException::withMessages(['lang' => 'A translation needs an additional language, not the base one.']);
        }

        return $lang;
    }

    /**
     * Values of the given fields of an owner in one language.
     *
     * @param  array<int, string>  $keys
     * @return array<string, mixed>
     */
    private function valuesOf(ValueOwner $owner, array $keys, string $lang): array
    {
        if ($owner->isType()) {
            return $this->reader->loadForType(ContentType::query()->where('key', $owner->contentType)->firstOrFail(), $keys, $lang);
        }

        $node = ContentNode::query()->findOrFail($owner->nodeId);

        return $this->reader->load([$node], $keys, $lang)[$node->id] ?? [];
    }

    /**
     * Base values of the given fields of an owner.
     *
     * @param  array<int, string>  $keys
     * @return array<string, mixed>
     */
    private function baseValues(ValueOwner $owner, array $keys): array
    {
        if ($keys === []) {
            return [];
        }

        if ($owner->isType()) {
            return $this->reader->loadForType(ContentType::query()->where('key', $owner->contentType)->firstOrFail(), $keys);
        }

        $node = ContentNode::query()->findOrFail($owner->nodeId);

        return $this->reader->load([$node], $keys)[$node->id] ?? [];
    }

    /**
     * Whether a submitted value is the same as the base value once both are in their stored form.
     */
    private function equalsBase(ContentField $field, mixed $value, mixed $base): bool
    {
        if ($value === null || $value === []) {
            return false;
        }

        $type = $this->registry->get($field->type);
        $stored = fn (mixed $item) => $type instanceof ComposedValue ? $type->split($item) : $type->prepare($item);

        if ($field->is_multiple) {
            return is_array($base) && $base !== []
                && array_map($stored, array_values($value)) === array_map($stored, array_values($base));
        }

        return $base !== null && $stored($value) === $stored($base);
    }
}
