<?php

namespace Webtack\Ecms\Content\FieldTypes;

/**
 * An image. Profile `image`. Settings: `mimes` (a subset of the profile's types) and `max_size` (KB, at most the profile's).
 */
class ImageType extends AbstractFileFieldType
{
    public function key(): string
    {
        return 'image';
    }

    public function profile(): string
    {
        return 'image';
    }
}
