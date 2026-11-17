<?php

namespace Webtack\Ecms\Content\FieldTypes;

use Closure;
use Illuminate\Support\Facades\Validator;
use Webtack\Ecms\Content\PresentContext;
use Webtack\Ecms\Files\FileStorage;
use Webtack\Ecms\Files\Rules\FileValue;
use Webtack\Ecms\Models\ContentField;

/**
 * A YouTube video: the link as the editor entered it, plus the video id, title, description and a
 * screenshot (a file of the `image` profile) kept as JSON in `options` (see `ComposedValue`).
 *
 * The composed value is `['url', 'video_id', 'title', 'description', 'screen']`. Input may also be
 * a bare link or an 11-character id. The id is always taken from the link, never from the input.
 * Saving never calls YouTube; the data comes from the lookup request (`Webtack\Ecms\Youtube\YoutubeLookup`).
 *
 * The link is kept as typed (with `t` and other parameters); only a Shorts link and a bare id become
 * `https://www.youtube.com/watch?v={id}`. One video can be saved in several forms, so only the
 * `contains` filter is supported: search by the video id.
 *
 * @example
 *   YoutubeType::parse('https://youtu.be/dQw4w9WgXcQ?t=10');
 *   // ['id' => 'dQw4w9WgXcQ', 'url' => 'https://youtu.be/dQw4w9WgXcQ?t=10']
 *   YoutubeType::parse('https://www.youtube.com/shorts/dQw4w9WgXcQ');
 *   // ['id' => 'dQw4w9WgXcQ', 'url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ']
 *   YoutubeType::parse('https://www.youtube.com/@channel'); // null
 */
class YoutubeType extends AbstractFieldType implements ComposedValue, HasUploadProfile, TracksFiles
{
    /** Longest stored link, the size of the `value` column. */
    public const MAX_URL = 512;

    private const HOSTS = [
        'youtube.com', 'www.youtube.com', 'm.youtube.com', 'music.youtube.com',
        'youtube-nocookie.com', 'www.youtube-nocookie.com', 'youtu.be',
    ];

    private const ID = '[A-Za-z0-9_-]{11}';

    /**
     * Read the video id from a link or a bare id, and build the link to store.
     *
     * @return array{id: string, url: string}|null `null` when the text is not a link to a video.
     */
    public static function parse(string $input): ?array
    {
        $input = trim($input);

        if (preg_match('/^'.self::ID.'$/', $input) === 1) {
            return ['id' => $input, 'url' => self::watchUrl($input)];
        }

        $parts = parse_url($input);
        $host = strtolower($parts['host'] ?? '');

        if (! in_array($parts['scheme'] ?? '', ['http', 'https'], true) || ! in_array($host, self::HOSTS, true)) {
            return null;
        }

        $path = $parts['path'] ?? '';

        if ($host === 'youtu.be') {
            return preg_match('#^/('.self::ID.')/?$#', $path, $m) === 1 ? ['id' => $m[1], 'url' => $input] : null;
        }

        if (preg_match('#^/(?:embed|live)/('.self::ID.')/?$#', $path, $m) === 1) {
            return ['id' => $m[1], 'url' => $input];
        }

        if (preg_match('#^/shorts/('.self::ID.')/?$#', $path, $m) === 1) {
            parse_str($parts['query'] ?? '', $query);
            $rest = http_build_query($query);

            return ['id' => $m[1], 'url' => self::watchUrl($m[1]).($rest === '' ? '' : '&'.$rest)];
        }

        if ($path === '/watch') {
            parse_str($parts['query'] ?? '', $query);

            return isset($query['v']) && is_string($query['v']) && preg_match('/^'.self::ID.'$/', $query['v']) === 1
                ? ['id' => $query['v'], 'url' => $input]
                : null;
        }

        return null;
    }

    private static function watchUrl(string $id): string
    {
        return 'https://www.youtube.com/watch?v='.$id;
    }

    public function key(): string
    {
        return 'youtube';
    }

    public function table(): string
    {
        return 'content_values_string';
    }

    public function uploadProfile(): string
    {
        return 'image';
    }

    public function rules(ContentField $field): array
    {
        return [function (string $attribute, mixed $value, Closure $fail) use ($field): void {
            $value = is_array($value) ? $value : ['url' => $value];

            $validator = Validator::make($value, [
                'url' => ['required', 'string', function (string $name, mixed $url, Closure $failUrl): void {
                    $parsed = self::parse($url);

                    if ($parsed === null) {
                        $failUrl("The {$name} must be a link to a YouTube video.");
                    } elseif (mb_strlen(trim($url)) > self::MAX_URL || mb_strlen($parsed['url']) > self::MAX_URL) {
                        $failUrl('The '.$name.' must not be greater than '.self::MAX_URL.' characters.');
                    }
                }],
                'title' => ['nullable', 'string', 'max:255'],
                'description' => ['nullable', 'string', 'max:10000'],
                // Filters validate with an empty field (see ContentNodeQuery): only the shape then.
                'screen' => ['nullable', 'string', new FileValue('image', $field->type === null ? null : $field)],
            ]);

            if ($validator->fails()) {
                $fail("The {$attribute}: ".$validator->errors()->first());
            }
        }];
    }

    public function split(mixed $input): array
    {
        $input = is_array($input) ? $input : ['url' => $input];
        $parsed = self::parse((string) $input['url']);

        $options = ['video_id' => $parsed['id']];
        foreach (['title', 'description', 'screen'] as $key) {
            $text = isset($input[$key]) ? trim((string) $input[$key]) : '';

            if ($text !== '') {
                $options[$key] = $text;
            }
        }

        return [$parsed['url'], $options];
    }

    public function compose(mixed $scalar, ?array $options): mixed
    {
        return [
            'url' => $scalar,
            'video_id' => $options['video_id'] ?? null,
            'title' => $options['title'] ?? null,
            'description' => $options['description'] ?? null,
            'screen' => $options['screen'] ?? null,
        ];
    }

    public function isBlank(mixed $value): bool
    {
        $url = is_array($value) ? ($value['url'] ?? null) : $value;

        return ! is_string($url) || trim($url) === '';
    }

    /**
     * The `media.uuid` of the screenshot of a value, or of every value of a multiple field.
     *
     * One composed value is an associative array, so it is told from a list with `array_is_list`.
     *
     * @return list<string>
     */
    public function fileUuids(mixed $storedValue): array
    {
        if (! is_array($storedValue)) {
            return [];
        }

        $uuids = [];

        foreach (array_is_list($storedValue) ? $storedValue : [$storedValue] as $item) {
            $screen = is_array($item) ? ($item['screen'] ?? null) : null;

            if (is_string($screen) && preg_match(FileValue::PATTERN, $screen, $match) === 1) {
                $uuids[] = $match[1];
            }
        }

        return array_values(array_unique($uuids));
    }

    /**
     * The video for a template: the id, the watch link, the privacy-friendly embed link and the texts.
     * The embed link is built from the id only (which must be 11 URL-safe characters), never from stored text.
     *
     * @return array{id: string, url: string, embed: string, title: string|null, description: string|null, screen: string|null}|null `null` when the value has no valid video id.
     *
     * @example
     *   $type->present(['url' => 'https://youtu.be/dQw4w9WgXcQ', 'video_id' => 'dQw4w9WgXcQ', ...], $field, $context);
     *   // ['id' => 'dQw4w9WgXcQ', 'url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
     *   //  'embed' => 'https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ', 'title' => null, ...]
     */
    public function present(mixed $value, ContentField $field, PresentContext $context): ?array
    {
        $id = is_array($value) ? ($value['video_id'] ?? null) : null;

        if (! is_string($id) || preg_match('/^'.self::ID.'$/', $id) !== 1) {
            return null;
        }

        $screen = $value['screen'] ?? null;

        return [
            'id' => $id,
            'url' => self::watchUrl($id),
            'embed' => 'https://www.youtube-nocookie.com/embed/'.$id,
            'title' => $value['title'] ?? null,
            'description' => $value['description'] ?? null,
            'screen' => is_string($screen) && preg_match(FileValue::PATTERN, $screen) === 1
                ? app(FileStorage::class)->urlForValue($this->uploadProfile(), $screen)
                : null,
        ];
    }

    public function operators(): array
    {
        return ['contains'];
    }

    protected function baseRules(): array
    {
        return [];
    }
}
