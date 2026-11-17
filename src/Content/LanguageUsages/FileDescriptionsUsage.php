<?php

namespace Webtack\Ecms\Content\LanguageUsages;

use Webtack\Ecms\Content\LanguageUsage;
use Webtack\Ecms\Models\FileTranslation;

/**
 * Translated file descriptions (`media_translations`).
 */
class FileDescriptionsUsage implements LanguageUsage
{
    public function exists(string $lang): bool
    {
        return FileTranslation::query()->where('lang', $lang)->exists();
    }
}
