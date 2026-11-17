<?php

namespace Webtack\Ecms\Content\FieldTypes;

use Illuminate\Validation\ValidationException;

/**
 * Optional capability of a field type whose values refer to rows of another table that has no
 * foreign key to the value table (for example `term`: the uuid lies in `content_values_string`).
 *
 * `ContentValueWriter` calls it inside the transaction of a write, before the values are stored,
 * so that a concurrent delete of a referenced row waits for the write (or the write sees the row
 * gone). Types that do not implement it are not touched.
 *
 * @example
 *   $type->lockReferences(['0190a1b2-...']); // shared row locks; throws if a row is already gone
 */
interface LocksReferences
{
    /**
     * Lock the rows the prepared values refer to against deletion until the transaction ends.
     *
     * @param  list<mixed>  $prepared  The prepared values of one field (one element or the list of a multiple field).
     *
     * @throws ValidationException When a referenced row no longer exists.
     */
    public function lockReferences(array $prepared): void;
}
