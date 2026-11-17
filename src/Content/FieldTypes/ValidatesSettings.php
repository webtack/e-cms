<?php

namespace Webtack\Ecms\Content\FieldTypes;

/**
 * Optional capability of a field type that has settings which must be checked when a field is saved
 * (`ContentField` calls it for a new field and whenever `settings` changes).
 *
 * @example
 *   $type->settingsErrors(['vocabulary' => 'nope'], null, false); // ['vocabulary' => 'The vocabulary does not exist.']
 */
interface ValidatesSettings
{
    /**
     * Errors of the settings, keyed by the setting name (the caller prefixes `settings.`).
     *
     * @param  array<string, mixed>  $settings  The settings being saved.
     * @param  array<string, mixed>|null  $original  The stored settings of an existing field; `null` for a new one.
     * @param  bool  $hasValues  Whether an existing field already has stored values.
     * @return array<string, string>
     */
    public function settingsErrors(array $settings, ?array $original, bool $hasValues): array;
}
