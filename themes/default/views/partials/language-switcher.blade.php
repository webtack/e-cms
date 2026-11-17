{{--
    Language switcher: one link per language, the current one marked. Included by layout in the header; another theme
    replaces it by providing a file with the same name. Prints nothing on a single-language site.

    Variables (shared by every public page, built by Webtack\Ecms\Theme\PublicData):

    $languages  list<array{code, lang, name, current, translated, url}>  the base language first, then the active ones by weight (empty when multilingual mode is off).
                                          `url` is the address of the same page in that language, relative and with the query string; for a language the page
                                          is not translated into (`translated` is false) it leads to the page with the base-language content.
--}}
@if (count($languages) > 1)
    <nav class="language-switcher ml-4 text-sm text-muted" aria-label="{{ __('Language') }}">
        <ul class="flex flex-wrap items-center gap-x-5 gap-y-2">
            @foreach ($languages as $language)
                <li>
                    @if ($language['current'])
                        <span class="font-semibold text-ink" aria-current="true" lang="{{ $language['code'] }}">{{ $language['name'] }}</span>
                    @else
                        <a class="hover:underline underline-offset-4" href="{{ $language['url'] }}" hreflang="{{ $language['code'] }}" lang="{{ $language['code'] }}">{{ $language['name'] }}</a>
                    @endif
                </li>
            @endforeach
        </ul>
    </nav>
@endif
