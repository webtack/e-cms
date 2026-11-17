{{--
    Field of the type `node_ref`. Candidates, most specific first: {t}/field-node_ref--{key}, {t}/field-node_ref, {t}/field--{key}, {t}/field,
    fields/field-node_ref--{key}, fields/field-node_ref, fields/field--{key}, fields/field ({t} is the record type key).

    Variables (only these: the shared $site, $meta, ... are NOT available in a field template):

    $items      array                     ALWAYS an array of ready values from FieldType::present(), also for a single field (then one element).
                                          It is never empty: a field without values prints nothing and this template is not even looked up.
    $field      Webtack\Ecms\Models\ContentField  the field (name is its label, printed in field__label unless hide_label is on; key, type, settings, ...)
    $key        string                    key of the field
    $ftype      string                    type of the field
    $multiple   bool                      whether the field is multiple
    $node       Webtack\Ecms\Models\ContentNode   the record the field belongs to
    $type       Webtack\Ecms\Models\ContentType   its type

    Each element of $items: array{uuid: string, title: string|null, url: string|null, content: string} of a visible record;
    url is null for a block (it has no address); content is the HTML of the target's own template: the block template
    for a block (the same as @block), the card (preview) for any other record. It is '' when the target is already
    being rendered or the chain is too deep. Invisible or deleted records are already dropped.

    Each item prints the content alone, because the card or the block already prints the title. Only when the content
    is empty (a cycle, the depth limit) the item prints the title (a link when it has an address), so the value is not lost.
--}}
<div class="field field-node_ref field--{{ $key }} mt-4">
    @unless ($field->hide_label)
        <div class="field__label text-sm font-medium text-muted">{{ $field->name }}</div>
    @endunless
    @foreach ($items as $item)
        <div class="field__item mt-1 text-ink">
            @if (($item['content'] ?? '') !== '')
                {!! $item['content'] !!}
            @elseif ($item['url'])
                <a href="{{ $item['url'] }}">{{ $item['title'] ?? $item['url'] }}</a>
            @else
                {{ $item['title'] }}
            @endif
        </div>
    @endforeach
</div>
