{{--
    A menu; printed by @menu('key') where a template places it.
    Candidates: menus/menu--{key}, menus/menu. A missing menu, or one without visible items, prints nothing.

    Variables (only these: the shared $site, $meta, ... are NOT available in a menu):

    $menu       Webtack\Ecms\Models\Menu           the menu (name, ...)
    $key        string                    key of the menu, e.g. `main`
    $items      list<array>               visible top-level items, each with the keys
                                          uuid, title (reading language, may be null), description, url (null when it is invalid or empty),
                                          target (`_blank` or null), css_class, image, is_external (bool), is_active (bool: the item matches the requested path),
                                          is_trail (bool: the item is an ancestor of the active one), children (the same structure, any depth).
                                          `url` of an internal item is absolute.
--}}
{{-- Pushed to the right of the header row; on a narrow screen the items wrap in the list. --}}
<nav class="menu menu--{{ $key }} ml-auto" aria-label="{{ $menu->name }}">
    @include('theme::menus.list', ['items' => $items, 'depth' => 1])
</nav>
