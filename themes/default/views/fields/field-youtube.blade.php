{{--
    Field of the type `youtube`. Candidates, most specific first: {t}/field-youtube--{key}, {t}/field-youtube, {t}/field--{key}, {t}/field,
    fields/field-youtube--{key}, fields/field-youtube, fields/field--{key}, fields/field ({t} is the record type key).

    Variables (only these: the shared $site, $meta, ... are NOT available in a field template):

    $items      array                     ALWAYS an array of ready values from FieldType::present(), also for a single field (then one element).
                                          It is never empty: a field without values prints nothing and this template is not even looked up.
    $field      Webtack\Ecms\Models\ContentField  the field (name is its label, printed in field__label unless hide_label is on; key, type, settings, ...)
    $key        string                    key of the field
    $ftype      string                    type of the field
    $multiple   bool                      whether the field is multiple
    $node       Webtack\Ecms\Models\ContentNode   the record the field belongs to
    $type       Webtack\Ecms\Models\ContentType   its type

    Each element of $items: array{id, url (watch page), embed (nocookie embed URL), title|null, description|null, screen (preview image URL|null)}.
--}}
<div class="field field-youtube field--{{ $key }} mt-4">
    @unless ($field->hide_label)
        <div class="field__label text-sm font-medium text-muted">{{ $field->name }}</div>
    @endunless
    @foreach ($items as $item)
        <div class="field__item mt-1 text-ink">
            {{-- The player fills the content width at 16:9. --}}
            <div class="aspect-video w-full">
                <iframe class="h-full w-full" src="{{ $item['embed'] }}" title="{{ $item['title'] ?? $field->name }}" loading="lazy" allowfullscreen referrerpolicy="strict-origin-when-cross-origin"></iframe>
            </div>
            @if ($item['title'])
                <p class="field__caption mt-2 text-sm text-muted">{{ $item['title'] }}</p>
            @endif
        </div>
    @endforeach
</div>
