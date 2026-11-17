<?php

namespace Webtack\Ecms\Http\Requests\Admin;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Webtack\Ecms\Access\AccessResolver;
use Webtack\Ecms\Content\FieldTypeRegistry;
use Webtack\Ecms\Content\FieldTypes\AbstractFileFieldType;
use Webtack\Ecms\Content\FieldTypes\HasUploadProfile;
use Webtack\Ecms\Files\FileProfiles;
use Webtack\Ecms\Models\ContentField;

/**
 * Validates a file upload: the file itself and where its rules come from, either the key of an
 * upload profile or a content field (`content_type` + `field_key`), never both.
 *
 * Type, size and safety are checked by `FileStorage::store()` (the rules come from config and the
 * field settings, never from the request), so only presence, the profile key and the existence
 * and type of the field are validated here.
 *
 * @example
 *   // POST /admin/files  (multipart)  file=<binary>  profile=image
 *   // POST /admin/files  (multipart)  file=<binary>  content_type=article  field_key=cover
 *   $request->validated('profile'); // 'image'
 *   $request->field();              // ContentField|null
 */
class StoreFileRequest extends FormRequest
{
    private ?ContentField $field = null;

    public function authorize(): bool
    {
        return app(AccessResolver::class)->canEditAnyContent($this->user());
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'file' => ['required', 'file'],
            'profile' => ['nullable', 'required_without:field_key', 'prohibits:field_key', 'string', Rule::in(app(FileProfiles::class)->keys())],
            'content_type' => ['nullable', 'required_with:field_key', 'string'],
            'field_key' => ['nullable', 'required_without:profile', 'prohibits:profile', 'string', 'required_with:content_type'],
        ];
    }

    /**
     * After the shape is valid, the field must exist and hold a file (`image` or `file`) or be a `richtext` (its images).
     */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty() || ! $this->filled('field_key')) {
                return;
            }

            $field = ContentField::query()
                ->where('content_type', $this->input('content_type'))
                ->where('key', $this->input('field_key'))
                ->first();

            $type = $field === null ? null : app(FieldTypeRegistry::class)->get($field->type);

            if (! $type instanceof AbstractFileFieldType && ! $type instanceof HasUploadProfile) {
                $validator->errors()->add('field_key', __('The selected field does not hold a file.'));

                return;
            }

            $this->field = $field;
        }];
    }

    /**
     * The target field of the upload, or `null` when a profile was given.
     */
    public function field(): ?ContentField
    {
        return $this->field;
    }
}
