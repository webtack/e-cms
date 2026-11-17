<?php

namespace Webtack\Ecms\Http\Controllers\Admin;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Webtack\Ecms\Http\Controllers\Controller;
use Webtack\Ecms\Models\ContentType;

/**
 * JSON search behind the autocomplete of the `type_ref` and `node_ref` fields: content types by `name` within
 * a kind, and nodes by `title` (language `default`) within a type. Read-only and not tied to a field; it is open
 * to every verified admin, like the rest of the admin.
 *
 * Both answer at most {@see self::LIMIT} results in a stable order; an empty query gives the first ones. A
 * missing or unknown `kind` / `content_type` is a validation error (422 for a JSON request).
 *
 * @example
 *   // GET /admin/lookup/content-types?kind=block&q=hero
 *   // [{"key":"hero","name":"Hero banner"}]
 *   // GET /admin/lookup/nodes?content_type=article&q=news
 *   // [{"uuid":"0190a1b2-…","title":"Latest news"}]
 */
class ReferenceLookupController extends Controller
{
    /** The most results one search returns. */
    public const LIMIT = 20;

    /** Character that escapes `%`, `_` and itself in a `LIKE` pattern. */
    private const LIKE_ESCAPE = '!';

    /**
     * Content types of a kind whose name contains the query.
     */
    public function contentTypes(Request $request): JsonResponse
    {
        $data = $request->validate([
            'kind' => ['required', 'string', Rule::in(config('content.kinds'))],
            'q' => ['nullable', 'string', 'max:255'],
        ]);

        $types = ContentType::query()
            ->where('kind', $data['kind'])
            ->when(($data['q'] ?? '') !== '', fn ($query) => $query->whereRaw('name LIKE ? ESCAPE \''.self::LIKE_ESCAPE.'\'', [$this->pattern($data['q'])]))
            ->orderBy('name')->orderBy('key')
            ->limit(self::LIMIT)
            ->get(['key', 'name']);

        return response()->json($types->map(fn (ContentType $type) => ['key' => $type->key, 'name' => $type->name])->values());
    }

    /**
     * Nodes of a type whose default-language title contains the query.
     */
    public function nodes(Request $request): JsonResponse
    {
        $data = $request->validate([
            'content_type' => ['required', 'string', Rule::exists('content_types', 'key')],
            'q' => ['nullable', 'string', 'max:255'],
        ]);

        $nodes = DB::table('content_nodes as n')
            ->join('content_values_string as t', function ($join) {
                $join->on('t.content_node_id', '=', 'n.id')
                    ->where('t.field_key', 'title')
                    ->where('t.lang', config('content.default_lang'))
                    ->where('t.weight', 0);
            })
            ->where('n.content_type', $data['content_type'])
            ->when(($data['q'] ?? '') !== '', fn ($query) => $query->whereRaw('t.value LIKE ? ESCAPE \''.self::LIKE_ESCAPE.'\'', [$this->pattern($data['q'])]))
            ->orderBy('t.value')->orderBy('n.id')
            ->limit(self::LIMIT)
            ->get(['n.uuid', 't.value as title']);

        return response()->json($nodes->map(fn (object $node) => ['uuid' => $node->uuid, 'title' => $node->title])->values());
    }

    /** A `LIKE` pattern matching the text anywhere, with the wildcards of the text taken literally. */
    private function pattern(string $text): string
    {
        $e = self::LIKE_ESCAPE;

        return '%'.str_replace([$e, '%', '_'], [$e.$e, $e.'%', $e.'_'], $text).'%';
    }
}
