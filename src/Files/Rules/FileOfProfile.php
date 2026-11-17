<?php

namespace Webtack\Ecms\Files\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Translation\PotentiallyTranslatedString;
use Webtack\Ecms\Models\File;

/**
 * Validation rule for a consumer's file field: the value must be the `uuid` (32 chars `0-9a-f`) of an existing file
 * that was uploaded under the expected profile.
 *
 * The upload endpoint accepts any profile, so this rule is what keeps, say, a document out of an
 * avatar field.
 *
 * @example
 *   $request->validate([
 *       'avatar' => ['nullable', new FileOfProfile('image')],
 *   ]);
 */
class FileOfProfile implements ValidationRule
{
    public function __construct(private readonly string $profile) {}

    /**
     * @param  Closure(string, ?string=): PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $file = is_string($value) && preg_match('/^[0-9a-f]{32}$/', $value) === 1 ? File::findByUuid($value) : null;

        if ($file === null) {
            $fail('The :attribute must be an existing file.')->translate();

            return;
        }

        if ($file->profile !== $this->profile) {
            $fail('The :attribute must be a file of the :profile profile.')->translate(['profile' => $this->profile]);
        }
    }
}
