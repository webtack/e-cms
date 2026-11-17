<?php

namespace Webtack\Ecms\Content\LanguageUsages;

use Illuminate\Support\Facades\DB;
use Webtack\Ecms\Content\FieldTypeRegistry;
use Webtack\Ecms\Content\LanguageUsage;

/**
 * Content values: any row with the language in a `content_values_*` table.
 */
class ContentValuesUsage implements LanguageUsage
{
    public function __construct(private readonly FieldTypeRegistry $registry) {}

    public function exists(string $lang): bool
    {
        foreach ($this->registry->tables() as $table) {
            if (DB::table($table)->where('lang', $lang)->exists()) {
                return true;
            }
        }

        return false;
    }
}
