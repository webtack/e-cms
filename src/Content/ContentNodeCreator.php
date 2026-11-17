<?php

namespace Webtack\Ecms\Content;

use Carbon\CarbonInterface;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use LogicException;
use Webtack\Ecms\Models\ContentNode;
use Webtack\Ecms\Models\ContentType;

/**
 * The single way to create a node: `slug` is mandatory but the title lives in the values, so
 * values are validated first, then the slug is chosen, the node inserted and the values written,
 * all in one transaction.
 *
 * An explicit slug is validated and never changed. Otherwise it is generated from the base-language
 * `title` (content/page) or randomly (block); a lost race on the unique index repeats generation once.
 *
 * The author is the explicit `$authorId` or the authenticated user; a node without an author is a
 * programming error (`LogicException`). An empty `$publishedAt` means "now".
 *
 * @example
 *   $node = app(ContentNodeCreator::class)->create($type, ['title' => 'Про нас']); // slug "pro-nas"
 *   $node = app(ContentNodeCreator::class)->create($type, ['title' => 'Soon'], publishedAt: now()->addDay(), isActive: false);
 *   $node = app(ContentNodeCreator::class)->create($type, ['title' => 'About'], slug: 'about');
 *   $node = app(ContentNodeCreator::class)->create($type, ['title' => 'Old news'], views: 1250); // import: carry the legacy counter over
 *   // Without `views:` a new node starts at 1.
 *   $node = app(ContentNodeCreator::class)->create($type, ['title' => 'Jam'], slug: 'jam-session-', legacySlug: true); // import: keep the legacy address
 */
class ContentNodeCreator
{
    public function __construct(
        private readonly ContentValueWriter $writer,
        private readonly SlugGenerator $slugs,
    ) {}

    /**
     * Create a node with its values.
     *
     * @param  array<string, mixed>  $values
     * @param  string|null  $slug  Explicit slug; generated when null.
     * @param  string|null  $uuid  Explicit uuid (import); generated when null.
     * @param  int|null  $authorId  Author; the authenticated user when null.
     * @param  CarbonInterface|null  $publishedAt  Publication moment; now when null.
     * @param  bool  $isActive  Whether the node may be shown on the site.
     * @param  CarbonInterface|null  $unpublishedAt  Moment the node stops being visible; must be after the publication moment, null for no end.
     * @param  int  $views  Initial view counter: 1 by default (the creation counts as the first view); import passes the legacy value. Must not be negative.
     * @param  bool  $legacySlug  Accept the looser legacy slug format ({@see ContentNode::LEGACY_SLUG_PATTERN}); only the import passes true.
     *
     * @throws ValidationException
     * @throws LogicException When there is no author.
     */
    public function create(
        ContentType $type,
        array $values,
        ?string $slug = null,
        string $lang = 'default',
        ?string $uuid = null,
        ?int $authorId = null,
        ?CarbonInterface $publishedAt = null,
        bool $isActive = true,
        ?CarbonInterface $unpublishedAt = null,
        int $views = 1,
        bool $legacySlug = false,
    ): ContentNode {
        $authorId ??= auth()->id();

        if ($authorId === null) {
            throw new LogicException('A content node needs an author: pass $authorId or authenticate a user.');
        }

        // Validate everything that can fail on input before touching the database.
        $this->writer->validate($type->key, $values, $lang, creating: true);

        $publishedAt ??= now();

        return DB::transaction(function () use ($type, $values, $slug, $lang, $uuid, $authorId, $publishedAt, $isActive, $unpublishedAt, $views, $legacySlug) {
            $node = $this->insertNode($type, $values, $slug, $lang, $uuid, $authorId, $publishedAt, $isActive, $unpublishedAt, $views, $legacySlug);
            $this->writer->write($node, $values, $lang, creating: true);

            return $node;
        });
    }

    /**
     * @param  array<string, mixed>  $values
     */
    private function insertNode(ContentType $type, array $values, ?string $slug, string $lang, ?string $uuid, int $authorId, CarbonInterface $publishedAt, bool $isActive, ?CarbonInterface $unpublishedAt, int $views, bool $legacySlug): ContentNode
    {
        $title = $lang === config('content.default_lang') ? ($values['title'] ?? null) : null;
        $attempts = $slug === null ? 2 : 1;

        for ($attempt = 1; ; $attempt++) {
            $candidate = $slug ?? $this->slugs->forType($type, is_string($title) ? $title : null);

            try {
                // A savepoint keeps the outer transaction usable after a unique violation.
                return DB::transaction(function () use ($type, $candidate, $uuid, $authorId, $publishedAt, $isActive, $unpublishedAt, $views, $legacySlug) {
                    $node = new ContentNode([
                        'content_type' => $type->key,
                        'slug' => $candidate,
                        'uuid' => $uuid,
                        'published_at' => $publishedAt,
                        'unpublished_at' => $unpublishedAt,
                        'is_active' => $isActive,
                    ]);
                    // The author is not mass-assignable: it never comes from HTTP input.
                    $node->user_id = $authorId;
                    // Likewise the counter: only the creating code (an import) may seed it.
                    $node->views = $views;
                    $node->allowLegacySlug($legacySlug)->save();

                    return $node;
                });
            } catch (UniqueConstraintViolationException $e) {
                if ($attempt >= $attempts) {
                    if ($slug !== null && ContentNode::query()->where('slug', $slug)->exists()) {
                        throw ValidationException::withMessages(['slug' => "The slug [{$slug}] is already taken."]);
                    }

                    throw $e;
                }
            }
        }
    }
}
