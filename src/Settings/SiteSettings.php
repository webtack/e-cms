<?php

namespace Webtack\Ecms\Settings;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;
use Webtack\Ecms\Content\ContentLanguages;
use Webtack\Ecms\Files\FileRelations;
use Webtack\Ecms\Files\Owners\SiteSettingOwner;
use Webtack\Ecms\Files\Rules\FileValue;
use Webtack\Ecms\Support\AppSettings;

/**
 * Reader and writer of the general site settings (`site.*` in {@see AppSettings}).
 *
 * Stored keys: `site.name`, `site.short_name`, `site.description`, `site.address`, `site.schedule`, `site.logo_large`, `site.logo_small`
 * (translatable: `{"default": …, "en": …}`), `site.emails`, `site.phones`, `site.socials` (ordered lists
 * whose `label` is translatable) and `site.indexable` (bool, `false` when absent). Every reader returns
 * the value of the requested language (the current locale by default) and falls back to `default`.
 *
 * The form edits one language, `default`: {@see self::formValues()} unwraps it and {@see self::save()}
 * wraps it back, merging into the stored object so other languages are never erased. `save()` writes all
 * keys and the logo relations in one transaction.
 *
 * @example
 *   $site = app(SiteSettings::class);
 *   $site->save(['name' => 'My site', 'phones' => [['value' => '+380 44 000', 'label' => 'Sales']]]);
 *   $site->name();      // 'My site'
 *   $site->phones();    // [['value' => '+380 44 000', 'label' => 'Sales']]
 *   $site->indexable(); // false
 */
class SiteSettings
{
    public const LANG = 'default';

    private const PREFIX = 'site.';

    /** Logo setting key → relation role (the same word). */
    private const LOGOS = ['logo_large', 'logo_small'];

    /** Single-text translatable settings (the logos are base-only). */
    private const TEXTS = ['name', 'short_name', 'description', 'address'];

    /** Lists with a translatable `label`: key → identity fields of an item. */
    private const LISTS = ['emails' => ['value'], 'phones' => ['value'], 'socials' => ['network', 'url']];

    public function __construct(
        private readonly AppSettings $settings,
        private readonly FileRelations $fileRelations,
    ) {}

    public function name(?string $lang = null): ?string
    {
        return $this->text('name', $lang);
    }

    public function shortName(?string $lang = null): ?string
    {
        return $this->text('short_name', $lang);
    }

    /** Free-form description of the site (may contain line breaks). */
    public function description(?string $lang = null): ?string
    {
        return $this->text('description', $lang);
    }

    /** Postal address of the site (may contain line breaks). */
    public function address(?string $lang = null): ?string
    {
        return $this->text('address', $lang);
    }

    /** The large logo as `{uuid}.{ext}`, or null. */
    public function logoLarge(?string $lang = null): ?string
    {
        return $this->text('logo_large', $lang);
    }

    /** The small logo as `{uuid}.{ext}`, or null. */
    public function logoSmall(?string $lang = null): ?string
    {
        return $this->text('logo_small', $lang);
    }

    /**
     * @return list<array{value: string, label: ?string}>
     */
    public function emails(?string $lang = null): array
    {
        return $this->list('emails', ['value'], $lang);
    }

    /**
     * @return list<array{value: string, label: ?string}>
     */
    public function phones(?string $lang = null): array
    {
        return $this->list('phones', ['value'], $lang);
    }

    /**
     * @return list<array{network: string, url: string, label: ?string}>
     */
    public function socials(?string $lang = null): array
    {
        return $this->list('socials', ['network', 'url'], $lang);
    }

    /**
     * Work schedule rows, each with four text columns.
     *
     * @return list<list<string>>
     */
    public function schedule(?string $lang = null): array
    {
        $rows = $this->translated('schedule', $lang);

        return is_array($rows) ? array_values(array_map(fn ($row) => array_values((array) $row), $rows)) : [];
    }

    public function indexable(): bool
    {
        return (bool) $this->settings->get(self::PREFIX.'indexable', false);
    }

    /**
     * The values of the `default` language in the shape of the admin form (flat, no language keys).
     *
     * @return array{name: string, short_name: string, description: string, address: string, logo_large: ?string, logo_small: ?string, emails: list<array{value: string, label: string}>, phones: list<array{value: string, label: string}>, socials: list<array{network: string, url: string, label: string}>, schedule: list<list<string>>, indexable: bool}
     */
    public function formValues(): array
    {
        $lang = self::LANG;
        $labelled = fn (array $items) => array_map(fn (array $item) => [...$item, 'label' => $item['label'] ?? ''], $items);

        return [
            'name' => $this->name($lang) ?? '',
            'short_name' => $this->shortName($lang) ?? '',
            'description' => $this->description($lang) ?? '',
            'address' => $this->address($lang) ?? '',
            'logo_large' => $this->logoLarge($lang),
            'logo_small' => $this->logoSmall($lang),
            'emails' => $labelled($this->emails($lang)),
            'phones' => $labelled($this->phones($lang)),
            'socials' => $labelled($this->socials($lang)),
            'schedule' => $this->schedule($lang),
            'indexable' => $this->indexable(),
        ];
    }

    /**
     * Save the form (language `default`). Only the keys present in `$data` are written, so a partial
     * array leaves the rest as it is. Blank texts and labels are dropped, list rows without their
     * required value and schedule rows with four blank columns are skipped, and a setting left with
     * nothing is forgotten. The values are expected to be validated already
     * (`UpdateSiteSettingsRequest`).
     *
     * @param  array<string, mixed>  $data  The form shape of {@see self::formValues()}.
     *
     * @throws Throwable
     */
    public function save(array $data): void
    {
        $this->atomically(function () use ($data) {
            foreach (['name', 'short_name', 'description', 'address', 'logo_large', 'logo_small'] as $key) {
                if (array_key_exists($key, $data)) {
                    $this->storeTranslated($key, $this->clean($data[$key]));
                }
            }

            foreach (['emails' => ['value'], 'phones' => ['value'], 'socials' => ['network', 'url']] as $key => $required) {
                if (array_key_exists($key, $data)) {
                    $this->storeList($key, (array) $data[$key], $required);
                }
            }

            if (array_key_exists('schedule', $data)) {
                $this->storeTranslated('schedule', $this->scheduleRows((array) $data['schedule']));
            }

            if (array_key_exists('indexable', $data)) {
                $this->settings->set(self::PREFIX.'indexable', (bool) $data['indexable']);
            }

            foreach (self::LOGOS as $role) {
                if (array_key_exists($role, $data)) {
                    $this->syncLogo($role, $this->clean($data[$role]));
                }
            }
        });
    }

    /**
     * The translatable settings in the shape of the translation form of one additional language: every
     * value is the stored translation or, when none exists, the base value. List rows are the base rows
     * (identity fields plus the prefilled `label`); logos and the indexing flag are not part of it.
     *
     * @return array{name: string, short_name: string, description: string, address: string, emails: list<array{value: string, label: string}>, phones: list<array{value: string, label: string}>, socials: list<array{network: string, url: string, label: string}>, schedule: list<list<string>>}
     *
     * @example
     *   $site->translationValues('en')['name']; // 'My site' (translated) or the base name
     */
    public function translationValues(string $lang): array
    {
        $labelled = fn (array $items) => array_map(fn (array $item) => [...$item, 'label' => $item['label'] ?? ''], $items);

        return [
            'name' => $this->name($lang) ?? '',
            'short_name' => $this->shortName($lang) ?? '',
            'description' => $this->description($lang) ?? '',
            'address' => $this->address($lang) ?? '',
            'emails' => $labelled($this->emails($lang)),
            'phones' => $labelled($this->phones($lang)),
            'socials' => $labelled($this->socials($lang)),
            'schedule' => $this->schedule($lang),
        ];
    }

    /**
     * Save the translatable settings in one additional language. Only the keys present in `$data` are
     * written. A text equal to the `default` one (trimmed) or submitted blank is removed from the
     * language; the schedule is compared and stored as a whole table. A list is `[row, …]` where each row
     * holds the identity fields of an existing item (`value`; or `network` and `url`) and its `label`:
     * rows that match no stored item are ignored, and rows, their values and their order are never
     * changed. The `default` values, other languages, the logos and the indexing flag stay as they are.
     *
     * @param  array<string, mixed>  $data  The keys of {@see self::translationValues()}.
     *
     * @throws ValidationException Under `lang` for the base language, an unknown or an inactive language.
     * @throws Throwable
     *
     * @example
     *   $site->saveTranslation('en', ['name' => 'My site', 'phones' => [['value' => '+380 44 000', 'label' => 'Sales']]]);
     */
    public function saveTranslation(string $lang, array $data): void
    {
        $lang = $this->additionalLanguage($lang);

        $this->atomically(function () use ($lang, $data) {
            foreach (self::TEXTS as $key) {
                if (array_key_exists($key, $data)) {
                    $this->storeTranslation($key, $lang, $this->clean($data[$key]), $this->baseValue($key));
                }
            }

            if (array_key_exists('schedule', $data)) {
                $this->storeTranslation('schedule', $lang, $this->scheduleRows((array) $data['schedule']), $this->scheduleRows($this->baseValue('schedule') ?? []));
            }

            foreach (self::LISTS as $key => $fields) {
                if (array_key_exists($key, $data)) {
                    $this->storeLabels($key, $fields, $lang, (array) $data[$key]);
                }
            }
        });
    }

    /**
     * Remove every translatable setting of one additional language (texts, schedule and list labels).
     *
     * @throws ValidationException Under `lang` for the base language, an unknown or an inactive language.
     * @throws Throwable
     */
    public function clearTranslation(string $lang): void
    {
        $lang = $this->additionalLanguage($lang);

        $this->atomically(function () use ($lang) {
            foreach ([...self::TEXTS, 'schedule'] as $key) {
                $this->storeTranslation($key, $lang, null, null);
            }

            foreach (self::LISTS as $key => $fields) {
                $this->storeLabels($key, $fields, $lang, null);
            }
        });
    }

    /**
     * Whether any translatable setting (not the logos) holds a value in $lang. Read-only: the code is
     * not validated, so it also answers for a language that is no longer active.
     */
    public function hasTranslation(string $lang): bool
    {
        foreach ([...self::TEXTS, 'schedule'] as $key) {
            $stored = $this->settings->get(self::PREFIX.$key);

            if (is_array($stored) && array_key_exists($lang, $stored)) {
                return true;
            }
        }

        foreach (array_keys(self::LISTS) as $key) {
            foreach ((array) $this->settings->get(self::PREFIX.$key, []) as $item) {
                if (is_array($item['label'] ?? null) && array_key_exists($lang, $item['label'])) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Codes of the given languages that have a translated setting.
     *
     * @param  list<string>  $codes
     * @return list<string>
     */
    public function translatedLanguages(array $codes): array
    {
        return array_values(array_filter($codes, fn (string $code) => $this->hasTranslation($code)));
    }

    /** Run a write in one transaction; on failure forget the cache, which holds values the rollback dropped. */
    private function atomically(callable $write): void
    {
        try {
            DB::transaction($write);
        } catch (Throwable $e) {
            $this->settings->flush();

            throw $e;
        }
    }

    /**
     * @throws ValidationException
     */
    private function additionalLanguage(string $lang): string
    {
        $languages = app(ContentLanguages::class);
        $lang = $languages->assertWritable($lang);

        if ($lang === config('content.default_lang')) {
            throw ValidationException::withMessages(['lang' => 'A translation needs an additional language, not the base one.']);
        }

        return $lang;
    }

    /** The `default` value of a translatable setting, or null. */
    private function baseValue(string $key): mixed
    {
        $stored = $this->settings->get(self::PREFIX.$key);

        return is_array($stored) ? ($stored[self::LANG] ?? null) : null;
    }

    /**
     * Write one language of a translatable setting, or remove it when the value is empty or equals the base.
     */
    private function storeTranslation(string $key, string $lang, mixed $value, mixed $base): void
    {
        $stored = $this->settings->get(self::PREFIX.$key);
        $stored = is_array($stored) ? $stored : [];

        if ($value === null || $value === [] || $value === $base) {
            unset($stored[$lang]);
        } else {
            $stored[$lang] = $value;
        }

        $this->store($key, $stored);
    }

    /**
     * Write the labels of one language of a list. `$rows` null removes the language from every item;
     * otherwise each row is matched to a stored item by its identity and its label is written, or
     * removed when blank or equal to the item's base label. Items without a row are left alone.
     *
     * @param  list<string>  $fields  The identity fields.
     * @param  array<int, mixed>|null  $rows
     */
    private function storeLabels(string $key, array $fields, string $lang, ?array $rows): void
    {
        $items = $this->settings->get(self::PREFIX.$key);

        if (! is_array($items) || $items === []) {
            return;
        }

        $submitted = [];

        foreach ($rows ?? [] as $row) {
            $row = (array) $row;
            $submitted[$this->identity($row, $fields)] ??= $this->clean($row['label'] ?? null);
        }

        $items = array_map(function ($item) use ($fields, $lang, $rows, $submitted) {
            $item = (array) $item;
            $labels = (array) ($item['label'] ?? []);
            $identity = $this->identity($item, $fields);

            if ($rows === null || array_key_exists($identity, $submitted)) {
                $label = $rows === null ? null : $submitted[$identity];

                if ($label === null || $label === ($labels[self::LANG] ?? null)) {
                    unset($labels[$lang]);
                } else {
                    $labels[$lang] = $label;
                }
            }

            unset($item['label']);

            return $labels === [] ? $item : [...$item, 'label' => $labels];
        }, $items);

        $this->store($key, array_values($items));
    }

    /**
     * A translatable setting for $lang, falling back to `default`; null when neither is set.
     */
    private function translated(string $key, ?string $lang): mixed
    {
        $stored = $this->settings->get(self::PREFIX.$key);

        if (! is_array($stored)) {
            return null;
        }

        $lang ??= app()->getLocale();

        $own = $stored[$lang] ?? null;

        // A blank translation is a missing one: the `default` value stands in for it.
        return $own === null || $own === '' || $own === [] ? ($stored[self::LANG] ?? null) : $own;
    }

    /**
     * A read-only view of these settings bound to one reading language (the public site), so a template reads
     * `$site->name()` without passing the language and a language without interface files still gets its
     * translated name. Every value falls back to `default`.
     *
     * @example
     *   app(SiteSettings::class)->forLang('en')->name(); // "Dance club" (English) or the default name
     */
    public function forLang(string $lang): SiteSettingsView
    {
        return new SiteSettingsView($this, $lang);
    }

    private function text(string $key, ?string $lang): ?string
    {
        $value = $this->translated($key, $lang);

        return is_string($value) && $value !== '' ? $value : null;
    }

    /**
     * A stored list with each item's `label` resolved to $lang (falling back to `default`, null when none).
     *
     * @param  list<string>  $fields  The language-independent fields of an item.
     * @return list<array<string, ?string>>
     */
    private function list(string $key, array $fields, ?string $lang): array
    {
        $items = $this->settings->get(self::PREFIX.$key);

        if (! is_array($items)) {
            return [];
        }

        $lang ??= app()->getLocale();

        return array_values(array_map(function ($item) use ($fields, $lang) {
            $result = [];

            foreach ($fields as $field) {
                $result[$field] = (string) ($item[$field] ?? '');
            }

            $label = $item['label'][$lang] ?? $item['label'][self::LANG] ?? null;
            $result['label'] = is_string($label) && $label !== '' ? $label : null;

            return $result;
        }, $items));
    }

    /** A trimmed string, or null when blank or not a string. */
    private function clean(mixed $value): ?string
    {
        $value = is_string($value) ? trim($value) : null;

        return $value === '' ? null : $value;
    }

    /**
     * Write the `default` language of a translatable setting, keeping the other languages; forget the
     * setting when no language is left.
     */
    private function storeTranslated(string $key, mixed $value): void
    {
        $stored = $this->settings->get(self::PREFIX.$key);
        $stored = is_array($stored) ? $stored : [];

        if ($value === null || $value === []) {
            unset($stored[self::LANG]);
        } else {
            $stored[self::LANG] = $value;
        }

        $this->store($key, $stored);
    }

    /**
     * Rewrite a list: each item keeps its other-language labels when the same item (same required
     * fields) was stored before.
     *
     * @param  list<string>  $required
     */
    private function storeList(string $key, array $items, array $required): void
    {
        $previous = [];

        foreach ((array) $this->settings->get(self::PREFIX.$key, []) as $old) {
            $previous[$this->identity((array) $old, $required)] ??= (array) ($old['label'] ?? []);
        }

        $rows = [];

        foreach ($items as $item) {
            $item = (array) $item;
            $row = [];

            foreach ($required as $field) {
                $row[$field] = $this->clean($item[$field] ?? null);
            }

            if (in_array(null, $row, true)) {
                continue;
            }

            $labels = $previous[$this->identity($row, $required)] ?? [];
            $label = $this->clean($item['label'] ?? null);

            if ($label === null) {
                unset($labels[self::LANG]);
            } else {
                $labels[self::LANG] = $label;
            }

            if ($labels !== []) {
                $row['label'] = $labels;
            }

            $rows[] = $row;
        }

        $this->store($key, $rows);
    }

    /**
     * @param  list<string>  $required
     */
    private function identity(array $item, array $required): string
    {
        return json_encode(array_map(fn (string $field) => (string) ($item[$field] ?? ''), $required), JSON_THROW_ON_ERROR);
    }

    /**
     * Schedule rows of four trimmed columns; rows with all columns blank are dropped.
     *
     * @return list<list<string>>
     */
    private function scheduleRows(array $rows): array
    {
        $result = [];

        foreach ($rows as $row) {
            $columns = array_map(fn ($cell) => $this->clean($cell) ?? '', array_slice(array_values((array) $row), 0, 4));
            $columns = array_pad($columns, 4, '');

            if (implode('', $columns) !== '') {
                $result[] = $columns;
            }
        }

        return $result;
    }

    /** Write a value, or forget the setting when it is empty. */
    private function store(string $key, mixed $value): void
    {
        if ($value === [] || $value === null) {
            $this->settings->forget(self::PREFIX.$key);
        } else {
            $this->settings->set(self::PREFIX.$key, $value);
        }
    }

    /** Make the logo relation of $role match the stored `default` value. */
    private function syncLogo(string $role, ?string $value): void
    {
        $uuids = $value !== null && preg_match(FileValue::PATTERN, $value, $match) === 1 ? [$match[1]] : [];

        $this->fileRelations->sync(SiteSettingOwner::TYPE, SiteSettingOwner::ID, $role, self::LANG, $uuids);
    }
}
