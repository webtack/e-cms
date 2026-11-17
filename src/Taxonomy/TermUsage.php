<?php

namespace Webtack\Ecms\Taxonomy;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Webtack\Ecms\Models\Term;
use Webtack\Ecms\Models\Vocabulary;

/**
 * Where a term is used: the values of `term` fields that hold its uuid.
 *
 * `content_values_string` has no foreign key to the terms (the table is shared with string types),
 * so this query is the only source of truth for "is the term used".
 *
 * @example
 *   app(TermUsage::class)->forTerm($term);
 *   // collect([['owner' => 'node', 'content_type' => 'article', 'field_key' => 'tags', 'count' => 2]])
 */
class TermUsage
{
    /**
     * @return Collection<int, array{owner: string, content_type: string, field_key: string, count: int}>
     */
    public function forTerm(Term $term): Collection
    {
        return DB::table('content_values_string as v')
            ->join('content_fields as f', function ($join) {
                $join->on('f.content_type', '=', 'v.content_type')->on('f.key', '=', 'v.field_key');
            })
            ->where('f.type', 'term')
            ->where('f.settings->vocabulary', $term->vocabulary_key)
            ->where('v.value', $term->uuid)
            ->groupBy('v.entity', 'v.content_type', 'v.field_key')
            ->orderBy('v.content_type')->orderBy('v.field_key')
            ->get(['v.entity', 'v.content_type', 'v.field_key', DB::raw('count(*) as total')])
            ->map(fn (object $row) => [
                'owner' => $row->entity,
                'content_type' => $row->content_type,
                'field_key' => $row->field_key,
                'count' => (int) $row->total,
            ])->values();
    }

    /**
     * Where the terms of a vocabulary are used, for all of them in one grouped query: place lists by term uuid.
     * Terms that are not used are absent. Each place has the shape of {@see self::forTerm()}.
     *
     * @return array<string, list<array{owner: string, content_type: string, field_key: string, count: int}>>
     *
     * @example
     *   app(TermUsage::class)->forVocabulary($vocabulary);
     *   // ['0190...' => [['owner' => 'node', 'content_type' => 'article', 'field_key' => 'tags', 'count' => 2]]]
     */
    public function forVocabulary(Vocabulary $vocabulary): array
    {
        $usage = [];

        $rows = DB::table('content_values_string as v')
            ->join('content_fields as f', function ($join) {
                $join->on('f.content_type', '=', 'v.content_type')->on('f.key', '=', 'v.field_key');
            })
            ->join('taxonomy_terms as t', 't.uuid', '=', 'v.value')
            ->where('t.vocabulary_key', $vocabulary->key)
            ->where('f.type', 'term')
            ->where('f.settings->vocabulary', $vocabulary->key)
            ->groupBy('v.value', 'v.entity', 'v.content_type', 'v.field_key')
            ->orderBy('v.content_type')->orderBy('v.field_key')
            ->get(['v.value', 'v.entity', 'v.content_type', 'v.field_key', DB::raw('count(*) as total')]);

        foreach ($rows as $row) {
            $usage[$row->value][] = [
                'owner' => $row->entity,
                'content_type' => $row->content_type,
                'field_key' => $row->field_key,
                'count' => (int) $row->total,
            ];
        }

        return $usage;
    }
}
