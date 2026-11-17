{{--
    The web sitemap, GET /sitemap. Candidate: sitemap/page, hook `sitemap`.
    Variables (the shared ones of every public page, plus):
    $items  list<array{title: string, url: string, children: list<array>}>  the tree of links; `title` is plain text (escape it),
            `url` is a relative address, `children` is empty for a leaf. The first item is the home page.
--}}
@extends('theme::layout')

@section('content')
    <div class="mx-auto max-w-3xl space-y-6">
        <h1 class="text-3xl font-semibold leading-tight text-ink">{{ __('Sitemap') }}</h1>
        @include('theme::sitemap.list', ['items' => $items, 'root' => true])
    </div>
@endsection
