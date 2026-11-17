<?php

namespace Webtack\Ecms\Content\LanguageUsages;

use Webtack\Ecms\Content\LanguageUsage;
use Webtack\Ecms\Settings\SiteSettings;

/**
 * Translated site settings (`site.*` of {@see SiteSettings}: texts, schedule and list labels; the logos are base-only).
 */
class SiteSettingsUsage implements LanguageUsage
{
    public function __construct(private readonly SiteSettings $site) {}

    public function exists(string $lang): bool
    {
        return $this->site->hasTranslation($lang);
    }
}
