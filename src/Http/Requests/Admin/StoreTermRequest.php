<?php

namespace Webtack\Ecms\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Webtack\Ecms\Models\Term;
use Webtack\Ecms\Models\Vocabulary;

/**
 * Validates the create form of a term of the vocabulary from the `vocabulary` route parameter.
 *
 * Fields: `translations.default` (the base name is required; other languages are never sent by the base
 * form, see the translation mode), `slug` (optional, generated when empty), `parent_uuid` (a
 * term of the same vocabulary), `weight` (optional) and `image` (`{uuid}.{ext}` of an image file, checked
 * by `TermWriter`). The depth limit and cycles are guarded by the model, which reports them under `parent_uuid`.
 * `UpdateTermRequest` reuses {@see self::sharedRules()}.
 *
 * @example
 *   // POST /admin/taxonomy/categories/terms
 *   //   translations[default][name]=Clothes&parent_uuid=&slug=&weight=&image=
 *   app(TermWriter::class)->create($vocabulary, $request->validated());
 */
class StoreTermRequest extends FormRequest
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
        return self::sharedRules($this->route('vocabulary'));
    }

    /**
     * Rules of the fields both forms edit.
     *
     * @return array<string, array<int, mixed>>
     *
     * @example
     *   self::sharedRules($vocabulary, $term)['slug']; // unique in the vocabulary, ignoring $term
     */
    public static function sharedRules(Vocabulary $vocabulary, ?Term $term = null): array
    {
        $rules = [
            'slug' => [
                'nullable', 'string', 'max:190', 'regex:'.Term::SLUG_PATTERN,
                Rule::unique('taxonomy_terms', 'slug')->where('vocabulary_key', $vocabulary->key)->ignore($term?->getKey()),
            ],
            'parent_uuid' => ['nullable', 'string', Rule::exists('taxonomy_terms', 'uuid')->where('vocabulary_key', $vocabulary->key)],
            'weight' => ['nullable', 'integer', 'min:0', 'max:2147483647'],
            'image' => ['nullable', 'string', 'max:255'],
            'translations' => ['required', 'array'],
            'translations.default.name' => ['required', 'string', 'max:255'],
            'translations.default.description' => ['nullable', 'string', 'max:65000'],
        ];

        return $rules;
    }
}
