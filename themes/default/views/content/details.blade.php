{{--
    A record of a `content` type, GET /{type.slug}/{node.slug}.
    Candidates: {type}/details--{id}, {type}/details, content/details.

    Variables:

    Shared by every public page (Webtack\Ecms\Theme\PublicData):
    $site       Webtack\Ecms\Settings\SiteSettings  name(), shortName(), description(), logoSmall(), logoLarge(), emails(), phones(), socials(), schedule()
    $theme      string                    name of the active theme
    $title      string                    document title: the page title as is, or the site name alone
    $meta       array                     always has the keys title, description, image (image is a URL); each is null when the record has no value
    $logoUrl    string|null               URL of the small logo, null when it is not set
    $assets     list<string>              Vite entries of the theme (empty when the theme has no assets)

    The record:
    $node       Webtack\Ecms\Models\ContentNode   the record (id, slug, content_type, ...)
    $type       Webtack\Ecms\Models\ContentType   its type (key, slug, name, kind, ...)
    $values     array<string, mixed>      raw values by field key, from ContentValueReader (null when empty, [] for an empty multiple field);
                                          print them with @field/@fields, not by hand, so that present() and the field templates apply
    $fields     array<string, ContentField>  fields to show, in order, by key (page_fields of the type; all added fields when it has none)

    Directives: @fields($values), @fields($values, ['a', 'b']), @field($values, 'key').
--}}
@extends('theme::layout')

@section('content')
    <article class="record space-y-6">
        <h1 class="text-3xl font-semibold leading-tight text-ink">{{ $values['title'] ?? $node->slug }}</h1>
        @fields($values)
    </article>
@endsection
