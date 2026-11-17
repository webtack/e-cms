<?php

namespace Webtack\Ecms\Http\Controllers\Public;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;
use Webtack\Ecms\Content\ContentNodeQuery;
use Webtack\Ecms\Content\ContentValueReader;
use Webtack\Ecms\Content\LocalizedUrl;
use Webtack\Ecms\Content\NodeQueryParams;
use Webtack\Ecms\Content\PublicDisplaySettings;
use Webtack\Ecms\Content\PublicPaths;
use Webtack\Ecms\Content\ViewCounter;
use Webtack\Ecms\Models\ContentNode;
use Webtack\Ecms\Models\ContentType;
use Webtack\Ecms\Theme\DisplayFields;
use Webtack\Ecms\Theme\FieldRenderer;
use Webtack\Ecms\Theme\PublicData;
use Webtack\Ecms\Theme\ThemeManager;
use Webtack\Ecms\Theme\TypeSection;

/**
 * Public pages of an address of any depth (`/{path}`, one or more segments), resolved in this order:
 *
 * 1. one segment: a visible `page` node with this slug;
 * 2. a `content` type whose slug equals the whole path: the list of its nodes (a type slug may have several segments);
 * 3. two or more segments: the type whose slug is the path without its last segment, and its visible node with
 *    the last segment as the slug;
 * 4. otherwise the theme's 404.
 *
 * A type list wins over a node when one address could mean both (the collision rules keep new data from
 * doing so). Only visible nodes are shown (active, published, not unpublished). The reserved check
 * (`PublicPaths::reserved()`) applies to the first segment and is never served, even if the database holds
 * such a slug. Blocks have no address. A record or page that was built is counted as a view ({@see ViewCounter}); lists and 404 are not. The template candidates go from the most specific to the most general.
 *
 * The fields a template shows come from the public display settings of the type (`PublicDisplaySettings`):
 * `$fields` (key => ContentField) holds `page_fields` for a page and for the details of a record, and the
 * `list_fields` without `title` for the cards of a list; the list is sorted, cut into pages and given a
 * paginator by the same settings (`$pagination` tells whether to show the paginator). A list also gets `$sectionTitle`,
 * the public title of the type, `$sectionDescription` (sanitized HTML or null) and `$sectionCover` (URL or null) ({@see TypeSection}).
 *
 * @example
 *   GET /about                        → pages/page-{type}--{id}, pages/page-{type}, pages/page
 *   GET /news                         → {type}/list, content/list
 *   GET /news/hello                   → {type}/details--{id}, {type}/details, content/details
 *   GET /common/institutions          → the list of the type with the slug "common/institutions"
 *   GET /common/institutions/hello    → the node "hello" of that type
 */
class PublicNodeController extends PublicController
{
    /** @var array<string, Collection<string, ContentField>> type key => fields that hold node values */
    private array $nodeFields = [];

    public function __construct(
        ThemeManager $themes,
        PublicData $data,
        private readonly PublicPaths $paths,
        private readonly ContentValueReader $reader,
    ) {
        parent::__construct($themes, $data);
    }

    /** `GET /{path}`: a page, the list of a content type, or a record of a content type. */
    public function show(Request $request, ContentNodeQuery $query, string $path): Response
    {
        $segments = explode('/', $path);

        if ($this->isReserved($segments[0])) {
            abort(404);
        }

        if (count($segments) === 1) {
            $node = ContentNode::findBySlug($path, onlyVisible: true);

            if ($node !== null && $node->contentType->kind === 'page') {
                return $this->page($request, $node);
            }
        }

        $type = $this->contentType($path);

        if ($type !== null) {
            return $this->list($request, $query, $type);
        }

        if (count($segments) > 1) {
            $slug = array_pop($segments);
            $type = $this->contentType(implode('/', $segments));

            if ($type !== null) {
                return $this->details($request, $type, $slug);
            }
        }

        return $this->notFound();
    }

    /**
     * The list of the visible records of a type, sorted and paged by its public display settings.
     *
     * A type with an archive ({@see PublicDisplaySettings}) is split by its archive date against the
     * start of the current UTC day: `/{slug}` lists the not-past records in the main sort and `/{slug}?period=archive`
     * the past ones in the archive sort (any other `period` value is the main list). A type without an archive
     * ignores `period`. The template gets `period` (`current` / `archive`) and `hasArchive` to render a switch.
     *
     * @example
     *   // GET /events?period=archive&page=2 → the second page of the past events, links keep `period=archive`
     */
    private function list(Request $request, ContentNodeQuery $query, ContentType $type): Response
    {
        // Per-request services are resolved here, not injected: the router keeps the controller between requests.
        $display = app(DisplayFields::class);
        $settings = $display->settings($type);
        $archive = $settings['archive'] ?? null;
        $inArchive = $archive !== null && $request->query('period') === 'archive';

        // The query reads `title` and the card fields of the whole page in one batch of queries.
        $paginator = $query->paginate($type, new NodeQueryParams(
            fields: $settings['list_fields'],
            lang: app(LocalizedUrl::class)->lang(),
            fallback: true,
            sort: $inArchive ? $archive['sort'] : $settings['sort'],
            page: max((int) $request->query('page', 1), 1),
            perPage: $settings['per_page'],
            onlyVisible: true,
            period: $archive === null ? null : [
                'field' => $archive['field'],
                'part' => $inArchive ? 'past' : 'current',
                'since' => now()->startOfDay(),
            ],
        ));

        if ($inArchive) {
            $paginator->appends(['period' => 'archive']);
        }

        $fields = $display->fields($type, array_values(array_diff($settings['list_fields'], ['title'])));
        // References of all the cards (terms, linked records) are read once, not per card.
        app(FieldRenderer::class)->preload($fields, array_map(fn (ContentNode $node) => $node->values, $paginator->items()));

        return $this->respond(
            ["{$type->key}/list", 'content/list'],
            'content.list',
            ['type' => $type, 'sectionTitle' => app(TypeSection::class)->title($type), 'sectionDescription' => app(TypeSection::class)->description($type), 'sectionCover' => app(TypeSection::class)->cover($type), 'paginator' => $paginator, 'fields' => $fields, 'pagination' => $settings['pagination'], 'period' => $inArchive ? 'archive' : 'current', 'hasArchive' => $archive !== null],
        );
    }

    /** A visible record of the type by its slug (the last segment of the address). */
    private function details(Request $request, ContentType $type, string $slug): Response
    {
        $node = ContentNode::query()
            ->visible()
            ->where('content_type', $type->key)
            ->where('slug', $slug)
            ->first();

        if ($node === null) {
            return $this->notFound();
        }

        $values = $this->readValues($node);

        $response = $this->respond(
            ["{$type->key}/details--{$node->id}", "{$type->key}/details", 'content/details'],
            'content.details',
            ['node' => $node, 'type' => $type, 'values' => $values, 'fields' => app(DisplayFields::class)->pageFields($type)],
            $this->titleOf($values),
        );

        // After the page is built, so a page that failed to render is never counted.
        app(ViewCounter::class)->record($request, $node);

        return $response;
    }

    private function page(Request $request, ContentNode $node): Response
    {
        $values = $this->readValues($node);
        $type = $node->content_type;

        $response = $this->respond(
            ["{$type}/page", "pages/page-{$type}--{$node->id}", "pages/page-{$type}", 'pages/page'],
            'page',
            ['node' => $node, 'type' => $node->contentType, 'values' => $values, 'fields' => app(DisplayFields::class)->pageFields($node->contentType)],
            $this->titleOf($values),
        );

        app(ViewCounter::class)->record($request, $node);

        return $response;
    }

    /**
     * All the values of a node in the reading language; each blank one is the base-language value (the fallback
     * is on for every public reading, which is a no-op in the base language).
     *
     * @return array<string, mixed>
     */
    private function readValues(ContentNode $node): array
    {
        $url = app(LocalizedUrl::class);

        return $this->reader->load([$node], null, $url->lang(), $url->isAdditional())[$node->id];
    }

    private function contentType(string $slug): ?ContentType
    {
        return ContentType::query()->ofKind('content')->where('slug', $slug)->first();
    }

    private function isReserved(string $segment): bool
    {
        return in_array(strtolower($segment), $this->paths->reserved(), true);
    }

    /** @param  array<string, mixed>  $values */
    private function titleOf(array $values): ?string
    {
        return is_string($values['title'] ?? null) ? $values['title'] : null;
    }
}
