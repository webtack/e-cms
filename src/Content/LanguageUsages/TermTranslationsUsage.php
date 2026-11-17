<?php

namespace Webtack\Ecms\Content\LanguageUsages;

use Webtack\Ecms\Content\LanguageUsage;
use Webtack\Ecms\Models\TermTranslation;

/**
 * Term translations (`taxonomy_term_translations`).
 */
class TermTranslationsUsage implements LanguageUsage
{
    public function exists(string $lang): bool
    {
        return TermTranslation::query()->where('lang', $lang)->exists();
    }
}
