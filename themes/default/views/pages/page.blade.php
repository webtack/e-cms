{{--
    A page (type of the kind `page`), GET /{slug}.
    Candidates: {type}/page, pages/page-{type}--{id}, pages/page-{type}, pages/page.

    Variables:

    Shared by every public page (Webtack\Ecms\Theme\PublicData):
    $site       Webtack\Ecms\Settings\SiteSettings  name(), shortName(), description(), logoSmall(), logoLarge(), emails(), phones(), socials(), schedule()
    $theme      string                    name of the active theme
    $title      string                    document title: the page title as is, or the site name alone
    $meta       array                     always has the keys title, description, image (image is a URL); each is null when the record has no value
    $logoUrl    string|null               URL of the small logo, null when it is not set
    $assets     list<string>              Vite entries of the theme (empty when the theme has no assets)

    The page:
    $node       Webtack\Ecms\Models\ContentNode   the record (id, slug, content_type, ...)
    $type       Webtack\Ecms\Models\ContentType   its type (key, slug, name, kind, ...)
    $values     array<string, mixed>      raw values by field key, from ContentValueReader (null when empty, [] for an empty multiple field);
                                          print them with @field/@fields, not by hand, so that present() and the field templates apply
    $fields     array<string, ContentField>  fields to show, in order, by key (page_fields of the type; all added fields when it has none)

    Directives: @fields($values) prints all of $fields; @fields($values, ['a', 'b']) the listed ones; @field($values, 'key') one field.
--}}
@extends('theme::layout')

@section('content')
    {{-- A page reads best at a narrower width than the lists. --}}
    <article class="page mx-auto max-w-3xl space-y-6">
        <h1 class="text-3xl font-semibold leading-tight text-ink">{{ $values['title'] ?? $node->slug }}</h1>
        @fields($values)
    </article>
@endsection
