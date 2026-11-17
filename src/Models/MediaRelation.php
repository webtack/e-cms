<?php

namespace Webtack\Ecms\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * "A file is used by an owner in a role": one row per (file, owner, role, language).
 *
 * Rows are written only through `Webtack\Ecms\Files\FileRelations`. The foreign key to `media.uuid` is
 * `RESTRICT`, so a file with a relation cannot be deleted.
 *
 * @property int $id
 * @property string $file_uuid
 * @property string $owner_type Key of the owner type, e.g. `content_node`.
 * @property int $owner_id
 * @property string $role The field key for content.
 * @property string $lang `default` or a language code.
 *
 * @example
 *   $file->relations; // Collection<MediaRelation>
 */
#[Fillable(['file_uuid', 'owner_type', 'owner_id', 'role', 'lang'])]
class MediaRelation extends Model
{
    /**
     * @return BelongsTo<File, $this>
     */
    public function file(): BelongsTo
    {
        return $this->belongsTo(File::class, 'file_uuid', 'uuid');
    }
}
