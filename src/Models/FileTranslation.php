<?php

namespace Webtack\Ecms\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Webtack\Ecms\Files\FileDescriptions;

/**
 * The display name and the description of a file in one additional language (`media_translations`); a row holds at
 * least one of them. The original name of the file never changes.
 * Written through {@see FileDescriptions}, which checks the language and the equal-to-base rule.
 *
 * @property int $id
 * @property string $file_uuid
 * @property string $lang
 * @property string|null $name
 * @property string|null $description
 *
 * @example
 *   $file->translations()->where('lang', 'en')->value('description');
 */
#[Fillable(['file_uuid', 'lang', 'name', 'description'])]
class FileTranslation extends Model
{
    protected $table = 'media_translations';

    /** @return BelongsTo<File, $this> */
    public function file(): BelongsTo
    {
        return $this->belongsTo(File::class, 'file_uuid', 'uuid');
    }
}
