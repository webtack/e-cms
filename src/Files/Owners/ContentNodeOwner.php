<?php

namespace Webtack\Ecms\Files\Owners;

use Illuminate\Support\Facades\DB;
use Webtack\Ecms\Http\Controllers\Admin\Concerns\TypeSections;

/**
 * Owner type `content_node`: the label is the node's title in the default language (the slug when
 * it has none), the link leads to the node's edit page in the section of the node's kind
 * (`content-types`, `page-types` or `block-types`).
 *
 * @example
 *   // a block node of type `hero` → '/admin/block-types/hero/nodes/{uuid}/edit'
 */
class ContentNodeOwner implements MediaOwner
{
    public function describe(array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        $titles = DB::table('content_values_string')
            ->whereIn('content_node_id', $ids)
            ->where('field_key', 'title')
            ->where('lang', config('content.default_lang'))
            ->orderBy('weight')
            ->pluck('value', 'content_node_id');

        $described = [];

        $nodes = DB::table('content_nodes')
            ->join('content_types', 'content_types.key', '=', 'content_nodes.content_type')
            ->whereIn('content_nodes.id', $ids)
            ->get(['content_nodes.id', 'content_nodes.uuid', 'content_nodes.slug', 'content_nodes.content_type', 'content_types.kind']);

        foreach ($nodes as $node) {
            $described[$node->id] = [
                'label' => $titles[$node->id] ?? $node->slug,
                'url' => route('admin.'.TypeSections::segment($node->kind).'.nodes.edit', [$node->content_type, $node->uuid], false),
            ];
        }

        return $described;
    }
}
