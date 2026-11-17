<?php

namespace Webtack\Ecms\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;
use Webtack\Ecms\Contracts\EcmsUser;
use Webtack\Ecms\Database\Factories\FileFactory;
use Webtack\Ecms\Support\UserModel;

/**
 * A stored file: one uploaded file with its own identity and metadata.
 *
 * Stored in the `media` table. Consumers refer to a file by `uuid` and never touch the disk;
 * writing and deleting go through `Webtack\Ecms\Files\FileStorage`. `disk` and `path` are
 * recorded per row, so changing a profile's disk in config does not affect existing files.
 *
 * @property int $id
 * @property string $uuid Stable external identifier (UUID v7 without dashes, 32 chars), generated on creation.
 * @property int|null $user_id The user who uploaded the file.
 * @property string $profile Upload profile key from config/files.php.
 * @property string $disk
 * @property string $path Path on the disk, `{uuid}.{ext}`, in the root of the disk.
 * @property string $original_name Client-side file name, metadata only; editable in the node form.
 * @property string|null $description Free-text description, edited in the node form; `null` when absent.
 * @property string $mime Type detected from the content.
 * @property int $size Bytes.
 * @property string $hash sha256 of the content (not unique).
 * @property int|null $width Pixels, images only.
 * @property int|null $height Pixels, images only.
 *
 * @example
 *   $file = File::findByUuid('0190a1b27c3e7f0a8b1c2d3e4f5a6b7c'); // File|null
 *   app(FileStorage::class)->url($file);      // '/storage/0190a1b27c3e7f0a8b1c2d3e4f5a6b7c.png'
 */
#[UseFactory(FileFactory::class)]
#[Fillable(['uuid', 'user_id', 'profile', 'disk', 'path', 'original_name', 'description', 'mime', 'size', 'hash', 'width', 'height'])]
class File extends Model
{
    /** @use HasFactory<FileFactory> */
    use HasFactory;

    protected $table = 'media';

    protected static function booted(): void
    {
        static::creating(function (self $file) {
            if (empty($file->uuid)) {
                $file->uuid = static::newUuid();
            }
        });
    }

    /**
     * A new identifier: UUID v7 without dashes (32 chars `0-9a-f`).
     *
     * @example File::newUuid() // '0190a1b27c3e7f0a8b1c2d3e4f5a6b7c'
     */
    public static function newUuid(): string
    {
        return str_replace('-', '', (string) Str::uuid7());
    }

    /**
     * Find a file by its stable uuid.
     *
     * @example File::findByUuid($uuid) // File|null
     */
    public static function findByUuid(string $uuid): ?self
    {
        return static::query()->where('uuid', $uuid)->first();
    }

    /**
     * The user who uploaded the file.
     *
     * @return BelongsTo<EcmsUser, $this>
     */
    public function uploader(): BelongsTo
    {
        return $this->belongsTo(UserModel::class(), 'user_id');
    }

    /**
     * Where the file is used (rows of `media_relations`, joined by `uuid`).
     *
     * @return HasMany<MediaRelation, $this>
     */
    public function relations(): HasMany
    {
        return $this->hasMany(MediaRelation::class, 'file_uuid', 'uuid');
    }

    /**
     * Translated descriptions, one row per additional language.
     *
     * @return HasMany<FileTranslation, $this>
     */
    public function translations(): HasMany
    {
        return $this->hasMany(FileTranslation::class, 'file_uuid', 'uuid');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'size' => 'integer',
            'width' => 'integer',
            'height' => 'integer',
        ];
    }
}
