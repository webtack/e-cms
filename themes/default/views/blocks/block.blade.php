{{--
    A block (record of the kind `block`); printed by @block('block-slug') where a template places it.
    Candidates: blocks/block-{type}--{slug}, blocks/block-{type}, blocks/block ({slug} is without the `block-` prefix).
    A hidden, missing or not-a-block record prints nothing.

    Variables (only these: the shared $site, $meta, ... are NOT available in a block):

    $node       Webtack\Ecms\Models\ContentNode   the block (slug is the full slug, with the `block-` prefix)
    $type       Webtack\Ecms\Models\ContentType   its type
    $values     array<string, mixed>      raw values by field key (null when empty)
    $fields     array<string, ContentField>  fields to show, in order, by key (page_fields of the type; all added fields when it has none)

    Directives: @fields($values), @fields($values, ['a', 'b']), @field($values, 'key').
--}}
<section class="block block--{{ $node->slug }}">
    @fields($values)
</section>
