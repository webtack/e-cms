{{--
    Breadcrumbs: a labelled navigation with an ordered list. Included at the top of <main> by layout; another theme
    replaces it by providing a file with the same name. Prints nothing when the trail is empty (home page, 404).

    Variables (shared by every public page, built by Webtack\Ecms\Theme\PublicData):

    $breadcrumbs  list<array{title: string, url: string|null}>  the trail; `url` is absolute, null on the last item (the current page)

    The structured data of the same trail is printed by html in <head> from $breadcrumbsJsonLd, so overriding this file keeps it.
--}}
@if ($breadcrumbs !== [])
    <nav class="breadcrumbs mb-6 text-sm text-muted" aria-label="{{ __('Breadcrumbs') }}">
        <ol class="flex flex-wrap items-center gap-x-2 gap-y-1">
            @foreach ($breadcrumbs as $crumb)
                <li class="breadcrumbs__item flex items-center gap-x-2">
                    @if ($crumb['url'] !== null)
                        <a class="hover:underline underline-offset-4" href="{{ $crumb['url'] }}">{{ $crumb['title'] }}</a>
                        <span aria-hidden="true">/</span>
                    @else
                        <span class="text-ink" aria-current="page">{{ $crumb['title'] }}</span>
                    @endif
                </li>
            @endforeach
        </ol>
    </nav>
@endif
