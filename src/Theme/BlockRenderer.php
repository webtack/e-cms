<?php

namespace Webtack\Ecms\Theme;

use Webtack\Ecms\Content\ContentValueReader;
use Webtack\Ecms\Content\LocalizedUrl;
use Webtack\Ecms\Models\ContentField;
use Webtack\Ecms\Models\ContentNode;
use Webtack\Ecms\Models\ContentType;

/**
 * Places a block (a node of a type of kind `block`) into a template by its slug: `@block('block-footer')`.
 *
 * A block has no address of its own; it exists only where a template calls it. Only a visible block
 * (active, published, not expired) is shown: a block that does not exist, is not visible, or is a node of
 * another kind renders `''` and does not break the page.
 *
 * Candidates, most specific first: `blocks/block-{t}--{slug}`, `blocks/block-{t}`, `blocks/block`, where `{t}`
 * is the key of the block's type and `{slug}` the slug without the `block-` prefix. The hook is `block`. The
 * template receives `$node`, `$type`, `$values`, `$fields` (the type's page fields) and `$owner`, so it prints the
 * fields with the same `@field` / `@fields` directives as a page.
 *
 * A block rendered as the content of a `node_ref` item has an owner: the node whose field holds the reference.
 * Then `{owner}/block-{t}--{slug}` and `{owner}/block-{t}` (`{owner}` is the key of the owner's type) go before the
 * three candidates above, so a theme can give a block type another template for the pages of one type. A block
 * placed by `@block('slug')` has no owner (`$owner` is `null`) and keeps the three candidates.
 *
 * @example
 *   app(BlockRenderer::class)->render('block-footer');
 *   // candidates for a node of type `banner`: blocks/block-banner--footer, blocks/block-banner, blocks/block
 * @example In a template
 *
 *   @block('block-footer')
 */
class BlockRenderer
{
    public function __construct(
        private readonly ThemeManager $themes,
        private readonly ContentValueReader $reader,
        private readonly DisplayFields $display,
    ) {}

    /**
     * The template candidates of a block, most specific first.
     *
     *
     * @param  string|null  $ownerKey  The key of the owner's type when the block is the content of a reference; `null` for a placed block.
     * @return list<string>
     *
     * @example app(BlockRenderer::class)->candidates('banner', 'block-footer'); // ['blocks/block-banner--footer', 'blocks/block-banner', 'blocks/block']
     * @example app(BlockRenderer::class)->candidates('banner', 'block-footer', 'article'); // ['article/block-banner--footer', 'article/block-banner', 'blocks/block-banner--footer', 'blocks/block-banner', 'blocks/block']
     */
    public function candidates(string $typeKey, string $slug, ?string $ownerKey = null): array
    {
        $name = str_starts_with($slug, ContentNode::BLOCK_PREFIX) ? substr($slug, strlen(ContentNode::BLOCK_PREFIX)) : $slug;
        $shared = ["blocks/block-{$typeKey}--{$name}", "blocks/block-{$typeKey}", 'blocks/block'];

        return $ownerKey === null || $ownerKey === ''
            ? $shared
            : ["{$ownerKey}/block-{$typeKey}--{$name}", "{$ownerKey}/block-{$typeKey}", ...$shared];
    }

    /**
     * The HTML of the block with this slug, or `''` when there is no visible block with it.
     */
    public function render(string $slug): string
    {
        $node = ContentNode::findBySlug($slug, onlyVisible: true);
        $type = $node?->contentType;

        if ($node === null || $type === null || $type->kind !== 'block') {
            return '';
        }

        return $this->renderNode($node, $type);
    }

    /**
     * The HTML of a block that is already found, by the same candidates and with the same variables as
     * {@see self::render()}, so a block looks the same wherever it is placed (also as the content of a `node_ref`
     * item, see {@see NodeContentRenderer}). The caller checks the visibility and the kind.
     *
     * @param  array<string, mixed>|null  $values  The values to give the template; `null` reads all of them in the default language.
     * @param  array<string, ContentField>|null  $fields  The fields to give the template; `null` takes the page fields of the type.
     * @param  ContentNode|null  $owner  The node whose field holds the reference to the block; `null` for a placed block. Its type directory is searched first and the template receives it as `$owner`.
     *
     * @example app(BlockRenderer::class)->renderNode($node, $node->contentType); // the same output as @block('block-footer')
     * @example app(BlockRenderer::class)->renderNode($node, $node->contentType, owner: $page); // also tries `{page type}/block-{t}` first
     */
    public function renderNode(ContentNode $node, ContentType $type, ?array $values = null, ?array $fields = null, ?ContentNode $owner = null): string
    {
        return $this->themes->render(
            $this->candidates($type->key, $node->slug, $owner?->content_type),
            [
                'node' => $node,
                'type' => $type,
                'values' => $values ?? $this->reader->load([$node], null, app(LocalizedUrl::class)->lang(), app(LocalizedUrl::class)->isAdditional())[$node->id],
                'fields' => $fields ?? $this->display->pageFields($type),
                'owner' => $owner,
            ],
            'block',
        );
    }
}
