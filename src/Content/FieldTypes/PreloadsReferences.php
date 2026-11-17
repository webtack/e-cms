<?php

namespace Webtack\Ecms\Content\FieldTypes;

use Webtack\Ecms\Content\PresentContext;
use Webtack\Ecms\Models\ContentField;

/**
 * Optional capability of a field type whose public form needs other rows (terms, nodes, types): it announces
 * every referenced id of a page to the {@see PresentContext} first, so the rows are read in one batch and
 * `present()` is then answered from memory.
 *
 * @example
 *   // TermType: collect the uuids of all the values of the page, then one query for all the names.
 *   $type->preload([$uuidA, $uuidB], $field, $context);
 */
interface PreloadsReferences
{
    /**
     * @param  list<mixed>  $values  Every value of the field on the page, as read by `ContentValueReader` (multiple fields flattened, blanks removed).
     */
    public function preload(array $values, ContentField $field, PresentContext $context): void;
}
