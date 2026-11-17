{{--
    Document shell: <html>, <head>, <body>. Wraps everything the site shows.
    Sections: body (filled by layout).

    Variables (shared by every public page, built by Webtack\Ecms\Theme\PublicData):

    $site       Webtack\Ecms\Settings\SiteSettings  name(), shortName(), description(), logoSmall(), logoLarge(), emails(), phones(), socials(), schedule()
    $theme      string                    name of the active theme
    $title      string                    document title: the page title as is, or the site name alone
    $meta       array                     always has the keys title, description, image (image is a URL); each is the record's meta_* value, else the field its type chose, else the site setting (description, large logo); null when none has a value
    $logoUrl    string|null               URL of the small logo, null when it is not set
    $assets     list<string>              Vite entries of the theme, vendor before app (empty when the theme has no assets)
    $assetTags  string                    ready HTML tags of those entries (stylesheets and scripts); print unescaped
    $breadcrumbs      list<array{title, url}>  the trail of the page; empty on the home page and 404 (printed by partials/breadcrumbs)
    $breadcrumbsJsonLd string|null            the trail as BreadcrumbList JSON, null when it has fewer than two items; print unescaped (safe inside a script)
    plus whatever the route adds (see the page templates).
--}}
<!DOCTYPE html>
<html lang="{{ $lang }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>{{ $title }}</title>
        {{-- Language signals: the canonical address and the hreflang alternates (empty on a single-language site and on the 404). --}}
        @if ($canonical)
            <link rel="canonical" href="{{ $canonical }}">
        @endif
        @foreach ($alternates as $alternate)
            <link rel="alternate" hreflang="{{ $alternate['hreflang'] }}" href="{{ $alternate['url'] }}">
        @endforeach
        @if ($meta['description'])
            <meta name="description" content="{{ $meta['description'] }}">
        @endif
        @if ($meta['image'])
            <meta property="og:image" content="{{ url($meta['image']) }}">
        @endif
        {!! $assetTags !!}
        @if ($breadcrumbsJsonLd !== null)
            <script type="application/ld+json">{!! $breadcrumbsJsonLd !!}</script>
        @endif
    </head>
    {{-- Body-level utilities: font, text color and background of the whole theme. --}}
    <body class="min-h-screen bg-white font-sans text-ink antialiased">
        @yield('body')
    </body>
</html>
