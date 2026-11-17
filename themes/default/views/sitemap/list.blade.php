{{--
    One level of the web sitemap tree; calls itself for the children.
    Variables: $items (list of {title, url, children}), $root (bool, true on the first level).
--}}
<ul class="{{ ($root ?? false) ? 'space-y-2' : 'mt-2 space-y-2 border-l border-line pl-4' }}">
    @foreach ($items as $item)
        <li>
            <a class="text-ink underline-offset-4 hover:underline" href="{{ $item['url'] }}">{{ $item['title'] }}</a>
            @if ($item['children'] !== [])
                @include('theme::sitemap.list', ['items' => $item['children'], 'root' => false])
            @endif
        </li>
    @endforeach
</ul>
