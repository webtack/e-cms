{{--
    Site frame: header (logo, name, menu `main`), content, footer (contacts). Extends html.
    Sections: content (filled by the page template).

    Variables (shared by every public page, built by Webtack\Ecms\Theme\PublicData):

    $site       Webtack\Ecms\Settings\SiteSettingsView  (the site settings in the reading language) name(), shortName(), description(), logoSmall(), logoLarge(), emails(), phones(), socials(), schedule()
    $theme      string                    name of the active theme
    $title      string                    document title: the page title as is, or the site name alone
    $meta       array                     always has the keys title, description, image (image is a URL); each is null when the record has no value
    $logoUrl    string|null               URL of the small logo, null when it is not set
    $assets     list<string>              Vite entries of the theme (empty when the theme has no assets)
    $breadcrumbs      list<array{title, url}>  the trail of the page (url is null on the last item); empty on the home page and 404, see partials/breadcrumbs
    $breadcrumbsJsonLd string|null            the same trail as BreadcrumbList JSON; printed in <head> by html
    $languages  list<array>               the languages for the switcher (code, lang, name, current, translated, url); empty on a single-language site, see partials/language-switcher
    $homeUrl    string                    the home page address in the reading language (`/` or `/en`)
    plus whatever the route adds (see the page templates).

    Directives: @menu('key') places a menu; @block('block-slug') places a block (a block has its own variables, see blocks/block).
--}}
@extends('theme::html')

@section('body')
    {{-- The header wraps onto several rows on a narrow screen; the menu sits after the logo and the name. --}}
    <header class="site-header mx-auto flex max-w-5xl flex-wrap items-center gap-x-6 gap-y-3 border-b border-line px-4 py-3 lg:px-6">
        @if ($site->logoSmall())
            <img class="h-10 w-auto" src="{{ $logoUrl }}" alt="{{ $site->name() }}">
        @endif
        <a class="text-lg font-semibold text-ink no-underline" href="{{ $homeUrl }}">{{ $site->name() ?? config('app.name') }}</a>
        {{-- Convention of the default theme: the menu with the key `main`; no such menu, no navigation. --}}
        @menu('main')
        @include('theme::partials.language-switcher')
    </header>

    {{-- The content column is capped in width and centered on wide screens. --}}
    <main class="site-main mx-auto w-full max-w-5xl px-4 py-6 lg:px-6 lg:py-10">
        @include('theme::partials.breadcrumbs')
        @yield('content')
    </main>

    <footer class="site-footer mx-auto mt-12 flex max-w-5xl flex-col gap-1 border-t border-line px-4 py-6 text-sm text-muted lg:px-6">
        @foreach ($site->emails() as $email)
            <div><a class="underline" href="mailto:{{ $email['value'] }}">{{ $email['value'] }}</a></div>
        @endforeach
        @foreach ($site->phones() as $phone)
            <div>{{ $phone['value'] }}</div>
        @endforeach
    </footer>
@endsection
