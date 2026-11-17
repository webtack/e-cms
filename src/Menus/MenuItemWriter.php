<?php

namespace Webtack\Ecms\Menus;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Webtack\Ecms\Content\ContentLanguages;
use Webtack\Ecms\Files\FileRelations;
use Webtack\Ecms\Files\Rules\FileValue;
use Webtack\Ecms\Models\ContentField;
use Webtack\Ecms\Models\Menu;
use Webtack\Ecms\Models\MenuItem;
use Webtack\Ecms\Models\MenuItemTranslation;

/**
 * Creates and updates a menu item together with its translations and image in one transaction.
 *
 * Input keys: `parent_uuid`, `weight`, `url` (blank clears it), `target`, `css_class`, `is_active`,
 * `published_at`, `unpublished_at` (blank clears them; stored to the minute), `image`
 * (`{uuid}.{ext}` of an `image` file, blank clears it) and `translations`
 * (`lang => ['title' => ..., 'description' => ...]`). Only the keys present are applied.
 * The `default` title is required; a translation of another language holds a title, a description or both
 * (each optional) and is deleted when it holds neither.
 * {@see self::saveTranslation()} and {@see self::clearTranslation()} change one language only (the admin
 * translation mode).
 * Disabling an item (`is_active = false`) never touches its dates.
 *
 * @example
 *   $item = app(MenuItemWriter::class)->create($menu, [
 *       'url' => '/about',
 *       'translations' => ['default' => ['title' => 'Про нас'], 'en' => ['title' => 'About us']],
 *   ]);
 *   app(MenuItemWriter::class)->update($item, ['is_active' => false, 'image' => '0190....png']);
 */
class MenuItemWriter
{
    /** Attributes copied from the input to the model as they are. */
    private const ATTRIBUTES = ['parent_uuid', 'weight', 'url', 'target', 'css_class', 'is_active'];

    /** Date attributes: parsed, cut to the minute. */
    private const DATES = ['published_at', 'unpublished_at'];

    public function __construct(
        private readonly ContentLanguages $languages,
        private readonly FileRelations $fileRelations,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     *
     * @throws ValidationException
     */
    public function create(Menu $menu, array $data): MenuItem
    {
        $translations = $this->normalizeTranslations($data['translations'] ?? [], creating: true);
        $image = $this->validatedImage($data);
        $dates = $this->dates($data);

        return DB::transaction(function () use ($menu, $data, $translations, $image, $dates) {
            $item = new MenuItem([
                ...array_intersect_key($data, array_flip(self::ATTRIBUTES)),
                ...$dates,
                'menu_key' => $menu->key,
                'image' => $image,
            ]);
            $item->save();

            $this->storeTranslations($item, $translations);
            $this->syncImage($item);

            return $item;
        });
    }

    /**
     * @param  array<string, mixed>  $data
     *
     * @throws ValidationException
     */
    public function update(MenuItem $item, array $data): MenuItem
    {
        $translations = $this->normalizeTranslations($data['translations'] ?? [], creating: false);
        $image = array_key_exists('image', $data) ? $this->validatedImage($data) : $item->image;
        $dates = $this->dates($data);

        return DB::transaction(function () use ($item, $data, $translations, $image, $dates) {
            $item->fill([...array_intersect_key($data, array_flip(self::ATTRIBUTES)), ...$dates, 'image' => $image])->save();

            $this->storeTranslations($item, $translations);
            $this->syncImage($item);

            return $item;
        });
    }

    /**
     * The date attributes present in the input as minute-precision UTC values (blank → null).
     *
     * @param  array<string, mixed>  $data
     * @return array<string, Carbon|null>
     *
     * @throws ValidationException
     */
    private function dates(array $data): array
    {
        $dates = [];

        foreach (self::DATES as $key) {
            if (! array_key_exists($key, $data)) {
                continue;
            }

            $value = $data[$key];

            if ($value === null || (is_string($value) && trim($value) === '')) {
                $dates[$key] = null;

                continue;
            }

            Validator::make([$key => $value], [$key => ['date']])->validate();

            $dates[$key] = Carbon::parse($value)->utc()->startOfMinute();
        }

        return $dates;
    }

    /**
     * Normalise the languages and drop empty descriptions. `default` must carry a title when creating,
     * and a given `default` title may never be blank.
     *
     * @param  array<string, mixed>  $translations
     * @return array<string, array{title: string|null, description: string|null}>
     */
    private function normalizeTranslations(array $translations, bool $creating): array
    {
        $normalized = [];

        foreach ($translations as $lang => $texts) {
            $lang = $this->languages->assertWritable((string) $lang);
            $title = trim((string) ($texts['title'] ?? ''));
            $description = trim((string) ($texts['description'] ?? ''));

            Validator::make(['title' => $title, 'description' => $description], [
                'title' => ['string', 'max:255'],
                'description' => ['string', 'max:65000'],
            ])->validate();

            $normalized[$lang] = ['title' => $title === '' ? null : $title, 'description' => $description === '' ? null : $description];
        }

        $default = config('content.default_lang');

        if (($creating || isset($normalized[$default])) && ($normalized[$default]['title'] ?? null) === null) {
            throw ValidationException::withMessages(["translations.{$default}.title" => 'The title in the default language is required.']);
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
     * @param  array<string, array{title: string|null, description: string|null}>  $translations
     */
    private function storeTranslations(MenuItem $item, array $translations): void
    {
        foreach ($translations as $lang => $texts) {
            $this->persist($item, $lang, $texts['title'], $texts['description']);
        }
    }

    /**
     * Writes the translation row of one language, or deletes it when neither field has a value.
     */
    private function persist(MenuItem $item, string $lang, ?string $title, ?string $description): void
    {
        $existing = MenuItemTranslation::query()->where('item_uuid', $item->uuid)->where('lang', $lang)->first();

        if ($title === null && $description === null) {
            $existing?->delete();

            return;
        }

        ($existing ?? new MenuItemTranslation(['item_uuid' => $item->uuid, 'lang' => $lang]))
            ->fill(['title' => $title, 'description' => $description])
            ->save();
    }

    /**
     * Saves the translation of one additional language and touches nothing else.
     *
     * Each field is stored only when it is non-empty and differs from the `default` value of the same
     * field; the row is deleted when nothing remains. Other languages and the base values are unchanged.
     *
     * @param  array{title?: string|null, description?: string|null}  $data
     *
     * @throws ValidationException Under `lang` for the base, an unknown or an inactive language; under `title`/`description` for invalid text.
     *
     * @example
     *   // base title "Про нас": only the description is stored for `en`
     *   app(MenuItemWriter::class)->saveTranslation($item, 'en', ['title' => 'Про нас', 'description' => 'Who we are']);
     */
    public function saveTranslation(MenuItem $item, string $lang, array $data): void
    {
        $lang = $this->assertAdditionalLanguage($lang);

        $title = trim((string) ($data['title'] ?? ''));
        $description = trim((string) ($data['description'] ?? ''));

        Validator::make(['title' => $title, 'description' => $description], [
            'title' => ['string', 'max:255'],
            'description' => ['string', 'max:65000'],
        ])->validate();

        $base = MenuItemTranslation::query()->where('item_uuid', $item->uuid)->where('lang', config('content.default_lang'))->first();

        $this->persist(
            $item,
            $lang,
            $title === '' || $title === $base?->title ? null : $title,
            $description === '' || $description === $base?->description ? null : $description,
        );
    }

    /**
     * Deletes the translation of one additional language; a missing one is not an error.
     *
     * @throws ValidationException Under `lang` for the base, an unknown or an inactive language.
     */
    public function clearTranslation(MenuItem $item, string $lang): void
    {
        $lang = $this->assertAdditionalLanguage($lang);

        MenuItemTranslation::query()->where('item_uuid', $item->uuid)->where('lang', $lang)->delete();
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

    private function syncImage(MenuItem $item): void
    {
        $uuids = $item->image !== null && preg_match(FileValue::PATTERN, $item->image, $match) === 1 ? [$match[1]] : [];

        $this->fileRelations->sync(MenuItem::MEDIA_OWNER, $item->id, 'image', config('content.default_lang'), $uuids);
    }
}
