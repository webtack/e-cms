<?php

namespace Webtack\Ecms\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Webtack\Ecms\Models\Term;
use Webtack\Ecms\Taxonomy\TermTree;

/**
 * Validates the edit form of a term (the vocabulary and the term come from the route parameters, the term is
 * already bound within the vocabulary).
 *
 * The vocabulary and `uuid` of a term never change: they have no rules. The parent can be neither the term nor
 * any of its descendants (the model also guards this and the depth limit).
 *
 * @example
 *   // PUT /admin/taxonomy/categories/terms/{uuid}  translations[default][name]=Outerwear&parent_uuid=
 */
class UpdateTermRequest extends FormRequest
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
        /** @var Term $term */
        $term = $this->route('term');
        $rules = StoreTermRequest::sharedRules($this->route('vocabulary'), $term);
        $rules['parent_uuid'][] = Rule::notIn(app(TermTree::class)->descendantUuids([$term->uuid]));

        return $rules;
    }
}
