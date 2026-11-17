<?php

namespace Webtack\Ecms\Content;

use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Webtack\Ecms\Models\ContentNode;
use Webtack\Ecms\Models\ContentType;
use XMLWriter;

/**
 * Builds the XML sitemap (`GET /sitemap.xml`, sitemaps.org protocol) from the type settings ({@see SitemapSettings}).
 *
 * The home page comes first (`monthly`, `1.0`, no `lastmod`). Then, per type ordered by key, the list address
 * (`/{type slug}`, when `xml.type` is on) and the visible nodes from the latest changed to the oldest (when
 * `xml.nodes` is on). Every type's addresses carry its `changefreq` and `priority`; `lastmod` is the node's
 * `updated_at` with the time-zone offset, and for a list address the latest `updated_at` of the visible nodes of the
 * type (omitted without any). The number of queries does not depend on the number of types: one for the types, one
 * grouped `max(updated_at)` for the list dates and one streamed read of the nodes. An address is written once. The
 * document is cut at `$limit` URLs (the protocol allows 50 000) and the cut is logged as a warning.
 *
 * @example
 *   $xml = app(SitemapXml::class)->build('https://example.com');
 *   $xml = app(SitemapXml::class)->build('https://example.com', limit: 3); // cut after three <url> elements
 */
class SitemapXml
{
    /** The protocol's maximum number of `url` elements in one file. */
    public const LIMIT = 50000;

    public function __construct(private readonly SitemapSettings $settings) {}

    /**
     * @param  string  $root  Scheme and host without a trailing slash (`https://example.com`).
     * @param  int  $limit  Maximum number of `url` elements; the rest is cut and logged.
     */
    public function build(string $root, int $limit = self::LIMIT): string
    {
        $root = rtrim($root, '/');
        $entries = $this->settings->xml();

        $writer = new XMLWriter;
        $writer->openMemory();
        $writer->setIndent(true);
        $writer->startDocument('1.0', 'UTF-8');
        $writer->startElement('urlset');
        $writer->writeAttribute('xmlns', 'http://www.sitemaps.org/schemas/sitemap/0.9');

        $seen = [];
        $count = 0;
        $cut = false;

        $write = function (string $path, ?string $lastmod, string $changefreq, string $priority) use ($writer, $root, &$seen, &$count, &$cut, $limit): void {
            $loc = $root.$path;

            if ($cut || isset($seen[$loc])) {
                return;
            }

            if ($count >= $limit) {
                $cut = true;

                return;
            }

            $seen[$loc] = true;
            $count++;
            $writer->startElement('url');
            $writer->writeElement('loc', $loc);

            if ($lastmod !== null) {
                $writer->writeElement('lastmod', $lastmod);
            }

            $writer->writeElement('changefreq', $changefreq);
            $writer->writeElement('priority', $priority);
            $writer->endElement();
        };

        $write('/', null, 'monthly', '1.0');

        $listDates = $this->listDates($entries);
        $byKey = [];

        foreach ($entries as $entry) {
            $byKey[$entry['type']->key] = $entry;
        }

        // `$pending` holds the types still to be written, in key order; a list address is written when its type
        // comes up, before the type's nodes (or before the next type's, when it has none).
        $pending = array_keys($byKey);
        $emitList = function (string $key) use (&$byKey, $listDates, $write): void {
            $entry = $byKey[$key];

            if ($entry['list']) {
                $write(SitemapSettings::address($entry['type']), $listDates[$key] ?? null, $entry['changefreq'], $entry['priority']);
            }
        };

        $nodeKeys = array_keys(array_filter($byKey, fn (array $entry) => $entry['nodes']));

        if ($nodeKeys !== []) {
            $nodes = ContentNode::query()
                ->visible()
                ->whereIn('content_nodes.content_type', $nodeKeys)
                ->select(['content_nodes.content_type', 'content_nodes.slug', 'content_nodes.updated_at'])
                ->orderBy('content_nodes.content_type')
                ->orderByDesc('content_nodes.updated_at')
                ->orderByDesc('content_nodes.id')
                ->cursor();

            foreach ($nodes as $node) {
                if ($cut) {
                    break;
                }

                // Write the list addresses of every type up to this node's type, then the node.
                while ($pending !== [] && in_array($node->content_type, $pending, true)) {
                    $key = array_shift($pending);
                    $emitList($key);

                    if ($key === $node->content_type) {
                        break;
                    }
                }

                $entry = $byKey[$node->content_type];
                $write(SitemapSettings::nodeAddress($entry['type'], $node->slug), $node->updated_at?->toAtomString(), $entry['changefreq'], $entry['priority']);
            }
        }

        foreach ($pending as $key) {
            $emitList($key);
        }

        $writer->endElement();
        $writer->endDocument();

        if ($cut) {
            Log::warning("The XML sitemap was cut at {$limit} URLs; the later addresses are not listed.", ['limit' => $limit]);
        }

        return $writer->outputMemory();
    }

    /**
     * The latest `updated_at` of the visible nodes of every type whose list address is written, as an atom date.
     *
     * @param  list<array{type: ContentType, list: bool, nodes: bool, changefreq: string, priority: string}>  $entries
     * @return array<string, string> type key => date
     */
    private function listDates(array $entries): array
    {
        $keys = array_map(fn (array $entry) => $entry['type']->key, array_filter($entries, fn (array $entry) => $entry['list']));

        if ($keys === []) {
            return [];
        }

        return ContentNode::query()
            ->visible()
            ->whereIn('content_nodes.content_type', $keys)
            ->groupBy('content_nodes.content_type')
            ->selectRaw('content_nodes.content_type, max(content_nodes.updated_at) as latest')
            ->pluck('latest', 'content_type')
            ->map(fn ($latest) => Carbon::parse($latest, config('app.timezone'))->toAtomString())
            ->all();
    }
}
