<?php

namespace Webtack\Ecms\Files;

/**
 * Short labels of file types for the admin: the extension a profile of config/files.php gives to a MIME type
 * (`docx` instead of `application/vnd.openxmlformats-officedocument.wordprocessingml.document`).
 *
 * A type that no profile lists gets the part of the MIME after `/`. The stored MIME and the upload rules stay
 * untouched: the label is only for display and for the media library filter.
 *
 * @example
 *   FileTypes::label('image/jpeg');   // 'jpg'
 *   FileTypes::label('text/plain');   // 'plain' (no profile lists it)
 *   FileTypes::labels();              // ['image/jpeg' => 'jpg', ..., 'application/pdf' => 'pdf', ...]
 *   FileTypes::options();             // ['jpg', 'png', 'webp', 'gif', 'pdf', 'doc', 'docx', 'xlsx']
 *   FileTypes::mimesFor('jpg');       // ['image/jpeg']
 */
final class FileTypes
{
    /**
     * The label of a MIME type.
     */
    public static function label(string $mime): string
    {
        return self::labels()[$mime] ?? self::fallback($mime);
    }

    /**
     * Every MIME type of every profile with its label (`mime => extension`).
     *
     * @return array<string, string>
     */
    public static function labels(): array
    {
        $labels = [];

        foreach (config('files.profiles', []) as $profile) {
            foreach ($profile['mimes'] as $mime => $extension) {
                $labels[$mime] ??= $extension;
            }
        }

        return $labels;
    }

    /**
     * The distinct labels in profile order, for a type filter.
     *
     * @return list<string>
     */
    public static function options(): array
    {
        return array_values(array_unique(self::labels()));
    }

    /**
     * The MIME types that carry a label; empty for a label no profile uses.
     *
     * @return list<string>
     */
    public static function mimesFor(string $label): array
    {
        return array_keys(array_filter(self::labels(), fn (string $known) => $known === $label));
    }

    private static function fallback(string $mime): string
    {
        $position = strpos($mime, '/');

        return $position === false ? $mime : substr($mime, $position + 1);
    }
}
