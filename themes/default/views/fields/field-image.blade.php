{{--
    Field of the type `image`. Candidates, most specific first: {t}/field-image--{key}, {t}/field-image, {t}/field--{key}, {t}/field,
    fields/field-image--{key}, fields/field-image, fields/field--{key}, fields/field ({t} is the record type key).

    Variables (only these: the shared $site, $meta, ... are NOT available in a field template):

    $items      array                     ALWAYS an array of ready values from FieldType::present(), also for a single field (then one element).
                                          It is never empty: a field without values prints nothing and this template is not even looked up.
    $field      Webtack\Ecms\Models\ContentField  the field (name is its label, printed in field__label unless hide_label is on; key, type, settings, ...)
    $key        string                    key of the field
    $ftype      string                    type of the field
    $multiple   bool                      whether the field is multiple
    $node       Webtack\Ecms\Models\ContentNode   the record the field belongs to
    $type       Webtack\Ecms\Models\ContentType   its type

    Each element of $items: array{url: string, name: string, description: string|null}: public URL, client-side file name and the
    description of the image (null when empty); the description is the alt text and the caption.
--}}
<div class="field field-image field--{{ $key }} mt-4">
    @unless ($field->hide_label)
        <div class="field__label text-sm font-medium text-muted">{{ $field->name }}</div>
    @endunless
    @foreach ($items as $item)
        <div class="field__item mt-1 text-ink">
            {{-- Scaled down to the width of the content, the proportions kept. --}}
            <img class="h-auto max-w-full" src="{{ $item['url'] }}" alt="{{ $item['description'] ?? '' }}" loading="lazy">
            @if ($item['description'])
                <p class="field__caption mt-2 text-sm text-muted">{{ $item['description'] }}</p>
            @endif
        </div>
    @endforeach
</div>
