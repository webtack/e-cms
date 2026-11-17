<?php

namespace Webtack\Ecms\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A row of the admin menu: a link to a type (`type_key` set), a link to a node (`node_uuid` set) or a group
 * (neither set, `title` set). Root rows have no parent.
 *
 * @property int $id
 * @property int|null $parent_id
 * @property string|null $type_key
 * @property string|null $node_uuid UUID of the linked node; the label is the node title, read at display time.
 * @property array<string, string>|null $title Translatable group name, `{"default": "...", "uk": "..."}`.
 * @property int $position
 *
 * @example
 *   AdminMenuEntry::create(['title' => ['default' => 'Catalog'], 'position' => 0]); // a group
 *   AdminMenuEntry::create(['type_key' => 'news', 'parent_id' => $group->id]);      // a link inside it
 *   AdminMenuEntry::create(['node_uuid' => $node->uuid]);                           // a link to a node
 */
#[Fillable(['parent_id', 'type_key', 'node_uuid', 'title', 'position'])]
class AdminMenuEntry extends Model
{
    /** Language key of the base title. */
    public const LANG = 'default';

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['title' => 'array'];
    }

    /** A group has neither a type nor a node. */
    public function isGroup(): bool
    {
        return $this->type_key === null && $this->node_uuid === null;
    }

    public function isTypeLink(): bool
    {
        return $this->type_key !== null;
    }

    public function isNodeLink(): bool
    {
        return $this->node_uuid !== null;
    }

    /** The group title in a language, falling back to the base language. */
    public function titleFor(?string $lang = null): string
    {
        $title = $this->title ?? [];

        return $title[$lang ?? app()->getLocale()] ?? $title[self::LANG] ?? '';
    }

    /** @return BelongsTo<AdminMenuEntry, $this> */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    /** @return HasMany<AdminMenuEntry, $this> */
    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('position')->orderBy('id');
    }
}
