<?php

namespace Webtack\Ecms\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Webtack\Ecms\Database\Factories\MenuItemTranslationFactory;
use Webtack\Ecms\Menus\MenuItemWriter;

/**
 * The title and public description of a menu item in one language (`default` or a content language).
 * Written through {@see MenuItemWriter}, which checks the language.
 *
 * @property int $id
 * @property string $item_uuid
 * @property string $lang
 * @property string|null $title
 * @property string|null $description
 *
 * @example
 *   $item->translations()->where('lang', 'en')->first()?->title;
 */
#[UseFactory(MenuItemTranslationFactory::class)]
#[Fillable(['item_uuid', 'lang', 'title', 'description'])]
class MenuItemTranslation extends Model
{
    /** @use HasFactory<MenuItemTranslationFactory> */
    use HasFactory;

    /** @return BelongsTo<MenuItem, $this> */
    public function item(): BelongsTo
    {
        return $this->belongsTo(MenuItem::class, 'item_uuid', 'uuid');
    }
}
