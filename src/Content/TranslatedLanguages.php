<?php

namespace Webtack\Ecms\Content;

use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Webtack\Ecms\Models\ContentNode;
use Webtack\Ecms\Models\ContentType;

/**
 * Which languages a node, or a type's own values, are translated into.
 *
 * An owner is translated into a language when at least one value of it is stored in that language
 * (the base language `default` never counts). The answer for a whole page of nodes is one
 * `UNION ALL` query over the value tables, never one query per node.
 *
 * @example
 *   $translated = app(TranslatedLanguages::class);
 *   $translated->forNodes([5, 6]);        // [5 => ['en'], 6 => []]
 *   $translated->forType($type);          // ['en', 'pl']
 *   $translated->withoutTranslation(ContentNode::query(), 'pl'); // nodes that have no Polish value
 */
class TranslatedLanguages
{
    public function __construct(private readonly FieldTypeRegistry $registry) {}

    /**
     * Languages with a stored value for each node of the list.
     *
     * @param  array<int, int>  $nodeIds
     * @return array<int, array<int, string>> Node id => sorted language codes (empty when none).
     */
    public function forNodes(array $nodeIds): array
    {
        $result = array_fill_keys($nodeIds, []);

        if ($nodeIds === []) {
            return $result;
        }

        $union = null;
        foreach ($this->registry->tables() as $table) {
            $query = DB::table($table)
                ->select('content_node_id', 'lang')
                ->where('entity', SystemFields::OWNER_NODE)
                ->whereIn('content_node_id', $nodeIds)
                ->where('lang', '<>', config('content.default_lang'));
            $union = $union === null ? $query : $union->unionAll($query);
        }

        foreach ($union->get() as $row) {
            $result[$row->content_node_id][$row->lang] = $row->lang;
        }

        return array_map(fn (array $langs) => array_values(collect($langs)->sort()->all()), $result);
    }

    /**
     * Languages with a stored value of the type itself (any tab).
     *
     * @return array<int, string> Sorted language codes.
     */
    public function forType(ContentType $type): array
    {
        $union = null;
        foreach ($this->registry->tables() as $table) {
            $query = DB::table($table)
                ->select('lang')
                ->where('entity', SystemFields::OWNER_TYPE)
                ->where('content_type', $type->key)
                ->whereNull('content_node_id')
                ->where('lang', '<>', config('content.default_lang'));
            $union = $union === null ? $query : $union->unionAll($query);
        }

        return $union->get()->pluck('lang')->unique()->sort()->values()->all();
    }

    /**
     * Keep only the nodes that have no stored value in the language.
     *
     * @param  EloquentBuilder<ContentNode>  $nodes  A query on `content_nodes`.
     * @return EloquentBuilder<ContentNode>
     */
    public function withoutTranslation(EloquentBuilder $nodes, string $lang): EloquentBuilder
    {
        $nodeTable = $nodes->getModel()->getTable();

        foreach ($this->registry->tables() as $table) {
            $nodes->whereNotExists(fn (Builder $q) => $q->select(DB::raw(1))
                ->from($table)
                ->whereColumn("{$table}.content_node_id", "{$nodeTable}.id")
                ->where("{$table}.entity", SystemFields::OWNER_NODE)
                ->where("{$table}.lang", $lang));
        }

        return $nodes;
    }
}
