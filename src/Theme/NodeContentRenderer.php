<?php

namespace Webtack\Ecms\Theme;

use Illuminate\Support\Str;
use Webtack\Ecms\Content\ContentValueReader;
use Webtack\Ecms\Content\PresentContext;
use Webtack\Ecms\Models\ContentNode;

/**
 * Renders the public content of a node referenced by a `node_ref` field: the HTML of the target's own template.
 *
 * {@see FieldRenderer::render()} calls it for every presented `node_ref` item and adds the result to the item as
 * `content`; there is no template and no directive of its own, the `node_ref` field template decides how the
 * content is wrapped. A target of kind `block` is rendered by its block template ({@see BlockRenderer::renderNode()},
 * the same output as `@block('slug')` unless the theme has a template in the directory of the type of the owner, with
 * the type's page fields); a target of any other kind by its card
 * ({@see PreviewRenderer::render()}: `{t}/preview--{id}`, `{t}/preview`, `content/preview`) with the card fields of
 * its type: `list_fields` without `title`, or the page fields for a kind that has no `list_fields`. Fields are output
 * by the same field templates as on the target's own page, in the context of the TARGET, so `{t}` in
 * `{t}/field-{ftype}--{key}` is the key of the target's type.
 *
 * The owner of a block is `$current` of {@see self::render()}: the node whose field is rendered, so a reference placed
 * inside another target takes that target as its owner. A card does not use the owner.
 *
 * Only a visible node (active, published, not expired) has content: a node that does not exist, is not visible, or
 * whose uuid is not a uuid gives `''`. Values are read in the reading language without substitution. Producing the
 * content does not count a view and creates no address.
 *
 * Guards: a node that is already being rendered (the node whose page or field is rendered, or one rendered higher
 * in the same chain) gives `''`, and so does a node nested deeper than {@see self::MAX_DEPTH} levels. The chain is a
 * stack of uuids kept for the request (the service is scoped) and popped in `finally`, so a failing template cannot
 * leave it dirty. The template is rendered inside the chain, so references placed in a block or a card are guarded too.
 *
 * @example
 *   app(NodeContentRenderer::class)->render($item['uuid'], $node, $item['url']);
 *   // '<article class="card">...</article>', or '' for a cycle or an invisible node
 */
class NodeContentRenderer
{
    /** How many levels of content may be nested inside the node being rendered (the page's own node is not counted). */
    public const MAX_DEPTH = 3;

    /** @var list<string> uuids of the nodes being rendered now, outermost first */
    private array $stack = [];

    public function __construct(
        private readonly ContentValueReader $reader,
        private readonly DisplayFields $display,
        private readonly FieldRenderer $fields,
        private readonly PresentContext $context,
        private readonly BlockRenderer $blocks,
        private readonly PreviewRenderer $previews,
    ) {}

    /**
     * The HTML of the block template or the card of the node with this uuid, or `''` when it is not visible, is
     * being rendered already or is too deep.
     *
     * @param  ContentNode|null  $current  The node whose page or field is rendered now; it counts as already being rendered.
     * @param  string|null  $url  The address of the target as the item presents it (the card's `$url`); `null` for a block.
     *
     * @example app(NodeContentRenderer::class)->render($item['uuid'], $node, $item['url']);
     */
    public function render(string $uuid, ?ContentNode $current = null, ?string $url = null): string
    {
        $uuid = strtolower($uuid);

        if (! Str::isUuid($uuid)) {
            return '';
        }

        $seeded = $this->stack === [] && $current !== null;

        if ($seeded) {
            $this->stack[] = strtolower($current->uuid);
        }

        try {
            return $this->content($uuid, $url, $current);
        } finally {
            if ($seeded) {
                array_shift($this->stack);
            }
        }
    }

    private function content(string $uuid, ?string $url, ?ContentNode $owner): string
    {
        if (in_array($uuid, $this->stack, true) || count($this->stack) - 1 >= self::MAX_DEPTH) {
            return '';
        }

        $node = ContentNode::query()->visible()->where('uuid', $uuid)->first();
        $type = $node?->contentType;

        if ($node === null || $type === null) {
            return '';
        }

        $isBlock = $type->kind === 'block';
        $settings = $this->display->settings($type);
        // A kind without `list_fields` (a page) shows its page fields in the card; `title` is read always, the card prints it.
        $fields = $isBlock || ! isset($settings['list_fields'])
            ? $this->display->pageFields($type)
            : $this->display->fields($type, array_values(array_diff($settings['list_fields'], ['title'])));

        $values = $this->reader->load([$node], ['title', ...array_keys($fields)], $this->context->lang, $this->context->fallback)[$node->id];
        $this->fields->preload($fields, [$values]);

        $this->stack[] = $uuid;

        try {
            return $isBlock
                ? $this->blocks->renderNode($node, $type, $values, $fields, $owner)
                : $this->previews->render($node, $type, $fields, $values, $url);
        } finally {
            array_pop($this->stack);
        }
    }
}
