{{--
    Field of the type `type_ref`. Candidates, most specific first: {t}/field-type_ref--{key}, {t}/field-type_ref, {t}/field--{key}, {t}/field,
    fields/field-type_ref--{key}, fields/field-type_ref, fields/field--{key}, fields/field ({t} is the record type key).

    Variables (only these: the shared $site, $meta, ... are NOT available in a field template):

    $items      array                     ALWAYS an array of ready values from FieldType::present(), also for a single field (then one element).
                                          It is never empty: a field without values prints nothing and this template is not even looked up.
    $field      Webtack\Ecms\Models\ContentField  the field (name is its label, printed in field__label unless hide_label is on; key, type, settings, ...)
    $key        string                    key of the field
    $ftype      string                    type of the field
    $multiple   bool                      whether the field is multiple
    $node       Webtack\Ecms\Models\ContentNode   the record the field belongs to
    $type       Webtack\Ecms\Models\ContentType   its type

    Each element of $items: array{key: string, name: string, url: string|null}; url is set only for a type of the kind `content`.
--}}
<div class="field field-type_ref field--{{ $key }} mt-4">
    @unless ($field->hide_label)
        <div class="field__label text-sm font-medium text-muted">{{ $field->name }}</div>
    @endunless
    @foreach ($items as $item)
        <div class="field__item mt-1 text-ink">
            @if ($item['url'])
                <a href="{{ $item['url'] }}">{{ $item['name'] }}</a>
            @else
                {{ $item['name'] }}
            @endif
        </div>
    @endforeach
</div>
