<?php

namespace Webtack\Ecms\Theme;

use Webtack\Ecms\Content\LocalizedUrl;
use Webtack\Ecms\Models\ContentField;
use Webtack\Ecms\Models\ContentNode;
use Webtack\Ecms\Models\ContentType;

/**
 * Renders the card (preview) of a record in a list. Candidates, most specific first:
 * `{t}/preview--{id}`, `{t}/preview`, `content/preview`, where `{t}` is the key of the record's content type
 * and `{id}` its numeric id. The hook is `content.preview`.
 *
 * The card template receives `$node`, `$type`, `$values` (the values the list loaded), `$fields` (the fields of
 * `list_fields` except `title`, by key) and `$url` (the address of the record, with the language prefix of the page). The `@preview($node)` directive
 * of the list template calls {@see self::renderInScope()} with the variables of that template.
 *
 * @example In content/list.blade.php
 *
 *   @foreach ($paginator->items() as $item) @preview($item) @endforeach
 */
class PreviewRenderer
{
    public function __construct(private readonly ThemeManager $themes) {}

    /**
     * The template candidates of a card, most specific first.
     *
     * @return list<string>
     *
     * @example app(PreviewRenderer::class)->candidates($node); // ['news/preview--7', 'news/preview', 'content/preview']
     */
    public function candidates(ContentNode $node): array
    {
        return ["{$node->content_type}/preview--{$node->id}", "{$node->content_type}/preview", 'content/preview'];
    }

    /**
     * @param  array<string, ContentField>  $fields  The fields the card shows, by key.
     * @param  array<string, mixed>|null  $values  The values to give the card; `null` takes the loaded `values` relation of the node (empty when it is not loaded).
     * @param  string|null  $url  The address of the card's record; `null` builds `/{type.slug}/{node.slug}`.
     *
     * @example app(PreviewRenderer::class)->render($node, $type, $fields, $values, '/news/hello'); // a card for a record outside a list
     */
    public function render(ContentNode $node, ContentType $type, array $fields, ?array $values = null, ?string $url = null): string
    {
        $values ??= $node->relationLoaded('values') ? $node->getRelation('values') : [];

        return $this->themes->render($this->candidates($node), [
            'node' => $node,
            'type' => $type,
            'values' => $values,
            'fields' => $fields,
            'url' => app(LocalizedUrl::class)->path($url ?? '/'.$type->slug.'/'.$node->slug),
        ], 'content.preview');
    }

    /**
     * The card of `$node`, with `$type` and `$fields` taken from the list template's variables.
     *
     * @param  array<string, mixed>  $scope  `get_defined_vars()` of the list template.
     *
     * @throws ThemeException When the template has no `$type`.
     */
    public function renderInScope(array $scope, ContentNode $node): string
    {
        $type = $scope['type'] ?? null;

        if (! $type instanceof ContentType) {
            throw ThemeException::missingContext('preview', ['type']);
        }

        return $this->render($node, $type, is_array($scope['fields'] ?? null) ? $scope['fields'] : []);
    }
}
