<?php

namespace Webtack\Ecms\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;
use Webtack\Ecms\Models\Vocabulary;
use Webtack\Ecms\Taxonomy\TermTree;

/**
 * Validates the edit form of a vocabulary (the vocabulary comes from the `vocabulary` route parameter).
 *
 * `key` has no rule, so it never reaches `validated()` and stays unchanged. A maximum depth below the
 * height of the existing term tree is reported under `max_depth`.
 *
 * @example
 *   // PUT /admin/taxonomy/categories  name=Categories&description=&max_depth=2
 */
class UpdateVocabularyRequest extends FormRequest
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
        return StoreVocabularyRequest::sharedRules();
    }

    /**
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [function (Validator $validator) {
            $depth = $this->input('max_depth');

            if ($validator->errors()->isNotEmpty() || $depth === null || $depth === '') {
                return;
            }

            /** @var Vocabulary $vocabulary */
            $vocabulary = $this->route('vocabulary');

            if (app(TermTree::class)->vocabularyHeight($vocabulary) > (int) $depth) {
                $validator->errors()->add('max_depth', __('The maximum depth is lower than the depth of the existing terms.'));
            }
        }];
    }
}
