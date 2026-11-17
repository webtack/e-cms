<?php

namespace Webtack\Ecms\Content\LanguageUsages;

use Webtack\Ecms\Content\LanguageUsage;
use Webtack\Ecms\Models\ContentField;

/**
 * Translated field labels (`content_fields.settings` → `labels` → language code).
 *
 * The code is a quoted JSON path segment, so a code with a hyphen (`pt-BR`) is matched as one key.
 */
class FieldLabelsUsage implements LanguageUsage
{
    public function exists(string $lang): bool
    {
        return ContentField::query()->whereJsonContainsKey("settings->labels->{$lang}")->exists();
    }
}
