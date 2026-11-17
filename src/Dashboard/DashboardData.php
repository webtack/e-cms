<?php

namespace Webtack\Ecms\Dashboard;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Webtack\Ecms\Access\AccessResolver;
use Webtack\Ecms\Contracts\EcmsUser;
use Webtack\Ecms\Http\Controllers\Admin\Concerns\TypeSections;
use Webtack\Ecms\Models\ContentNode;
use Webtack\Ecms\Models\ContentType;
use Webtack\Ecms\Models\File;
use Webtack\Ecms\Navigation\NodeTitles;
use Webtack\Ecms\Navigation\SidebarNavigation;
use Webtack\Ecms\Support\AppSettings;

/**
 * The data of every widget of the admin start page `/admin/dashboard`, limited to what the user's rights allow.
 *
 * Every node query starts from {@see self::scoped()}: the types on which the user's level is `any` (all nodes) or
 * `own` (nodes of the user only); a type with level `none` never reaches a query. Media follows the media level in
 * the same way. Definitions: *published* = active, already published and not yet unpublished (the rule of
 * `ContentNode::scopeVisible()`, evaluated at `$now`); *drafts* = disabled nodes; *total* = every accessible node, so it
 * may exceed published + drafts (scheduled and expired nodes are active but neither). See docs/admin-dashboard.md.
 *
 * @example
 *   $data = app(DashboardData::class)->forUser($user);
 *   $data['recent'][0];  // ['uuid' => '…', 'title' => 'About us', 'type' => ['key' => 'page', …], 'author' => 'Oksana', 'edit_url' => '/admin/page-types/page/nodes/…/edit', …]
 *   $data['counters'];   // [['key' => 'news', 'name' => 'News', 'kind' => 'content', 'href' => '…', 'total' => 10, 'published' => 6, 'drafts' => 2], …]
 *   $data['media'];      // null (level none) or ['count' => 12, 'size' => 5242880, 'latest' => [...]]
 */
class DashboardData
{
    /** Maximum rows of the list widgets other than "Recent changes". */
    public const LIMIT = 5;

    /** Maximum rows of "Recent changes" and of the latest media uploads (the two widgets that stand next to taller ones). */
    public const WIDE_LIMIT = 7;

    private const KIND_ORDER = ['content' => 0, 'page' => 1, 'block' => 2];

    public function __construct(
        private readonly AccessResolver $access,
        private readonly NodeTitles $nodeTitles,
        private readonly SidebarNavigation $sidebar,
        private readonly AppSettings $settings,
    ) {}

    /**
     * @param  CarbonInterface|null  $now  Moment to evaluate "published", "scheduled" and "expired" at; defaults to now (inject it in tests).
     * @return array{
     *     recent: list<array<string, mixed>>,
     *     drafts: list<array<string, mixed>>,
     *     create: list<array{key: string, name: string, kind: string, url: string}>,
     *     create_flat: bool,
     *     favorites: list<array{key: string, name: string, kind: string, href: string, count: int}>,
     *     counters: list<array{key: string, name: string, kind: string, href: string, total: int, published: int, drafts: int}>,
     *     top: list<array<string, mixed>>,
     *     scheduled: list<array<string, mixed>>,
     *     expired: list<array<string, mixed>>,
     *     media: array{count: int, size: int, latest: list<array<string, mixed>>}|null
     * }
     */
    public function forUser(EcmsUser $user, ?CarbonInterface $now = null): array
    {
        $now ??= now();
        $types = $this->accessibleTypes($user);
        $scope = $this->scope($types, $user);

        $lists = [
            'recent' => $this->nodes($scope)->orderByDesc('updated_at')->orderByDesc('id'),
            'drafts' => $this->nodes($scope)->where('is_active', false)->where('user_id', $user->id)->orderByDesc('updated_at')->orderByDesc('id'),
            'top' => $this->nodes($scope)->where('views', '>', 0)->orderByDesc('views')->orderByDesc('id'),
            'scheduled' => $this->nodes($scope)->where('is_active', true)->where('published_at', '>', $now)->orderBy('published_at')->orderBy('id'),
            'expired' => $this->nodes($scope)->where('is_active', true)->where('unpublished_at', '<=', $now)->orderByDesc('unpublished_at')->orderByDesc('id'),
        ];

        $nodes = $types->isEmpty()
            ? array_map(fn () => collect(), $lists)
            : collect($lists)->map(fn (Builder $query, string $name) => $query->limit($name === 'recent' ? self::WIDE_LIMIT : self::LIMIT)->get())->all();

        // One query for the titles of every listed node.
        $titles = $this->nodeTitles->for(collect($nodes)->flatten(1)->pluck('uuid')->unique()->values()->all());
        $rows = fn (string $name) => $nodes[$name]->map(fn (ContentNode $node) => $this->row($user, $node, $types[$node->content_type], $titles))->all();

        $counters = $this->counters($scope, $types, $now);

        return [
            'recent' => $rows('recent'),
            'drafts' => $rows('drafts'),
            ...$this->create($types),
            'favorites' => $this->favorites($user, $counters),
            'counters' => $counters,
            'top' => $rows('top'),
            'scheduled' => $rows('scheduled'),
            'expired' => $rows('expired'),
            'media' => $this->media($user),
        ];
    }

    /**
     * The types on which the user's level is not `none`, keyed by type key, ordered by kind and then by name.
     *
     * @return Collection<string, ContentType>
     */
    private function accessibleTypes(EcmsUser $user): Collection
    {
        return ContentType::query()->get(['key', 'name', 'kind'])
            ->filter(fn (ContentType $type) => $this->access->nodeLevel($user, $type) !== AccessResolver::NONE)
            ->sortBy(fn (ContentType $type) => [self::KIND_ORDER[$type->kind] ?? 99, mb_strtolower($type->name)])
            ->keyBy('key');
    }

    /**
     * The constraint of every node query: types with level `any` whole, types with level `own` for the user's nodes only.
     * A user with no accessible type gets a constraint that matches nothing.
     *
     * @param  Collection<string, ContentType>  $types  The accessible types.
     * @return callable(Builder<ContentNode>): Builder<ContentNode>
     */
    private function scope(Collection $types, EcmsUser $user): callable
    {
        $any = $types->filter(fn (ContentType $type) => $this->access->nodeLevel($user, $type) === AccessResolver::ANY)->keys()->all();
        $own = $types->filter(fn (ContentType $type) => $this->access->nodeLevel($user, $type) === AccessResolver::OWN)->keys()->all();

        return fn (Builder $query) => $query->where(function (Builder $q) use ($any, $own, $user) {
            if ($any === [] && $own === []) {
                $q->whereRaw('1 = 0');

                return;
            }

            $q->whereIn('content_nodes.content_type', $any)
                ->orWhere(fn (Builder $mine) => $mine->whereIn('content_nodes.content_type', $own)->where('content_nodes.user_id', $user->id));
        });
    }

    /**
     * The access-scoped base query of a node list widget.
     *
     * @return Builder<ContentNode>
     */
    private function nodes(callable $scope): Builder
    {
        return $scope(ContentNode::query()->with('author:id,name'));
    }

    /**
     * @param  array<string, string>  $titles
     * @return array<string, mixed>
     */
    private function row(EcmsUser $user, ContentNode $node, ContentType $type, array $titles): array
    {
        return [
            'uuid' => $node->uuid,
            'title' => $titles[$node->uuid] ?? $node->slug,
            'type' => ['key' => $type->key, 'name' => $type->name, 'kind' => $type->kind],
            'author' => $node->author?->name,
            'views' => $node->views,
            'updated_at' => $node->updated_at?->toIso8601String(),
            'published_at' => $node->published_at?->toIso8601String(),
            'unpublished_at' => $node->unpublished_at?->toIso8601String(),
            // Only a node the user may edit gets a link (the rule of the admin menu): `own` never links to another user's node.
            'edit_url' => $this->access->canEditNode($user, $type, $node->user_id)
                ? $this->typeUrl($type).'/nodes/'.$node->uuid.'/edit'
                : null,
        ];
    }

    /**
     * The "Create" menu: the types the user may create nodes of, with the creation form URL.
     *
     * Not configured (no `dashboard.create_types` setting): every accessible type, ordered by kind then name, and
     * `create_flat` is false (the client groups by kind). Configured: only the configured types, in the stored order,
     * and `create_flat` is true; a key the user has no access to or of a deleted type is skipped, so the list may be empty.
     *
     * @param  Collection<string, ContentType>  $types  The accessible types.
     * @return array{create: list<array{key: string, name: string, kind: string, url: string}>, create_flat: bool}
     *
     * @example
     *   // dashboard.create_types = ['page', 'news'] and the user may use both
     *   $this->create($types); // ['create' => [['key' => 'page', …], ['key' => 'news', …]], 'create_flat' => true]
     */
    private function create(Collection $types): array
    {
        $configured = $this->settings->get('dashboard.create_types');
        $flat = is_array($configured);

        if ($flat) {
            $types = collect($configured)->filter(fn ($key) => is_string($key) && $types->has($key))->unique()
                ->map(fn (string $key) => $types[$key]);
        }

        return [
            'create' => $types->map(fn (ContentType $type) => [
                'key' => $type->key,
                'name' => $type->name,
                'kind' => $type->kind,
                'url' => $this->typeUrl($type).'/nodes/create',
            ])->values()->all(),
            'create_flat' => $flat,
        ];
    }

    /**
     * Counters per accessible type from one grouped query; types without nodes get zeros.
     *
     * @param  Collection<string, ContentType>  $types
     * @return list<array{key: string, name: string, kind: string, href: string, total: int, published: int, drafts: int}>
     */
    private function counters(callable $scope, Collection $types, CarbonInterface $now): array
    {
        $sums = $types->isEmpty() ? collect() : $scope(ContentNode::query())
            ->selectRaw('content_type, count(*) as total, sum(case when is_active = 1 and published_at <= ? and (unpublished_at is null or unpublished_at > ?) then 1 else 0 end) as published, sum(case when is_active = 0 then 1 else 0 end) as drafts', [$now, $now])
            ->groupBy('content_type')
            ->get()
            ->keyBy('content_type');

        return $types->map(fn (ContentType $type) => [
            'key' => $type->key,
            'name' => $type->name,
            'kind' => $type->kind,
            'href' => $this->typeUrl($type).'/nodes',
            'total' => (int) ($sums[$type->key]->total ?? 0),
            'published' => (int) ($sums[$type->key]->published ?? 0),
            'drafts' => (int) ($sums[$type->key]->drafts ?? 0),
        ])->values()->all();
    }

    /**
     * The favorites of the sidebar (already limited to accessible types, in the order of adding) with the node count of each.
     *
     * @param  list<array{key: string, total: int}>  $counters
     * @return list<array{key: string, name: string, kind: string, href: string, count: int}>
     */
    private function favorites(EcmsUser $user, array $counters): array
    {
        $totals = collect($counters)->pluck('total', 'key');

        return collect($this->sidebar->forUser($user)['favorites'])
            ->map(fn (array $link) => [...$link, 'count' => (int) ($totals[$link['key']] ?? 0)])
            ->all();
    }

    /**
     * Files count, total size in bytes and the latest uploads, by the media level; null when the level is `none`.
     *
     * @return array{count: int, size: int, latest: list<array<string, mixed>>}|null
     */
    private function media(EcmsUser $user): ?array
    {
        $level = $this->access->mediaLevel($user);

        if ($level === AccessResolver::NONE) {
            return null;
        }

        $files = fn () => File::query()->when($level === AccessResolver::OWN, fn (Builder $query) => $query->where('user_id', $user->id));

        return [
            'count' => $files()->count(),
            'size' => (int) $files()->sum('size'),
            'latest' => $files()->orderByDesc('created_at')->orderByDesc('id')->limit(self::WIDE_LIMIT)->get()
                ->map(fn (File $file) => [
                    'uuid' => $file->uuid,
                    'name' => $file->original_name,
                    'size' => $file->size,
                    'created_at' => $file->created_at?->toIso8601String(),
                    'url' => route('admin.media.show', $file->uuid, false),
                ])->all(),
        ];
    }

    /** The admin section URL of a type, e.g. `/admin/content-types/news`. */
    private function typeUrl(ContentType $type): string
    {
        return '/admin/'.TypeSections::segment($type->kind).'/'.$type->key;
    }
}
