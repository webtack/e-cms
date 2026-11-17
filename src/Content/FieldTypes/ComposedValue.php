<?php

namespace Webtack\Ecms\Content\FieldTypes;

/**
 * Optional capability of a field type whose value is more than one scalar: a scalar kept in the
 * `value` column plus extra data kept as JSON in the `options` column of the same row.
 *
 * The composed value is an ASSOCIATIVE array, so `ContentValueWriter` tells one composed value
 * from the list of a multiple field with `array_is_list`. Types that do not implement it are
 * stored and read as before, with `options` left `NULL`.
 *
 * @example
 *   $type->split(['url' => 'https://youtu.be/dQw4w9WgXcQ', 'title' => 'Clip']);
 *   // ['https://youtu.be/dQw4w9WgXcQ', ['video_id' => 'dQw4w9WgXcQ', 'title' => 'Clip']]
 *   $type->compose('https://youtu.be/dQw4w9WgXcQ', ['video_id' => 'dQw4w9WgXcQ', 'title' => 'Clip']);
 *   // ['url' => 'https://youtu.be/dQw4w9WgXcQ', 'video_id' => 'dQw4w9WgXcQ', 'title' => 'Clip', ...]
 */
interface ComposedValue
{
    /**
     * Split ONE validated input value into what is written to the database.
     *
     * @return array{0: mixed, 1: array<string, mixed>|null} The scalar for `value` and the data for `options`.
     */
    public function split(mixed $input): array;

    /**
     * Build the composed value from a stored row.
     *
     * @param  array<string, mixed>|null  $options  The decoded `options` column.
     */
    public function compose(mixed $scalar, ?array $options): mixed;
}
