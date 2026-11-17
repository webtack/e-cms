<?php

namespace Webtack\Ecms\Files;

use InvalidArgumentException;
use Webtack\Ecms\Content\FieldTypeRegistry;
use Webtack\Ecms\Content\FieldTypes\AbstractFileFieldType;
use Webtack\Ecms\Content\FieldTypes\HasUploadProfile;
use Webtack\Ecms\Models\ContentField;

/**
 * The effective rules of an upload: the allowed types, the size limit and, for images, the pixel limits.
 *
 * The profile of config/files.php is the ceiling. A field of type `image` or `file` (and `richtext`, for its images) may only narrow
 * it with its settings (`mimes`, `max_size`, `max_width`, `max_height`); whatever its settings hold, the result is never wider
 * than the profile, and executable files and SVG are never allowed. For each pixel limit the smaller of the
 * profile's and the field's value applies; a profile without the limit takes the field's one, and a side
 * that neither limits stays unrestricted (null).
 *
 * @example
 *   UploadRules::forProfile('image');   // all types of the profile, its limit
 *   UploadRules::forField($field);      // field settings `mimes: [image/png]`, `max_size: 1024`
 *   $rules->mimes;                      // ['image/png']
 *   $rules->maxSize;                    // 1024 (KB)
 *   $rules->maxWidth;                   // min(profile max_width, field max_width), null if neither is set
 *   $rules->allows('image/jpeg');       // false
 */
final class UploadRules
{
    /**
     * @param  list<string>  $mimes  Allowed detected types, all of them present in the profile.
     * @param  int  $maxSize  Kilobytes, not above the profile's limit.
     * @param  int|null  $maxWidth  Pixels, not above the profile's limit; null means unrestricted.
     * @param  int|null  $maxHeight  Pixels, not above the profile's limit; null means unrestricted.
     */
    public function __construct(
        public readonly string $profile,
        public readonly array $mimes,
        public readonly int $maxSize,
        public readonly ?int $maxWidth = null,
        public readonly ?int $maxHeight = null,
    ) {}

    /**
     * Everything the profile allows.
     *
     * @throws InvalidArgumentException When the profile is not configured.
     */
    public static function forProfile(string $profile): self
    {
        return self::narrowed($profile, null, null, null, null);
    }

    /**
     * The profile of the field's type narrowed by the field's settings. A `richtext` field has no file
     * settings, so it gets the whole `image` profile.
     *
     * @throws InvalidArgumentException When the field is not of a file-bearing type.
     */
    public static function forField(ContentField $field): self
    {
        $type = app(FieldTypeRegistry::class)->get($field->type);

        if ($type instanceof HasUploadProfile) {
            return self::forProfile($type->uploadProfile());
        }

        if (! $type instanceof AbstractFileFieldType) {
            throw new InvalidArgumentException("Field type [{$field->type}] does not hold files.");
        }

        $settings = $field->settings ?? [];

        return self::narrowed(
            $type->profile(),
            $settings['mimes'] ?? null,
            $settings['max_size'] ?? null,
            $settings['max_width'] ?? null,
            $settings['max_height'] ?? null,
        );
    }

    /**
     * Whether a detected type is allowed.
     */
    public function allows(string $mime): bool
    {
        return in_array($mime, $this->mimes, true);
    }

    /**
     * Intersect the profile with the requested types and take the smaller size and pixel limits. Anything
     * malformed (not a list, not a positive number) is ignored, which leaves the profile's value.
     */
    private static function narrowed(string $profile, mixed $mimes, mixed $maxSize, mixed $maxWidth, mixed $maxHeight): self
    {
        $settings = app(FileProfiles::class)->get($profile);

        $allowed = array_values(array_diff(array_keys($settings['mimes']), FileStorage::FORBIDDEN_MIMES));

        if (is_array($mimes) && $mimes !== []) {
            $allowed = array_values(array_intersect($allowed, array_filter($mimes, 'is_string')));
        }

        $limit = $settings['max_size'];

        if (is_numeric($maxSize) && (int) $maxSize > 0) {
            $limit = min($limit, (int) $maxSize);
        }

        return new self(
            $profile,
            $allowed,
            $limit,
            self::smaller(self::positive($settings['max_width'] ?? null), self::positive($maxWidth)),
            self::smaller(self::positive($settings['max_height'] ?? null), self::positive($maxHeight)),
        );
    }

    /**
     * A positive integer from a config or settings value (an `.env` string included), otherwise null.
     */
    private static function positive(mixed $value): ?int
    {
        return is_numeric($value) && (int) $value > 0 ? (int) $value : null;
    }

    /**
     * The smaller of two optional limits; a missing one does not count.
     */
    private static function smaller(?int $a, ?int $b): ?int
    {
        return $a === null || $b === null ? ($a ?? $b) : min($a, $b);
    }
}
