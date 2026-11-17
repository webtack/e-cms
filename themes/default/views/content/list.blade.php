{{--
    List of the records of a `content` type, GET /{type.slug}[?page=N].
    Candidates: {type}/list, content/list.

    Variables:

    Shared by every public page (Webtack\Ecms\Theme\PublicData):
    $site       Webtack\Ecms\Settings\SiteSettings  name(), shortName(), description(), logoSmall(), logoLarge(), emails(), phones(), socials(), schedule()
    $theme      string                    name of the active theme
    $title      string                    document title: the page title as is, or the site name alone
    $meta       array                     always has the keys title, description, image (image is a URL); each is null when the record has no value
    $logoUrl    string|null               URL of the small logo, null when it is not set
    $assets     list<string>              Vite entries of the theme (empty when the theme has no assets)

    The list:
    $type       Webtack\Ecms\Models\ContentType   the listed type (key, slug, name, ...)
    $sectionTitle string                  the public title of the type (its section_title, else its name); print this, not $type->name
    $paginator  Illuminate\Pagination\LengthAwarePaginator  visible records of the current page; ->items() are ContentNode objects
                                          whose `values` already hold `title` and the card fields (read in one batch)
    $sectionDescription string|null       the Section description of the type: sanitized HTML, print it unescaped ({!! !!}); null when empty
    $sectionCover string|null             public URL of the Section cover image of the type; null when empty
    $pagination bool                      whether to show the paginator (public display settings of the type)
    $fields     array<string, ContentField>  card fields by key: list_fields without `title`

    There is no $node or $values here: they belong to each card.
    Directives: @preview($item) prints the card of one record (see content/preview).
--}}
@extends('theme::layout')

@section('content')
    <h1 class="mb-6 text-3xl font-semibold text-ink">{{ $sectionTitle }}</h1>
    @if ($sectionCover)
        <img class="mb-6 h-auto max-w-full" src="{{ $sectionCover }}" alt="" loading="lazy">
    @endif
    @if ($sectionDescription)
        <div class="richtext mb-6 text-ink">{!! $sectionDescription !!}</div>
    @endif
    {{-- Cards go one under another with a gap; the paginator is set apart below them. --}}
    <div class="record-list flex flex-col gap-6">
        @foreach ($paginator->items() as $item)
            @preview($item)
        @endforeach
    </div>
    @if ($pagination)
        <div class="mt-8">{{ $paginator->links() }}</div>
    @endif
@endsection
