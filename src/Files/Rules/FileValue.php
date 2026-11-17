<?php

namespace Webtack\Ecms\Files\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Number;
use Illuminate\Translation\PotentiallyTranslatedString;
use Webtack\Ecms\Files\UploadRules;
use Webtack\Ecms\Models\ContentField;
use Webtack\Ecms\Models\File;

/**
 * Validation rule for the value of an `image` or `file` field: `{uuid}.{ext}` of an existing file.
 *
 * It checks the shape (so `../secret.png` never gets further), that the file exists, that its
 * profile is the type's profile, that the extension is the stored one and, when the field is
 * given, that the file's type and size are within the field's settings. This is the authoritative
 * check: the upload endpoint checks the same limits only to refuse early.
 *
 * @example
 *   ['string', new FileValue('image', $field)]
 *   ['string', new FileValue('image')]   // shape only (filters)
 */
class FileValue implements ValidationRule
{
    /** `{uuid}.{ext}`; group 1 is the uuid, group 2 the extension. */
    public const PATTERN = '/^([0-9a-f]{32})\.([a-z0-9]{1,8})$/';

    public function __construct(private readonly string $profile, private readonly ?ContentField $field = null) {}

    /**
     * @param  Closure(string, ?string=): PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || preg_match(self::PATTERN, $value, $match) !== 1) {
            $fail('The :attribute must be a stored file.')->translate();

            return;
        }

        if ($this->field === null) {
            return;
        }

        $file = File::findByUuid($match[1]);

        if ($file === null) {
            $fail('The :attribute must be an existing file.')->translate();

            return;
        }

        if ($file->profile !== $this->profile) {
            $fail('The :attribute must be a file of the :profile profile.')->translate(['profile' => $this->profile]);

            return;
        }

        if (pathinfo($file->path, PATHINFO_EXTENSION) !== $match[2]) {
            $fail('The :attribute does not match the stored file.')->translate();

            return;
        }

        $rules = UploadRules::forField($this->field);

        if (! $rules->allows($file->mime)) {
            $fail('The :attribute must be a file of an allowed type.')->translate();
        } elseif ($file->size > $rules->maxSize * 1024) {
            $fail('The :attribute must not be larger than :max.')->translate(['max' => Number::fileSize($rules->maxSize * 1024)]);
        }
    }
}
