<?php

namespace Webtack\Ecms\Content\LanguageUsages;

use Webtack\Ecms\Content\LanguageUsage;
use Webtack\Ecms\Models\MenuItemTranslation;

/**
 * Menu item translations (`menu_item_translations`).
 */
class MenuItemTranslationsUsage implements LanguageUsage
{
    public function exists(string $lang): bool
    {
        return MenuItemTranslation::query()->where('lang', $lang)->exists();
    }
}
