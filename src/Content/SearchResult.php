<?php

namespace Webtack\Ecms\Content;

use Carbon\CarbonInterface;

/**
 * One hit of the site search ({@see SiteSearch}): plain data a theme template prints, independent of how the
 * search finds nodes.
 *
 * @example
 *   $result->title;        // "Lamp museum"
 *   $result->url;          // "/news/lamp-museum"
 *   $result->kind;         // "content" | "page"
 *   $result->sectionTitle; // "News" for a record, null for a page
 *   $result->excerpt;      // "The museum collects lamps from…" | null
 *   $result->titleParts;   // [['text' => 'Lamp', 'match' => true], ['text' => ' museum', 'match' => false]]
 *   $result->excerptParts; // the same split for the excerpt; [] when there is no excerpt
 *   $result->publishedAt;  // Carbon, the `published_at` of the node
 */
final readonly class SearchResult
{
    public function __construct(
        /** The node title in the reading language (the slug when the node has no title in it). */
        public string $title,
        /** Public address: `/{slug}` for a page, `/{type slug}/{node slug}` for a record. */
        public string $url,
        /** Kind of the node's type: `page` or `content`. */
        public string $kind,
        /** Public title of the node's type for a record; null for a page. */
        public ?string $sectionTitle,
        /** Plain text around the first occurrence of the term in the first text field that has it (else the start of the first non-empty text value), or null. */
        public ?string $excerpt,
        /**
         * The `title` split into consecutive parts; `match` is true for an occurrence of the term. Plain text only,
         * so a template must escape it. Joined, the parts equal `title`.
         *
         * @var list<array{text: string, match: bool}>
         */
        public array $titleParts,
        /**
         * The `excerpt` split like `titleParts`; an empty list when there is no excerpt. Joined, the parts equal `excerpt`.
         *
         * @var list<array{text: string, match: bool}>
         */
        public array $excerptParts,
        /** When the node was published (`published_at`, UTC). */
        public CarbonInterface $publishedAt,
    ) {}
}
