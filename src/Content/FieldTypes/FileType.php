<?php

namespace Webtack\Ecms\Content\FieldTypes;

/**
 * A document. Profile `document`. Settings: `mimes` (a subset of the profile's types) and `max_size` (KB, at most the profile's).
 */
class FileType extends AbstractFileFieldType
{
    public function key(): string
    {
        return 'file';
    }

    public function profile(): string
    {
        return 'document';
    }
}
