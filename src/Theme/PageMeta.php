<?php

namespace Webtack\Ecms\Theme;

use Illuminate\Support\Collection;
use Webtack\Ecms\Content\ContentValueReader;
use Webtack\Ecms\Content\HtmlSanitizer;
use Webtack\Ecms\Content\LocalizedUrl;
use Webtack\Ecms\Content\PublicDisplaySettings;
use Webtack\Ecms\Content\ValueOwner;
use Webtack\Ecms\Files\FileStorage;
use Webtack\Ecms\Files\Rules\FileValue;
use Webtack\Ecms\Models\ContentField;
use Webtack\Ecms\Models\ContentType;
use Webtack\Ecms\Settings\SiteSettings;
use Webtack\Ecms\Settings\SiteSettingsView;

/**
 * The document metadata (`$meta`) of a public page: `title`, `description` and `image`, each taken on its own from
 * the first source that has a value:
 *
 * 1. the record's own `meta_title`, `meta_description` and `meta_image` values;
 * 2. the field the record's type chose for it in "Meta data" on its "SEO" tab (`settings.public.meta`, see
 *    {@see PublicDisplaySettings}): a `string` field for the title, a `text` or `richtext` one for the description,
 *    an `image` or `youtube` one for the image;
 * 3. (title only) the record's own `title`;
 * 4. the `meta_title`, `meta_description` and `meta_image` values of the type itself (its "SEO" tab, base language);
 *    for the title, when the type has no `meta_title`, its Section title ({@see TypeSection::section()}, reading
 *    language then base; never the administrative `name`);
 * 5. the site settings: the description and the large logo (the small one when there is no large one). The title has
 *    no site source here: {@see PublicData} falls back to the site name.
 *
 * A multiple field gives its first value. The description of a field or of the type has its HTML removed (richtext),
 * its white space collapsed and is cut to `content.meta_description_max` characters at a word boundary with a
 * trailing "…". A `youtube` field gives the stored screenshot of the video, and nothing when the video has none. An
 * empty value counts as missing, so the next source is tried. A page without a record (a type's list) has the
 * sources 4 and 5; home and 404 have the last one only. The values of the type are read only when an earlier source
 * did not supply the value, and only for types that hold them (not for a block).
 *
 * @example
 *   // The type picked `summary` (richtext) for the description and `cover` (image) for the image; the record has no meta_*
 *   app(PageMeta::class)->make(['summary' => '<p>Hello <b>world</b></p>', 'cover' => '0190….png'], $type);
 *   // ['title' => null, 'description' => 'Hello world', 'image' => '/storage/0190….png']
 *
 *   // The list page of a `content` type with section_title "Latest news" and no meta_title
 *   app(PageMeta::class)->make([], $news); // ['title' => 'Latest news', …]
 */
class PageMeta
{
    public function __construct(
        private readonly SiteSettings $site,
        private readonly FileStorage $files,
        private readonly PublicDisplaySettings $display,
        private readonly HtmlSanitizer $html,
        private readonly ContentValueReader $reader,
        private readonly TypeSection $sections,
    ) {}

    /**
     * @param  array<string, mixed>  $values  Values of the shown record by field key (empty without a record).
     * @param  ContentType|null  $type  Type of the shown record, if any.
     * @return array{title: string|null, description: string|null, image: string|null}
     */
    public function make(array $values = [], ?ContentType $type = null): array
    {
        $chosen = $type === null ? [] : $this->chosen($type);
        $field = fn (string $name) => $chosen[$name] ?? null;
        $own = $this->typeValues($type);

        return [
            'title' => $this->text($values['meta_title'] ?? null)
                ?? $this->fieldTitle($values, $field('title'))
                ?? $this->text($values['title'] ?? null)
                ?? $this->text($own('meta_title'))
                ?? ($type === null ? null : $this->text($this->sections->section($type))),
            'description' => $this->text($values['meta_description'] ?? null)
                ?? $this->fieldDescription($values, $field('description'))
                ?? $this->plain($own('meta_description'))
                ?? $this->text($this->readingSite()->description()),
            'image' => $this->imageUrl($values['meta_image'] ?? null)
                ?? $this->fieldImage($values, $field('image'))
                ?? $this->imageUrl($own('meta_image'))
                ?? $this->imageUrl($this->readingSite()->logoLarge() ?? $this->readingSite()->logoSmall()),
        ];
    }

    /** The site settings read in the reading language of the request (the `default` ones fill what is not translated). */
    private function readingSite(): SiteSettingsView
    {
        return $this->site->forLang(app(LocalizedUrl::class)->lang());
    }

    /**
     * A reader of the `meta_*` values of the type itself, by field key. The values are loaded on the first call
     * (in the reading language, with the fallback to the base one) and only for the `meta_*` fields the type holds, so a type without them costs no query.
     *
     * @return \Closure(string): mixed
     */
    private function typeValues(?ContentType $type): \Closure
    {
        $loaded = null;

        return function (string $key) use ($type, &$loaded) {
            if ($type === null) {
                return null;
            }

            if ($loaded === null) {
                $held = ValueOwner::forType($type)->fields($type->fields->keyBy('key'))->keys()
                    ->intersect(['meta_title', 'meta_description', 'meta_image'])->values()->all();
                $loaded = $held === [] ? [] : $this->reader->loadForType($type, $held, app(LocalizedUrl::class)->lang(), app(LocalizedUrl::class)->isAdditional());
            }

            return $this->first($loaded[$key] ?? null);
        };
    }

    /**
     * The fields chosen for the meta data of a type, by meta name.
     *
     * @return array<string, ContentField>
     */
    private function chosen(ContentType $type): array
    {
        /** @var Collection<string, ContentField> $fields */
        $fields = ValueOwner::forNodesOfType($type->key)->fields($type->fields->keyBy('key'));

        return collect($this->display->clean($type, $fields)['meta'] ?? [])
            ->map(fn (string $key) => $fields[$key] ?? null)
            ->filter()
            ->all();
    }

    private function fieldTitle(array $values, ?ContentField $field): ?string
    {
        return $field === null ? null : $this->text($this->first($values[$field->key] ?? null));
    }

    private function fieldDescription(array $values, ?ContentField $field): ?string
    {
        $value = $field === null ? null : $this->first($values[$field->key] ?? null);

        if (! is_string($value)) {
            return null;
        }

        $text = $field->type === 'richtext' ? $this->html->text($value) : trim((string) preg_replace('/\s+/u', ' ', $value));

        return $text === '' ? null : $this->shorten($text);
    }

    private function fieldImage(array $values, ?ContentField $field): ?string
    {
        $value = $field === null ? null : $this->first($values[$field->key] ?? null);

        // A video is shown by its stored screenshot; without one the field has no image.
        return $this->imageUrl($field?->type === 'youtube' ? (is_array($value) ? ($value['screen'] ?? null) : null) : $value);
    }

    /** A plain text value with its white space collapsed and cut to the description length, or null when empty. */
    private function plain(mixed $value): ?string
    {
        $text = is_string($value) ? trim((string) preg_replace('/\s+/u', ' ', $value)) : '';

        return $text === '' ? null : $this->shorten($text);
    }

    /** The first value of a multiple field, or the value itself. */
    private function first(mixed $value): mixed
    {
        return is_array($value) && array_is_list($value) ? ($value[0] ?? null) : $value;
    }

    private function text(mixed $value): ?string
    {
        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }

    private function imageUrl(mixed $value): ?string
    {
        return is_string($value) && preg_match(FileValue::PATTERN, $value) === 1 ? $this->files->urlForValue('image', $value) : null;
    }

    /**
     * Cuts a text longer than `content.meta_description_max` at the last word boundary that leaves room for "…".
     *
     * @example
     *   // max 10: 'Hello wonderful world' → 'Hello…'
     */
    private function shorten(string $text): string
    {
        $max = (int) config('content.meta_description_max');

        if (mb_strlen($text) <= $max) {
            return $text;
        }

        $cut = mb_substr($text, 0, $max - 1);
        $space = mb_strrpos($cut, ' ');

        return preg_replace('/[\s,.;:!?\-—]+$/u', '', $space === false ? $cut : mb_substr($cut, 0, $space)).'…';
    }
}
