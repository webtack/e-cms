<?php

namespace Webtack\Ecms\Content;

use Illuminate\Contracts\Database\Query\Builder as BuilderContract;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Webtack\Ecms\Models\ContentField;
use Webtack\Ecms\Models\ContentNode;
use Webtack\Ecms\Models\ContentType;
use Webtack\Ecms\Theme\TypeSection;

/**
 * Whole-site search of the public site: which visible `page` and `content` nodes contain a term.
 *
 * A node matches when its `title` or the value of one of its text fields (the field types whose
 * `searchable()` is true: `string`, `text`, `richtext`) contains the whole term, case-insensitively (the
 * database collation decides; `%` and `_` are matched literally). The search looks through **every language** of a
 * node (the base language and each active additional language), not only the reading language, and a node is returned once
 * however many of its languages match. The title is shown in the reading language with the fallback to the base
 * language; the excerpt is cut around the first match in the language in which the match was found (the reading
 * language first, then the base one, then the other active languages). Blocks, invisible nodes and records of a type without a slug (they have no public
 * address) are never returned. The excerpt of a result is cut around the first occurrence of the term, and the title
 * and the excerpt are also split into parts that flag the occurrences ({@see TermHighlighter}). The order is `published_at` descending, then `id` descending (no ranking).
 *
 * The term must already be clean (trimmed, cut to the maximum length, not shorter than the minimum): the caller
 * decides what counts as "no query". The cost is a fixed number of queries per page of results, not per result:
 * one for the page of nodes with the total, one for the searchable fields of the types shown, one batch read of the
 * values of each shown type (and one more per other language only for the nodes whose excerpt is not found in the
 * reading language) and one read of the section titles.
 *
 * @example
 *   $page = app(SiteSearch::class)->search('lamp', 'default', perPage: 15, page: 1);
 *   foreach ($page as $result) {
 *       echo $result->title, ' → ', $result->url; // "Lamp museum → /news/lamp-museum"
 *   }
 *   $page->total(); // 1
 */
class SiteSearch
{
    /** Escape character of LIKE patterns; the same as {@see ContentNodeQuery}. */
    private const LIKE_ESCAPE = '!';

    public function __construct(
        private readonly FieldTypeRegistry $registry,
        private readonly ContentValueReader $reader,
        private readonly HtmlSanitizer $html,
        private readonly TypeSection $sections,
        private readonly PublicLanguages $languages,
    ) {}

    /**
     * @param  string  $term  A clean, non-empty term.
     * @param  string  $lang  The reading language (`default` or a content language code); it picks the title and the first language of the excerpt, never limits the match.
     * @return LengthAwarePaginator<int, SearchResult>
     */
    public function search(string $term, string $lang, int $perPage, int $page = 1): LengthAwarePaginator
    {
        $pattern = $this->likePattern($term);

        $query = ContentNode::query()
            ->select('content_nodes.*')
            ->join('content_types', 'content_types.key', '=', 'content_nodes.content_type')
            ->visible()
            // A record needs a type slug to have an address; a page stands on its own slug.
            ->where(fn (Builder $kinds) => $kinds
                ->where('content_types.kind', 'page')
                ->orWhere(fn (Builder $records) => $records
                    ->where('content_types.kind', 'content')
                    ->whereNotNull('content_types.slug')
                    ->where('content_types.slug', '<>', '')))
            ->where(function (Builder $match) use ($pattern) {
                foreach ($this->searchableTypesByTable() as $table => $fieldTypes) {
                    $match->orWhereExists(fn ($sub) => $this->valueMatches($sub, $table, $fieldTypes, $this->searchedLanguages(), $pattern));
                }
            })
            ->orderByDesc('content_nodes.published_at')
            ->orderByDesc('content_nodes.id');

        $paginator = $query->paginate($perPage, ['content_nodes.*'], 'page', max($page, 1));
        $paginator->setCollection(collect($this->results($paginator->items(), $lang, $term)));

        return $paginator;
    }

    /**
     * Searchable field type keys grouped by the value table that stores them.
     *
     * @return array<string, list<string>> table => field type keys
     */
    private function searchableTypesByTable(): array
    {
        $tables = [];

        foreach ($this->searchableTypes() as $key) {
            $tables[$this->registry->get($key)->table()][] = $key;
        }

        return $tables;
    }

    /** @return list<string> Keys of the field types whose values can be searched. */
    private function searchableTypes(): array
    {
        return array_values(array_filter($this->registry->keys(), fn (string $key) => $this->registry->get($key)->searchable()));
    }

    /**
     * The languages a node is searched in: the base language and every active additional language.
     *
     * @return list<string>
     */
    private function searchedLanguages(): array
    {
        return [config('content.default_lang'), ...$this->languages->active()];
    }

    /**
     * The condition "the node has a value of a searchable field in one of the languages that contains the term".
     * Only rows of node values are read (`entity = node`), so the values of a type itself never match.
     *
     * @param  list<string>  $fieldTypes
     * @param  list<string>  $languages
     */
    private function valueMatches(BuilderContract|\Illuminate\Database\Query\Builder $sub, string $table, array $fieldTypes, array $languages, string $pattern): void
    {
        $sub->select(DB::raw(1))
            ->from($table)
            ->join('content_fields', function ($join) use ($table) {
                $join->on('content_fields.content_type', '=', "{$table}.content_type")
                    ->on('content_fields.key', '=', "{$table}.field_key");
            })
            ->whereColumn("{$table}.content_node_id", 'content_nodes.id')
            ->where("{$table}.entity", SystemFields::OWNER_NODE)
            ->whereIn("{$table}.lang", $languages)
            ->whereIn('content_fields.type', $fieldTypes)
            ->whereRaw("{$table}.value LIKE ? ESCAPE '".self::LIKE_ESCAPE."'", [$pattern]);
    }

    /**
     * Turn the nodes of one page into results: the titles and excerpts come from one batch read per type.
     *
     * @param  array<int, ContentNode>  $nodes
     * @param  string  $term  The clean search term; it picks the excerpt and marks the parts.
     * @return list<SearchResult>
     */
    private function results(array $nodes, string $lang, string $term): array
    {
        if ($nodes === []) {
            return [];
        }

        $typeKeys = array_values(array_unique(array_map(fn (ContentNode $node) => $node->content_type, $nodes)));
        $types = ContentType::query()->whereIn('key', $typeKeys)->get()->keyBy('key');
        $textFields = $this->textFields($typeKeys);

        $values = [];
        $matched = [];

        foreach (collect($nodes)->groupBy('content_type') as $typeKey => $group) {
            $keys = ['title', ...array_keys($textFields[$typeKey] ?? [])];
            // The reading language, each blank value replaced by the base one: the title and the first excerpt candidate.
            $values += $this->reader->load($group, $keys, $lang, fallback: true);

            foreach ($group as $node) {
                $matched[$node->id] = $this->excerpt($values[$node->id] ?? [], $textFields[$typeKey] ?? [], $term, onlyMatch: true);
            }

            // A node that matched in another language only: look for the match there, one batch per language.
            foreach (array_values(array_diff($this->searchedLanguages(), [$lang])) as $other) {
                $pending = $group->filter(fn (ContentNode $node) => $matched[$node->id] === null);

                if ($pending->isEmpty()) {
                    break;
                }

                foreach ($this->reader->load($pending, array_keys($textFields[$typeKey] ?? []), $other) as $id => $otherValues) {
                    $matched[$id] = $this->excerpt($otherValues, $textFields[$typeKey] ?? [], $term, onlyMatch: true);
                }
            }
        }

        return array_map(function (ContentNode $node) use ($types, $textFields, $values, $term, $matched) {
            $type = $types[$node->content_type];
            $own = $values[$node->id] ?? [];
            $title = $own['title'] ?? null;

            $title = is_string($title) && trim($title) !== '' ? trim($title) : $node->slug;
            $excerpt = $matched[$node->id] ?? $this->excerpt($own, $textFields[$node->content_type] ?? [], $term);

            return new SearchResult(
                title: $title,
                url: app(LocalizedUrl::class)->path($type->kind === 'page' ? '/'.$node->slug : '/'.$type->slug.'/'.$node->slug),
                kind: $type->kind,
                sectionTitle: $type->kind === 'content' ? $this->sections->title($type) : null,
                excerpt: $excerpt,
                titleParts: TermHighlighter::parts($title, $term),
                excerptParts: $excerpt === null ? [] : TermHighlighter::parts($excerpt, $term),
                publishedAt: $node->published_at,
            );
        }, $nodes);
    }

    /**
     * The searchable fields that hold node values (the title excluded), by type key, in field order.
     *
     * @param  list<string>  $typeKeys
     * @return array<string, array<string, string>> type key => field key => field type key
     */
    private function textFields(array $typeKeys): array
    {
        $keys = [];

        $fields = ContentField::query()
            ->whereIn('content_type', $typeKeys)
            ->whereIn('type', $this->searchableTypes())
            ->where('key', '<>', 'title')
            ->orderBy('weight')
            ->orderBy('id')
            ->get();

        foreach ($fields as $field) {
            if (ValueOwner::forNodesOfType($field->content_type)->owns($field->key)) {
                $keys[$field->content_type][$field->key] = $field->type;
            }
        }

        return $keys;
    }

    /**
     * The plain-text excerpt: a window around the first occurrence of the term in the first text field (in field
     * order) whose plain text contains it. When no field contains the term (the node matched by its title, or by
     * something the plain-text comparison does not see) it is the start of the first non-empty text value.
     *
     * @param  array<string, mixed>  $values
     * @param  array<string, string>  $fields  Field key => field type key, in reading order.
     * @param  bool  $onlyMatch  Return `null` instead of the start of the first text when no field contains the term.
     */
    private function excerpt(array $values, array $fields, string $term, bool $onlyMatch = false): ?string
    {
        $first = null;

        foreach ($fields as $key => $fieldType) {
            $value = $values[$key] ?? null;
            $value = is_array($value) ? collect($value)->first(fn ($item) => is_string($item) && trim($item) !== '') : $value;

            if (! is_string($value) || trim($value) === '') {
                continue;
            }

            $text = $fieldType === 'richtext'
                ? $this->html->text($value)
                : trim((string) preg_replace('/\s+/u', ' ', $value));

            if ($text === '') {
                continue;
            }

            if (TermHighlighter::contains($text, $term)) {
                return $this->window($text, $term);
            }

            $first ??= $text;
        }

        return $first === null || $onlyMatch ? null : $this->shorten($first);
    }

    /**
     * A part of the text around the first occurrence of the term, at most `content.meta_description_max`
     * characters including the "…" at each cut edge. The window starts about a third of the maximum before the match
     * (moved to a word boundary) and ends at a word boundary after it; a text that fits is returned whole.
     */
    private function window(string $text, string $term): string
    {
        $max = (int) config('content.meta_description_max');
        $length = mb_strlen($text);

        if ($length <= $max) {
            return $text;
        }

        // Character position and length of the first occurrence, from the same comparison the highlight uses.
        $position = 0;
        $matchLength = 0;

        foreach (TermHighlighter::parts($text, $term) as $part) {
            if ($part['match']) {
                $matchLength = mb_strlen($part['text']);

                break;
            }

            $position += mb_strlen($part['text']);
        }

        // About a third of the maximum before the match, less when the term itself needs the room (two edge "…").
        $start = max(0, $position - min(intdiv($max, 3), max($max - 2 - $matchLength, 0)));

        if ($start > 0 && mb_substr($text, $start - 1, 1) !== ' ') {
            // Start inside a word: begin after the next space that is still before the match, else at the start of
            // the word that holds the match.
            $space = mb_strpos(mb_substr($text, 0, $position), ' ', $start);
            $start = $space !== false
                ? $space + 1
                : (($before = mb_strrpos(mb_substr($text, 0, $position), ' ')) === false ? 0 : $before + 1);
        }

        $lead = $start > 0 ? 1 : 0;

        if ($length - $start + $lead <= $max) {
            return ($lead ? '…' : '').mb_substr($text, $start);
        }

        $room = max($max - $lead - 1, 1);
        $slice = mb_substr($text, $start, $room);

        if (mb_substr($text, $start + $room, 1) !== ' ') {
            $space = mb_strrpos($slice, ' ');

            // Cut back to a word boundary unless that would drop the match.
            if ($space !== false && $space >= $position - $start + $matchLength) {
                $slice = mb_substr($slice, 0, $space);
            }
        }

        return ($lead ? '…' : '').preg_replace('/[\s,.;:!?\-—]+$/u', '', $slice).'…';
    }

    /** Cuts a text longer than `content.meta_description_max` at a word boundary and adds "…" (as `PageMeta` does). */
    private function shorten(string $text): string
    {
        $max = (int) config('content.meta_description_max');

        if (mb_strlen($text) <= $max) {
            return $text;
        }

        $cut = mb_substr($text, 0, $max - 1);
        $space = mb_strrpos($cut, ' ');

        return preg_replace('/[\s,.;:!?\-—]+$/u', '', $space === false ? $cut : mb_substr($cut, 0, $space)).'…';
    }

    private function likePattern(string $term): string
    {
        $e = self::LIKE_ESCAPE;

        return '%'.str_replace([$e, '%', '_'], [$e.$e, $e.'%', $e.'_'], $term).'%';
    }
}
