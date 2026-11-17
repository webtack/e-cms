{{--
    One level of a menu. Not a template candidate: menus/menu includes it, and it includes itself for children.

    Variables:

    $items      list<array>               items of this level; the keys are described in menus/menu (uuid, title, description, url, target, css_class, image, is_external, is_active, is_trail, children)
    $depth      int                       level, starting at 1
    $menu, $key                           inherited from menus/menu (the menu model and its key)
--}}
{{-- One level of a menu; it includes itself for the children, so any depth works. Not a template candidate. --}}
{{-- Top level: a row that wraps. Nested levels: an indented column with a rule on the left. --}}
<ul class="menu__list menu__list--{{ $depth }} {{ $depth === 1 ? 'flex flex-wrap items-center gap-x-5 gap-y-2' : 'ml-4 mt-2 flex flex-col gap-1 border-l border-line pl-3' }}">
    @foreach ($items as $item)
        @php
            $label = $item['title'] ?? $item['url'];
            $newTab = $item['target'] === '_blank';
            // Constant attributes only: no stored text reaches the unescaped output below.
            $attributes = ($newTab ? ' target="_blank"' : '').($newTab || $item['is_external'] ? ' rel="noopener noreferrer"' : '');
            // A parent item (with children) is bolder than the items it contains.
            $labelClass = ($item['children'] !== [] ? 'font-semibold ' : '').($depth === 1 ? 'text-ink' : 'text-sm text-muted').' hover:underline underline-offset-4';
            // `is-active` / `is-trail` are styled in assets/app.css (accent colour and underline; ink and bold for an ancestor).
            $active = $item['is_active'] ?? false;
            $trail = $item['is_trail'] ?? false;
            $labelClass .= $active ? ' is-active' : '';
        @endphp
        @continue($label === null)
        <li class="menu__item{{ $item['css_class'] ? ' '.$item['css_class'] : '' }}{{ $trail ? ' is-trail' : '' }}">
            @if ($item['url'])
                <a class="{{ $labelClass }}" href="{{ $item['url'] }}"{!! $attributes !!}{!! $active ? ' aria-current="page"' : '' !!}>{{ $label }}</a>
            @else
                <span class="{{ $labelClass }}">{{ $label }}</span>
            @endif
            @if ($item['children'] !== [])
                @include('theme::menus.list', ['items' => $item['children'], 'depth' => $depth + 1])
            @endif
        </li>
    @endforeach
</ul>
