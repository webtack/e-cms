<?php

namespace Webtack\Ecms\Content\FieldTypes;

/**
 * Optional capability of a field type whose filter value stands for more than itself (a term stands
 * for the term and all its descendants). `ContentNodeQuery` expands the validated values and turns
 * `eq` into `in` over the expanded set.
 *
 * @example
 *   $type->expandFilterValues([$clothes]); // [$clothes, $jackets, $winter]
 */
interface ExpandsFilterValues
{
    /**
     * @param  list<mixed>  $prepared  The prepared filter values.
     * @return list<mixed> The values to match, without duplicates.
     */
    public function expandFilterValues(array $prepared): array;
}
