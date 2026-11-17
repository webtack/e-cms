<?php

namespace Webtack\Ecms\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A type a user put into the personal "Favorites" list of the sidebar.
 *
 * @property int $id
 * @property int $user_id
 * @property string $type_key
 * @property Carbon|null $created_at
 */
#[Fillable(['user_id', 'type_key'])]
class UserFavoriteType extends Model
{
    const UPDATED_AT = null;

    /** @return BelongsTo<ContentType, $this> */
    public function type(): BelongsTo
    {
        return $this->belongsTo(ContentType::class, 'type_key', 'key');
    }
}
