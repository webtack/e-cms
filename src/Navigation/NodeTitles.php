<?php

namespace Webtack\Ecms\Navigation;

use Illuminate\Support\Facades\DB;

/**
 * Titles of nodes for the admin menu labels, read for many nodes in one query.
 *
 * The title is the first `title` value in the current locale, then in the base language (`default`), then the node
 * `slug`, so a label is never empty. Nothing is cached or stored: a renamed node renames its menu item.
 *
 * @example
 *   app(NodeTitles::class)->for(['0190a1b2-…', '0190a1b3-…']);
 *   // ['0190a1b2-…' => 'About us', '0190a1b3-…' => 'contacts']  (the second one fell back to the slug)
 */
class NodeTitles
{
    /**
     * @param  list<string>  $uuids
     * @return array<string, string> Title per node uuid; unknown uuids are absent.
     */
    public function for(array $uuids): array
    {
        if ($uuids === []) {
            return [];
        }

        $base = config('content.default_lang');
        $locale = app()->getLocale();

        $rows = DB::table('content_nodes as n')
            ->leftJoin('content_values_string as t', function ($join) use ($base, $locale) {
                $join->on('t.content_node_id', '=', 'n.id')
                    ->where('t.field_key', 'title')
                    ->where('t.weight', 0)
                    ->whereIn('t.lang', array_unique([$locale, $base]));
            })
            ->whereIn('n.uuid', $uuids)
            ->get(['n.uuid', 'n.slug', 't.lang', 't.value']);

        $titles = [];

        foreach ($rows->groupBy('uuid') as $uuid => $group) {
            $byLang = $group->pluck('value', 'lang');
            $title = $byLang[$locale] ?? $byLang[$base] ?? null;

            $titles[$uuid] = $title !== null && $title !== '' ? $title : $group->first()->slug;
        }

        return $titles;
    }
}
