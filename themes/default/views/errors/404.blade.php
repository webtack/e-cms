{{--
    The site 404 page (status 404): an unknown address, a hidden or missing record.

    Variables (shared by every public page, built by Webtack\Ecms\Theme\PublicData):

    $site       Webtack\Ecms\Settings\SiteSettings  name(), shortName(), description(), logoSmall(), logoLarge(), emails(), phones(), socials(), schedule()
    $theme      string                    name of the active theme
    $title      string                    document title: the page title as is, or the site name alone
    $meta       array                     always has the keys title, description, image (image is a URL); each is null when the record has no value
    $logoUrl    string|null               URL of the small logo, null when it is not set
    $assets     list<string>              Vite entries of the theme (empty when the theme has no assets)

    There is no $node, $type or $values here: no record was found.
--}}
@extends('theme::layout')

@section('content')
    <div class="py-16 text-center">
        <h1 class="text-5xl font-bold text-ink">404</h1>
        <p class="mt-4 text-lg text-muted">{{ __('Page not found.') }}</p>
    </div>
@endsection
