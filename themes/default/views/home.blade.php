{{--
    Home page, GET /.

    Variables (shared by every public page, built by Webtack\Ecms\Theme\PublicData):

    $site       Webtack\Ecms\Settings\SiteSettings  name(), shortName(), description(), logoSmall(), logoLarge(), emails(), phones(), socials(), schedule()
    $theme      string                    name of the active theme
    $title      string                    document title: the page title as is, or the site name alone
    $meta       array                     always has the keys title, description, image (image is a URL); each is null when the record has no value
    $logoUrl    string|null               URL of the small logo, null when it is not set
    $assets     list<string>              Vite entries of the theme (empty when the theme has no assets)

    The route adds nothing: there is no $node, $type or $values here.
--}}
@extends('theme::layout')

@section('content')
    <h1 class="text-3xl font-semibold leading-tight text-ink">{{ $site->name() ?? config('app.name') }}</h1>
    @if ($site->description())
        <p class="mt-4 max-w-prose text-lg text-muted">{{ $site->description() }}</p>
    @endif
@endsection
