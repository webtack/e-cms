<?php

namespace Webtack\Ecms\Reference;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Webtack\Ecms\Models\ContentNode;
use Webtack\Ecms\Models\ContentType;

/**
 * Where a content type or a content node is referenced: the values of `type_ref` and `node_ref`
 * fields that hold its `key` / `uuid`.
 *
 * `content_values_string` has no foreign key to `content_types` or `content_nodes` (the table is
 * shared with string types), so these queries are the only source of truth for "is it referenced".
 * Values that the deletion itself removes by database cascade (the values owned by the deleted node,
 * or by the deleted type and its nodes) are not uses.
 *
 * @example
 *   app(ReferenceUsage::class)->forNode($node);
 *   // collect([['owner' => 'node', 'content_type' => 'page', 'field_key' => 'related', 'count' => 1]])
 */
class ReferenceUsage
{
    /**
     * Places where the node's uuid is stored in `node_ref` values, except the node's own values.
     *
     * @return Collection<int, array{owner: string, content_type: string, field_key: string, count: int}>
     */
    public function forNode(ContentNode $node): Collection
    {
        $query = $this->query('node_ref')->where('v.value', $node->uuid)
            ->where(fn (Builder $q) => $q->whereNull('v.content_node_id')->orWhere('v.content_node_id', '!=', $node->id));

        return $this->places($query);
    }

    /**
     * Places that reference the type: `type_ref` values holding its key and `node_ref` values holding the
     * uuid of one of its nodes. The values of the type's own fields (its nodes and the type itself) are not counted.
     *
     * @return Collection<int, array{owner: string, content_type: string, field_key: string, count: int}>
     */
    public function forType(ContentType $type): Collection
    {
        $types = $this->query('type_ref')->where('v.value', $type->key)->where('v.content_type', '!=', $type->key);

        $nodes = $this->query('node_ref')
            ->join('content_nodes as n', 'n.uuid', '=', 'v.value')
            ->where('n.content_type', $type->key)
            ->where('v.content_type', '!=', $type->key);

        return $this->places($types)->concat($this->places($nodes))->values();
    }

    /**
     * Value rows of the fields of the given type, joined to their field definition.
     */
    private function query(string $fieldType): Builder
    {
        return DB::table('content_values_string as v')
            ->join('content_fields as f', function ($join) {
                $join->on('f.content_type', '=', 'v.content_type')->on('f.key', '=', 'v.field_key');
            })
            ->where('f.type', $fieldType);
    }

    /**
     * @return Collection<int, array{owner: string, content_type: string, field_key: string, count: int}>
     */
    private function places(Builder $query): Collection
    {
        return $query
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
}
