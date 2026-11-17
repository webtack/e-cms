<?php

namespace Webtack\Ecms\Content\FieldTypes;

/**
 * Optional capability of a field type that accepts uploaded files but has no file settings of its own
 * (`mimes`, `max_size`): the upload profile alone is the ceiling for what the field takes.
 *
 * File field types (`AbstractFileFieldType`) name their profile with `profile()` and narrow it with
 * settings; a type with this interface is uploaded to with the profile defaults.
 *
 * @example
 *   $type->uploadProfile(); // 'image'
 */
interface HasUploadProfile
{
    /** The upload profile of the files of this field (a key of config/files.php `profiles`). */
    public function uploadProfile(): string;
}
