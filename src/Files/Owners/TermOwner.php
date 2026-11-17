<?php

namespace Webtack\Ecms\Files\Owners;

use Illuminate\Support\Facades\DB;

/**
 * Owner type `taxonomy_term`: a file used as the image of a term. The label is the term name in the
 * default language (the slug when it has none). The link leads to the edit form of the term, built from
 * the vocabulary key of the term, so no query per owner is needed.
 *
 * @example
 *   (new TermOwner)->describe([5]);
 *   // [5 => ['label' => 'Jackets', 'url' => '/admin/taxonomy/categories/terms/0190…/edit']]
 */
class TermOwner implements MediaOwner
{
    public function describe(array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        $terms = DB::table('taxonomy_terms')->whereIn('id', $ids)->get(['id', 'uuid', 'vocabulary_key', 'slug']);

        $names = DB::table('taxonomy_term_translations')
            ->whereIn('term_uuid', $terms->pluck('uuid'))
            ->where('lang', config('content.default_lang'))
            ->pluck('name', 'term_uuid');

        $described = [];

        foreach ($terms as $term) {
            $described[$term->id] = [
                'label' => $names[$term->uuid] ?? $term->slug,
                'url' => route('admin.taxonomy.terms.edit', [$term->vocabulary_key, $term->uuid], false),
            ];
        }

        return $described;
    }
}
