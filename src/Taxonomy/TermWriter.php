<?php

namespace Webtack\Ecms\Taxonomy;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Webtack\Ecms\Content\ContentLanguages;
use Webtack\Ecms\Files\FileRelations;
use Webtack\Ecms\Files\Rules\FileValue;
use Webtack\Ecms\Models\ContentField;
use Webtack\Ecms\Models\Term;
use Webtack\Ecms\Models\TermTranslation;
use Webtack\Ecms\Models\Vocabulary;

/**
 * Creates and updates a term together with its translations and image in one transaction.
 *
 * Input keys: `parent_uuid`, `slug`, `weight`, `image` (`{uuid}.{ext}` of an `image` file, blank
 * clears it) and `translations` (`lang => ['name' => ..., 'description' => ...]`). Only the keys
 * present are applied. The `default` name is required; a translation of another language holds a name, a description or both (each optional) and is deleted
 * when it holds neither. A missing `slug` is generated from the `default` name.
 *
 * {@see self::saveTranslation()} and {@see self::clearTranslation()} change one language only (the
 * admin translation mode).
 *
 * @example
 *   $term = app(TermWriter::class)->create($vocabulary, [
 *       'translations' => ['default' => ['name' => 'Куртки'], 'en' => ['name' => 'Jackets']],
 *       'parent_uuid' => $clothes->uuid,
 *   ]); // slug "kurtky"
 *   app(TermWriter::class)->update($term, ['image' => '0190....png']);
 */
class TermWriter
{
    /** Room kept for a numeric suffix within the 190-character limit. */
    private const SLUG_BASE_LENGTH = 180;

    public function __construct(
        private readonly ContentLanguages $languages,
        private readonly FileRelations $fileRelations,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     *
     * @throws ValidationException
     */
    public function create(Vocabulary $vocabulary, array $data): Term
    {
        $translations = $this->normalizeTranslations($data['translations'] ?? [], creating: true);
        $image = $this->validatedImage($data);

        return DB::transaction(function () use ($vocabulary, $data, $translations, $image) {
            $term = new Term([
                'vocabulary_key' => $vocabulary->key,
                'parent_uuid' => $data['parent_uuid'] ?? null,
                'slug' => ($data['slug'] ?? '') !== '' ? $data['slug'] : $this->generateSlug($vocabulary, $translations['default']['name']),
                'weight' => $data['weight'] ?? 0,
                'image' => $image,
            ]);
            $term->save();

            $this->storeTranslations($term, $translations);
            $this->syncImage($term);

            return $term;
        });
    }

    /**
     * @param  array<string, mixed>  $data
     *
     * @throws ValidationException
     */
    public function update(Term $term, array $data): Term
    {
        $translations = $this->normalizeTranslations($data['translations'] ?? [], creating: false);
        $image = array_key_exists('image', $data) ? $this->validatedImage($data) : $term->image;

        return DB::transaction(function () use ($term, $data, $translations, $image) {
            $attributes = array_intersect_key($data, array_flip(['parent_uuid', 'slug', 'weight']));
            $term->fill([...$attributes, 'image' => $image])->save();

            $this->storeTranslations($term, $translations);
            $this->syncImage($term);

            return $term;
        });
    }

    /**
     * Normalise the languages and drop empty descriptions. `default` must carry a name when creating,
     * and a given `default` name may never be blank.
     *
     * @param  array<string, mixed>  $translations
     * @return array<string, array{name: string|null, description: string|null}>
     */
    private function normalizeTranslations(array $translations, bool $creating): array
    {
        $normalized = [];

        foreach ($translations as $lang => $texts) {
            $lang = $this->languages->assertWritable((string) $lang);
            $name = trim((string) ($texts['name'] ?? ''));
            $description = trim((string) ($texts['description'] ?? ''));

            Validator::make(['name' => $name, 'description' => $description], [
                'name' => ['string', 'max:255'],
                'description' => ['string', 'max:65000'],
            ])->validate();

            $normalized[$lang] = ['name' => $name === '' ? null : $name, 'description' => $description === '' ? null : $description];
        }

        $default = config('content.default_lang');

        if (($creating || isset($normalized[$default])) && ($normalized[$default]['name'] ?? null) === null) {
            throw ValidationException::withMessages(["translations.{$default}.name" => 'The name in the default language is required.']);
        }

        return $normalized;
    }

    /**
     * The image from the input, checked as a stored `image` file; `null` when cleared or absent.
     *
     * @param  array<string, mixed>  $data
     */
    private function validatedImage(array $data): ?string
    {
        $image = isset($data['image']) && trim((string) $data['image']) !== '' ? (string) $data['image'] : null;

        if ($image !== null) {
            Validator::make(['image' => $image], [
                'image' => ['string', new FileValue('image', new ContentField(['type' => 'image']))],
            ])->validate();
        }

        return $image;
    }

    /**
     * @param  array<string, array{name: string|null, description: string|null}>  $translations
     */
    private function storeTranslations(Term $term, array $translations): void
    {
        foreach ($translations as $lang => $texts) {
            $this->persist($term, $lang, $texts['name'], $texts['description']);
        }
    }

    /**
     * Writes the translation row of one language, or deletes it when neither field has a value.
     */
    private function persist(Term $term, string $lang, ?string $name, ?string $description): void
    {
        $existing = TermTranslation::query()->where('term_uuid', $term->uuid)->where('lang', $lang)->first();

        if ($name === null && $description === null) {
            $existing?->delete();

            return;
        }

        ($existing ?? new TermTranslation(['term_uuid' => $term->uuid, 'lang' => $lang]))
            ->fill(['name' => $name, 'description' => $description])
            ->save();
    }

    /**
     * Saves the translation of one additional language and touches nothing else.
     *
     * Each field is stored only when it is non-empty and differs from the `default` value of the same
     * field; the row is deleted when nothing remains. Other languages and the base values are unchanged.
     *
     * @param  array{name?: string|null, description?: string|null}  $data
     *
     * @throws ValidationException Under `lang` for the base, an unknown or an inactive language; under `name`/`description` for invalid text.
     *
     * @example
     *   // base name "Куртки": only the description is stored for `en`
     *   app(TermWriter::class)->saveTranslation($term, 'en', ['name' => 'Куртки', 'description' => 'Warm jackets']);
     */
    public function saveTranslation(Term $term, string $lang, array $data): void
    {
        $lang = $this->assertAdditionalLanguage($lang);

        $name = trim((string) ($data['name'] ?? ''));
        $description = trim((string) ($data['description'] ?? ''));

        Validator::make(['name' => $name, 'description' => $description], [
            'name' => ['string', 'max:255'],
            'description' => ['string', 'max:65000'],
        ])->validate();

        $base = TermTranslation::query()->where('term_uuid', $term->uuid)->where('lang', config('content.default_lang'))->first();

        $this->persist(
            $term,
            $lang,
            $name === '' || $name === $base?->name ? null : $name,
            $description === '' || $description === $base?->description ? null : $description,
        );
    }

    /**
     * Deletes the translation of one additional language; a missing one is not an error.
     *
     * @throws ValidationException Under `lang` for the base, an unknown or an inactive language.
     */
    public function clearTranslation(Term $term, string $lang): void
    {
        $lang = $this->assertAdditionalLanguage($lang);

        TermTranslation::query()->where('term_uuid', $term->uuid)->where('lang', $lang)->delete();
    }

    /**
     * @throws ValidationException
     */
    private function assertAdditionalLanguage(string $lang): string
    {
        $lang = $this->languages->assertWritable($lang);

        if ($lang === config('content.default_lang')) {
            throw ValidationException::withMessages(['lang' => 'The base language is edited in the base form.']);
        }

        return $lang;
    }

    private function syncImage(Term $term): void
    {
        $uuids = $term->image !== null && preg_match(FileValue::PATTERN, $term->image, $match) === 1 ? [$match[1]] : [];

        $this->fileRelations->sync(Term::MEDIA_OWNER, $term->id, 'image', config('content.default_lang'), $uuids);
    }

    /**
     * A free slug in the vocabulary from a name: transliterated, with `-2`, `-3` ... when taken.
     */
    private function generateSlug(Vocabulary $vocabulary, string $name): string
    {
        $base = trim(Str::limit(Str::slug($name, '-', 'uk'), self::SLUG_BASE_LENGTH, ''), '-');
        $base = $base === '' ? 'term' : $base;

        $taken = DB::table('taxonomy_terms')
            ->where('vocabulary_key', $vocabulary->key)
            ->where(fn ($query) => $query->where('slug', $base)->orWhere('slug', 'like', $base.'-%'))
            ->pluck('slug')->map(fn (string $slug) => strtolower($slug))->flip();

        if (! $taken->has($base)) {
            return $base;
        }

        for ($suffix = 2; $taken->has("{$base}-{$suffix}"); $suffix++);

        return "{$base}-{$suffix}";
    }
}
