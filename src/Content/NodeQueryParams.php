<?php

namespace Webtack\Ecms\Content;

use Carbon\CarbonInterface;

/**
 * Parameters of a node listing; see {@see ContentNodeQuery}.
 *
 * Fields left `null` fall back to the `settings.list` of the content type, then to built-in defaults.
 *
 * @example
 *   new NodeQueryParams(
 *       fields: ['title', 'price'],
 *       lang: 'en',
 *       search: 'lamp',
 *       filters: [['field' => 'price', 'operator' => 'between', 'value' => [10, 20]]],
 *       sort: ['field' => 'price', 'direction' => 'desc'],
 *       page: 2,
 *       perPage: 10, // 0 = all nodes (up to `content.max_all`)
 *       onlyVisible: true, // the public site: only active, already published nodes
 *       fallback: true, // the public site: values and sort keys fall back to the base language
 *       period: ['field' => 'date', 'part' => 'current', 'since' => now()->startOfDay()], // not-past nodes by `date`
 *   );
 */
final class NodeQueryParams
{
    /**
     * @param  array<int, string>|null  $fields  Visible field keys.
     * @param  array<int, string>|null  $searchFields  Text fields searched by $search.
     * @param  array<int, array{field: string, operator: string, value: mixed}>  $filters  Combined with AND.
     * @param  array{field: string, direction?: string}|null  $sort
     * @param  int|null  $perPage  Nodes per page; `0` means "all nodes" in one page, up to `content.max_all`; `null` is `content.per_page`.
     * @param  bool  $onlyVisible  Keep only nodes shown on the site (`ContentNode::scopeVisible()`); the admin passes false and sees all.
     * @param  array{field: string, part: string, since: CarbonInterface}|null  $period  Not-past (`part = current`) or past
     *                                                                                   (`part = past`) nodes by one date: an added `date` / `datetime` field, `created_at` or `unpublished_at`;
     *                                                                                   `since` is the boundary moment. The two parts are exact complements.
     * @param  bool  $fallback  The public site's reading: a field blank in `lang` is read from the base language, and sorting orders a node without a value by its base value. Off for the admin panel and APIs.
     * @param  string|null  $untranslated  Keep only nodes with no stored value in this language (the admin filter "Without translation in"); the caller passes the code of an active additional language.
     */
    public function __construct(
        public readonly ?array $fields = null,
        public readonly string $lang = 'default',
        public readonly ?string $search = null,
        public readonly ?array $searchFields = null,
        public readonly array $filters = [],
        public readonly ?array $sort = null,
        public readonly int $page = 1,
        public readonly ?int $perPage = null,
        public readonly bool $onlyVisible = false,
        public readonly ?array $period = null,
        public readonly ?string $untranslated = null,
        public readonly bool $fallback = false,
    ) {}
}
