<?php

namespace Webtack\Ecms\Taxonomy;

use Illuminate\Support\Facades\DB;
use Webtack\Ecms\Models\Term;
use Webtack\Ecms\Models\Vocabulary;

/**
 * Reads the term tree: descendants of terms, the height of a subtree and the tree of a vocabulary
 * for display. Levels are walked breadth first, one query per level; the number of levels is
 * bounded by the vocabulary's maximum depth, and a visited set protects against damaged data.
 *
 * @example
 *   $tree = app(TermTree::class);
 *   $tree->descendantUuids([$clothes->uuid]); // [$clothes->uuid, $jackets->uuid, $winter->uuid]
 *   $tree->height($clothes);                  // 3 (itself plus two levels below)
 *   $tree->tree($vocabulary, 'default');      // [['uuid' => ..., 'name' => 'Clothes', 'children' => [...]]]
 *   $tree->tree($vocabulary, 'en', fallback: true); // a term without an English name shows its default name
 */
class TermTree
{
    /**
     * The given terms together with all their descendants, without duplicates.
     *
     * @param  list<string>  $uuids
     * @return list<string>
     */
    public function descendantUuids(array $uuids): array
    {
        $seen = array_fill_keys($uuids, true);
        $level = array_keys($seen);

        while ($level !== []) {
            $level = DB::table('taxonomy_terms')->whereIn('parent_uuid', $level)->pluck('uuid')
                ->reject(fn (string $uuid) => isset($seen[$uuid]))
                ->unique()->values()->all();

            $seen += array_fill_keys($level, true);
        }

        return array_keys($seen);
    }

    /**
     * Levels of the subtree of a term, counting the term itself (a leaf has height 1).
     */
    public function height(Term $term): int
    {
        return $this->levelsBelow([$term->uuid]);
    }

    /**
     * Levels of the whole tree of a vocabulary (0 for an empty vocabulary).
     */
    public function vocabularyHeight(Vocabulary $vocabulary): int
    {
        $roots = DB::table('taxonomy_terms')
            ->where('vocabulary_key', $vocabulary->key)->whereNull('parent_uuid')->pluck('uuid')->all();

        return $this->levelsBelow($roots);
    }

    /**
     * Uuids of the ancestors of a term, nearest first.
     *
     * @return list<string>
     */
    public function ancestorUuids(Term $term): array
    {
        $ancestors = [];
        $parent = $term->parent_uuid;

        while ($parent !== null && ! in_array($parent, $ancestors, true)) {
            $ancestors[] = $parent;
            $parent = DB::table('taxonomy_terms')->where('uuid', $parent)->value('parent_uuid');
        }

        return $ancestors;
    }

    /**
     * The terms of a vocabulary as a nested tree, siblings ordered by weight and id. The name and
     * description are those of the language; a missing translation gives `null`. With `$fallback` (the public
     * site) a blank name or description is replaced by the `default` one, each independently of the other.
     *
     * @param  bool  $fallback  Read the `default` text for a blank name or description.
     * @return list<array{uuid: string, slug: string, weight: int, image: string|null, name: string|null, description: string|null, children: list<array<string, mixed>>}>
     */
    public function tree(Vocabulary $vocabulary, string $lang = 'default', bool $fallback = false): array
    {
        $terms = DB::table('taxonomy_terms')
            ->where('vocabulary_key', $vocabulary->key)
            ->orderBy('weight')->orderBy('id')
            ->get(['uuid', 'parent_uuid', 'slug', 'weight', 'image']);

        $texts = DB::table('taxonomy_term_translations')
            ->whereIn('term_uuid', $terms->pluck('uuid'))->where('lang', $lang)
            ->get(['term_uuid', 'name', 'description'])->keyBy('term_uuid');

        if ($fallback && $lang !== config('content.default_lang')) {
            $base = DB::table('taxonomy_term_translations')
                ->whereIn('term_uuid', $terms->pluck('uuid'))->where('lang', config('content.default_lang'))
                ->get(['term_uuid', 'name', 'description'])->keyBy('term_uuid');
            $blank = fn (?string $text) => $text === null || trim($text) === '';

            $texts = $terms->mapWithKeys(fn (object $term) => [$term->uuid => (object) [
                'name' => $blank($texts[$term->uuid]->name ?? null) ? ($base[$term->uuid]->name ?? null) : $texts[$term->uuid]->name,
                'description' => $blank($texts[$term->uuid]->description ?? null) ? ($base[$term->uuid]->description ?? null) : $texts[$term->uuid]->description,
            ]]);
        }

        $byParent = $terms->groupBy(fn (object $term) => $term->parent_uuid ?? '');

        $build = function (string $parent, array $path) use (&$build, $byParent, $texts): array {
            $nodes = [];

            foreach ($byParent->get($parent, []) as $term) {
                if (in_array($term->uuid, $path, true)) {
                    continue;
                }

                $nodes[] = [
                    'uuid' => $term->uuid,
                    'slug' => $term->slug,
                    'weight' => (int) $term->weight,
                    'image' => $term->image,
                    'name' => $texts[$term->uuid]->name ?? null,
                    'description' => $texts[$term->uuid]->description ?? null,
                    'children' => $build($term->uuid, [...$path, $term->uuid]),
                ];
            }

            return $nodes;
        };

        return $build('', []);
    }

    /**
     * Count the levels from the given terms down.
     *
     * @param  list<string>  $level
     */
    private function levelsBelow(array $level): int
    {
        $seen = array_fill_keys($level, true);
        $levels = 0;

        while ($level !== []) {
            $levels++;
            $level = DB::table('taxonomy_terms')->whereIn('parent_uuid', $level)->pluck('uuid')
                ->reject(fn (string $uuid) => isset($seen[$uuid]))->values()->all();
            $seen += array_fill_keys($level, true);
        }

        return $levels;
    }
}
