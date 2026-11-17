{{--
    Card of one record in a list; printed by @preview($item) from content/list.
    Candidates: {type}/preview--{id}, {type}/preview, content/preview.

    Variables (only these: the shared $site, $meta, ... are NOT available in a card):

    $node       Webtack\Ecms\Models\ContentNode   the record
    $type       Webtack\Ecms\Models\ContentType   its type
    $values     array<string, mixed>      values the list has read: `title` and the list_fields (raw, print them with @fields)
    $fields     array<string, ContentField>  card fields by key: list_fields without `title`
    $url        string                    address of the record: /{type.slug}/{node.slug}

    Directives: @fields($values), @fields($values, ['a', 'b']), @field($values, 'key').
--}}
<article class="card rounded-lg border border-line p-5">
    <h2 class="card__title text-xl font-semibold text-ink"><a class="underline-offset-4 hover:underline" href="{{ $url }}">{{ $values['title'] ?? $node->slug }}</a></h2>
    @fields($values)
</article>
