<?php

namespace Webtack\Ecms\Content\FieldTypes;

/**
 * Optional capability of a field type whose values refer to stored files.
 *
 * `ContentValueWriter` calls it after a value is written and keeps `media_relations` in step with
 * the answer (see `Webtack\Ecms\Files\FileRelations`). Types that do not implement it are not touched.
 *
 * @example
 *   $type->fileUuids('0190a1b27c3e7f0a8b1c2d3e4f5a6b7c.png'); // ['0190a1b27c3e7f0a8b1c2d3e4f5a6b7c']
 *   $type->fileUuids(['<uuid>.png', '<uuid2>.pdf']);          // ['<uuid>', '<uuid2>']
 *   $type->fileUuids(null);                                   // []
 */
interface TracksFiles
{
    /**
     * The `media.uuid` of every file in a value of this type (one element or the list of a multiple field).
     *
     * @return list<string>
     */
    public function fileUuids(mixed $storedValue): array;
}
