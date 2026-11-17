<?php

namespace Webtack\Ecms\Reference;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Webtack\Ecms\Http\Controllers\Admin\Concerns\TypeSections;
use Webtack\Ecms\Models\ContentField;

/**
 * What a `type_ref` / `node_ref` value stands for in the admin: the label of its target (the type's `name`, the
 * node's `title` in the default language) and the address of the target's edit page. Labels are never stored, so
 * they are always current; a target that is gone has no entry (callers show the stored value then).
 *
 * One query per kind of reference for the whole set of values, so a page of the list or a form costs two queries
 * at most.
 *
 * @example
 *   $labels = app(ReferenceLabels::class);
 *   $labels->types(['hero']);          // ['hero' => ['label' => 'Hero banner', 'href' => '/admin/block-types/hero/edit']]
 *   $labels->nodes([$article->uuid]);  // ['0190…' => ['label' => 'Latest news', 'href' => '/admin/content-types/article/nodes/0190…/edit']]
 */
class ReferenceLabels
{
    /**
     * Labels and edit addresses of content types by key.
     *
     * @param  list<string>  $keys
     * @return array<string, array{label: string, href: string}>
     */
    public function types(array $keys): array
    {
        if ($keys === []) {
            return [];
        }

        $found = [];

        foreach (DB::table('content_types')->whereIn('key', array_values(array_unique($keys)))->get(['key', 'name', 'kind']) as $type) {
            $found[$type->key] = [
                'label' => $type->name,
                'href' => route('admin.'.TypeSections::segment($type->kind).'.edit', $type->key, absolute: false),
            ];
        }

        return $found;
    }

    /**
     * Labels and edit addresses of nodes by uuid; the label is the `title` of the default language.
     *
     * @param  list<string>  $uuids
     * @return array<string, array{label: string, href: string}>
     */
    public function nodes(array $uuids): array
    {
        if ($uuids === []) {
            return [];
        }

        $rows = DB::table('content_nodes as n')
            ->join('content_types as t', 't.key', '=', 'n.content_type')
            ->leftJoin('content_values_string as v', function ($join) {
                $join->on('v.content_node_id', '=', 'n.id')
                    ->where('v.field_key', 'title')
                    ->where('v.lang', config('content.default_lang'))
                    ->where('v.weight', 0);
            })
            ->whereIn('n.uuid', array_values(array_unique($uuids)))
            ->get(['n.uuid', 'n.content_type', 't.kind', 'v.value as title']);

        $found = [];

        foreach ($rows as $row) {
            $found[$row->uuid] = [
                'label' => $row->title ?? $row->uuid,
                'href' => route('admin.'.TypeSections::segment($row->kind).'.nodes.edit', [$row->content_type, $row->uuid], absolute: false),
            ];
        }

        return $found;
    }

    /**
     * Targets of every `type_ref` / `node_ref` value held by the given fields in the given value sets.
     *
     * @param  iterable<int, ContentField>  $fields  The fields to look at; others are skipped.
     * @param  iterable<int, array<string, mixed>>  $valueSets  Stored values by field key, one set per node.
     * @return array{type_ref: array<string, array{label: string, href: string}>, node_ref: array<string, array{label: string, href: string}>}
     *
     * @example
     *   $labels->forValues($fields, [['source' => 'hero', 'related' => ['0190…']]]);
     *   // ['type_ref' => ['hero' => [...]], 'node_ref' => ['0190…' => [...]]]
     */
    public function forValues(iterable $fields, iterable $valueSets): array
    {
        $wanted = ['type_ref' => [], 'node_ref' => []];
        $sets = Collection::make($valueSets);

        foreach ($fields as $field) {
            if (! isset($wanted[$field->type])) {
                continue;
            }

            foreach ($sets as $values) {
                foreach ((array) ($values[$field->key] ?? []) as $value) {
                    if (is_string($value) && $value !== '') {
                        $wanted[$field->type][] = $value;
                    }
                }
            }
        }

        return ['type_ref' => $this->types($wanted['type_ref']), 'node_ref' => $this->nodes($wanted['node_ref'])];
    }
}
