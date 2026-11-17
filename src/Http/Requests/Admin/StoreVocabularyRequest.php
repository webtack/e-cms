<?php

namespace Webtack\Ecms\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Webtack\Ecms\Models\Vocabulary;

/**
 * Validates the create form of a vocabulary: key, name, description and maximum depth.
 *
 * The rules duplicate the model's on purpose: the form gets errors per field, the model stays the last
 * barrier for other write paths. `UpdateVocabularyRequest` reuses {@see self::sharedRules()}.
 * An empty `max_depth` means "no limit" and is validated as absent.
 *
 * @example
 *   // POST /admin/taxonomy  key=categories&name=Categories&description=&max_depth=3
 */
class StoreVocabularyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            ...self::sharedRules(),
            'key' => ['required', 'string', 'max:64', 'regex:'.Vocabulary::KEY_PATTERN, Rule::unique('taxonomy_vocabularies', 'key')],
        ];
    }

    /**
     * Rules of the fields both forms edit.
     *
     * @return array<string, array<int, mixed>>
     */
    public static function sharedRules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'max_depth' => ['nullable', 'integer', 'min:1', 'max:1000'],
        ];
    }

    /**
     * The model attributes for the validated input: `max_depth` moves into `settings`.
     *
     * @param  array<string, mixed>  $validated
     * @param  array<string, mixed>  $settings  Existing settings to keep.
     * @return array<string, mixed>
     *
     * @example
     *   self::modelAttributes(['name' => 'Tags', 'max_depth' => '2']); // ['name' => 'Tags', 'settings' => ['max_depth' => 2]]
     */
    public static function modelAttributes(array $validated, array $settings = []): array
    {
        $depth = $validated['max_depth'] ?? null;
        unset($validated['max_depth'], $settings['max_depth']);

        if ($depth !== null && $depth !== '') {
            $settings['max_depth'] = (int) $depth;
        }

        return [...$validated, 'settings' => $settings === [] ? null : $settings];
    }
}
