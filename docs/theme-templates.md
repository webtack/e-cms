# Theme templates: fields, pages, blocks, menus

How the theme `default` (and any other theme) outputs the values of fields, pages, blocks, and menus. The theme skeleton, the "candidate → theme" cascade, and hooks are in [site-themes.md](site-themes.md); the routes and page variables are in [public-site.md](public-site.md); the format of values is in [content-values.md](content-values.md#public-presentation).

Principle: a template makes no queries and does not know the storage format. The raw value from `ContentValueReader` is turned into ready data by `FieldType::present()`, and the template only marks it up.

## Fields

`Webtack\Ecms\Theme\FieldRenderer` renders one field of a node. Step by step:

1. Each field value (for a multiple field — each item) is empty or not is decided by `FieldType::isBlank()`; the non-empty ones pass through `present()`, and those the type returned as `null` (for example, a reference to an invisible node) are dropped.
2. If `$items` is empty, the field outputs nothing (`''`), and the template is not even looked for.
3. Otherwise, the first existing one of the eight candidates is taken and rendered with the hooks `field` and `field.{ftype}`.

### Candidates

From specific to general; `{t}` — the key of the node's type, `{ftype}` — the field type (`string`, `node_ref`, …), `{key}` — the field key:

| № | Candidate |
| --- | --- |
| 1 | `{t}/field-{ftype}--{key}` |
| 2 | `{t}/field-{ftype}` |
| 3 | `{t}/field--{key}` |
| 4 | `{t}/field` |
| 5 | `fields/field-{ftype}--{key}` |
| 6 | `fields/field-{ftype}` |
| 7 | `fields/field--{key}` |
| 8 | `fields/field` |

The directory of the type `{t}` outranks the global `fields/` (the template `news/field-string` matters more than `fields/field-string`); within the directory, the template of the field type matters more than the template of the key. Further the usual order "candidate → theme" applies ([site-themes.md](site-themes.md#candidate-cascade)): `fields/field-image--cover` from `default` beats `fields/field-image` of the active theme.

### Field template variables

| Variable | Value |
| --- | --- |
| `$items` | **always an array** of ready values (`present()`), also for a single-value field (then it has one element) |
| `$field` | the `ContentField` model (`$field->settings`, …). Its `name` is **the label in the reading language** (the translated label, else the stored name): a detached copy that is never saved, so a template that prints `$field->name` as the label needs no change; the stored name is untouched |
| `$key`, `$ftype`, `$multiple` | the key and type of the field, whether it is multiple |
| `$node`, `$type` | the node and its type |
| `$values` | the values of the node by field key, as the caller has them (the ones passed to `@field` / `@fields`); `[]` when a field is rendered without them. No extra query is made for it |

`$values` lets a field template use another field of the same record. For example, a card can fall back to the record title:

```blade
{{-- fields/field-youtube--youtube.blade.php --}}
@foreach ($items as $item)
    {{ $item['title'] ?: ($values['title'] ?? $field->name) }}
@endforeach
```

In an additional language the values of `$items` follow the reading language with the fallback to the base one (term names, the file name and description, `url` addresses with the language prefix, rich text with prefixed internal links, referenced nodes and their titles): [public-site.md](public-site.md#language-prefix).

What each type's element of `$items` contains — the table in [content-values.md](content-values.md#public-presentation).

### Theme static files

For an image or font that belongs to the theme (`themes/<name>/assets/images/…`), the template calls the global helper `theme_asset($path)`. It returns `/app/<theme>/<path>?v=<mtime>` (active theme first, then `default`); a missing file gives the URL without `?v`. Print it with `{{ }}`:

```blade
<img src="{{ theme_asset('images/logo1.png') }}" alt="">
```

Details and the cascade rules — [site-themes.md](site-themes.md#static-files-fonts-images).

### Images of different sizes

For a thumbnail or a picture of a given size, the template calls the helper `image_style($url, $style)` on a ready image URL (for example, an element of `$items` of type `image`) or on a value `{uuid}.{ext}`:

```blade
<img src="{{ image_style($item['url'], 'thumb') }}" alt="">
<img src="{{ image_style($item['url'], '300x250') }}" alt="">
```

The style is a preset (`thumb`) or an arbitrary size `300x250`, `300x`, `x300` (Latin `x`); an external address and an invalid style are returned unchanged. Details, the format of files, and the signature of arbitrary sizes — [image-styles.md](image-styles.md#image_style-helper).

### URL of a stored file

A block or page template gets the raw value of a file or image field in `$values` (for example `$values['herotrack']`, a list of `{uuid}.{ext}` for a multiple field). The helper `file_url($value, $profile = 'image')` turns such a value into its public URL on the disk of the upload profile; use the profile `file` for a `file` field:

```blade
<img src="{{ file_url($values['cover']) }}" alt="">
<a href="{{ file_url($values['price'], 'file') }}">Price list</a>
```

`null` and `''` give `null`; a value that is not `{uuid}.{ext}` (an external address) is returned unchanged. It is the original file; for a resized picture use `image_style` above.

### `default` templates

The `default` templates are styled with Tailwind utilities directly in the markup (`layout`, `home`, `pages/page`, `content/*`, `blocks/block`, `menus/*`, `errors/404`, `fields/*`). The hook classes, by which other themes override the markup, remain first in the `class`: `site-header`, `site-main`, `site-footer`, `menu`, `menu--{key}`, `menu__item`, `breadcrumbs`, `breadcrumbs__item`, `page`, `record-list`, `card`, `card__title`, `block`, `block--{slug}`, `field`, `field-{ftype}`, `field--{key}`, `field__label`, `field__item`. The utilities are added to them, not in place of them. The content of `richtext` (HTML from the editor without classes) is styled by the ordinary selectors `.richtext` in `themes/default/assets/css/app.css`, so `field-richtext` puts the class `richtext` on `field__item`.

`themes/default/views/fields/` has `field-{ftype}.blade.php` for all 15 types and a common `field.blade.php`. `field-node_ref` prints the `content` of the target alone ([Node reference content](#node-reference-content)), because the card or the block already prints the title; only when the content is empty (a cycle or the depth limit) it prints the title as a link; a theme that wants only the link overrides this template. All of them have a shared markup `<div class="field field-{ftype} field--{key} mt-6">`, the field label `$field->name` — `<div class="field__label text-sm font-medium text-muted">` (a small muted caption), printed only when `$field->hide_label` is off (every default template wraps it in `@unless ($field->hide_label)`; the values are printed anyway; a custom theme reads `$field->hide_label` and decides for itself), the elements — `<div class="field__item mt-1 text-ink">`. The hooks `field`, `field-{ftype}`, `field--{key}`, `field__label`, `field__item` come first, the utilities follow them. The `richtext` field puts the class `richtext` on `field__item`, which styles the editor's HTML; `youtube` wraps the `iframe` in `aspect-video w-full` (a 16:9 ratio); `image` has `h-auto max-w-full`, and the captions `field__caption` and `field__description` are small muted text. `file` shows the file name (`original_name`) as a link and under it a `field__description` with the file's description; `image` puts the description in `alt` and in `field__caption`. The name and the description come from `PresentContext` in the reading language: in an additional language the translated display name and description of the file ([file-storage.md](file-storage.md#translated-names-and-descriptions-media_translations)) win, and a file without a translated value keeps the original name or the base description (`PresentContext::file()`, `AbstractFileFieldType::present()`, one extra query for a page). The original name is never changed. Everything is output through `{{ }}` (escaped); `{!! !!}` only in `richtext` (the HTML is cleaned in `present()`) and in `text` (`nl2br(e())`). The command `theme:check` requires that `default` has `field-{ftype}` for each type from the registry ([site-themes.md](site-themes.md#theme-check-themecheck)): a new field type without a template will be caught by the check.

### Blade directives

| Directive | What it does |
| --- | --- |
| `@field($values, 'key')` | outputs one field of the node `$node` |
| `@fields($values)` | outputs all fields from the variable `$fields` in its order |
| `@fields($values, ['a', 'b'])` | outputs the listed fields |

The directives take `$node`, `$type`, and `$fields` from the template variables (the controller puts them there), and the values — from the first argument. A key that the type does not have (the field was deleted in the admin panel) outputs nothing. A directive in a template without `$node` or `$type` throws `ThemeException` with the name of the missing variable.

```blade
<h1>{{ $values['title'] }}</h1>
@fields($values)

{{-- or selectively --}}
@field($values, 'cover')
@fields($values, ['price', 'color'])
```

### Field hooks

`preprocess('field')` runs for each field, `preprocess('field.youtube')` only for `youtube` (the general one first). `suggest('field')` / `suggest('field.{ftype}')` change the list of candidates. Example: a theme hides the captions under videos.

```php
// themes/<name>/hooks.php
use Webtack\Ecms\Theme\ThemeHooks; // see ../UPGRADING.md for the former import

return function (ThemeHooks $hooks): void {
    $hooks->preprocess('field.youtube', function (array &$vars) {
        $vars['items'] = array_map(fn (array $item) => [...$item, 'title' => null], $vars['items']);
    });
};
```

The example is checked by `tests/Feature/Theme/FieldRendererTest.php` (`test_the_documented_youtube_example_works`).

## Pages, list, and details

The `default` templates compose a page from the title and the fields: `<h1>{{ $values['title'] ?? $node->slug }}</h1>` and `@fields($values)` (all fields from the variable `$fields`, that is, the type's `page_fields`). This is how `pages/page` (a page) and `content/details` (a node) work.

The list `content/list` outputs the heading of the type (the variable `$sectionTitle`, not the administrative name), the cards through `@preview($item)`, and the paginator by the variable `$pagination`:

```blade
@foreach ($paginator->items() as $item)
    @preview($item)
@endforeach
@if ($pagination)
    <div class="mt-8">{{ $paginator->links() }}</div>
@endif
```

For a type with an archive the list also receives `$period` (`'current'` / `'archive'`, the list shown) and `$hasArchive`; a template uses them to render a link to `/{slug}?period=archive` ([public-site.md](public-site.md#archive-period-of-a-list)). The paginator already keeps `period=archive`, so `{{ $paginator->links() }}` needs no change.

### Sitemap page

`sitemap/page` (hook `sitemap`) is the page of `GET /sitemap` ([type-sitemap.md](type-sitemap.md#web-sitemap)). Besides the shared variables of every public page it receives `$items`, the tree of links: a list of `['title' => string, 'url' => string, 'children' => list]`, the first item being the home page. `title` is plain text (escape it), `url` is a relative address, and `children` is empty for a leaf. Walk it recursively, for example with a partial that includes itself (`default` and `dcculture` have `sitemap/page` and `sitemap/list`). The page is indexable; the template adds no `noindex`.

```blade
<ul>
    @foreach ($items as $item)
        <li><a href="{{ $item['url'] }}">{{ $item['title'] }}</a>
            @if ($item['children'] !== []) @include('theme::sitemap.list', ['items' => $item['children']]) @endif
        </li>
    @endforeach
</ul>
```

### Site search results

`search/results` (hook `search`) is the page of `GET /search` ([public-site.md](public-site.md#site-search)). It is optional: the `default` theme does not have it, because its layout has no search form, and a theme that offers the search adds it. It receives `$query` (the clean term), `$tooShort`, `$minLength`, and `$results` (a paginator of `Webtack\Ecms\Content\SearchResult` with `title`, `url`, `kind`, `sectionTitle`, `publishedAt`, `excerpt`, `titleParts` and `excerptParts`, or `null` while there is no query), plus the shared variables of every public page. The paginator already keeps `q`, so `{{ $results->links() }}` needs no change. The search form is a `GET` form to `/search` with the text field `q`. The response already carries `X-Robots-Tag: noindex`; the template does not add a `<meta>` tag.

```blade
<form action="/search" method="get"><input type="search" name="q" value="{{ $query }}"></form>
@if ($tooShort) <p>{{ __('At least :min characters', ['min' => $minLength]) }}</p> @endif
@foreach ($results ?? [] as $result)
    <a href="{{ $result->url }}">{{ $result->title }}</a>
@endforeach
```

`titleParts` and `excerptParts` split the title and the excerpt into `['text', 'match']` parts (`match` is true for an occurrence of the term, [public-site.md](public-site.md#site-search)). They are plain text, so print them escaped and wrap the flagged ones yourself; never use `{!! !!}`:

```blade
@foreach ($result->excerptParts as $part)@if ($part['match'])<mark>{{ $part['text'] }}</mark>@else{{ $part['text'] }}@endif
@endforeach
```

Keep the loop body unindented and on one line: whitespace between the parts would be printed and show as extra spaces around the highlighted words. The line break before `@endforeach` is needed (Blade does not compile `@endforeach` written right after `@endif`) and is not printed, because PHP drops one newline after `?>`.

### Node card (preview)

`@preview($item)` renders the card of one node from the list of candidates `{t}/preview--{id}`, `{t}/preview`, `content/preview` (`{id}` — the numeric `id` of the node, `{t}` — the type key), the hook `content.preview`. So a separate card can be set for a whole type (`news/preview`) or for one node (`news/preview--7`). The directive takes `$type` and `$fields` from the list's variables; without `$type` it throws `ThemeException`. The card receives:

| Variable | Value |
| --- | --- |
| `$node` | the node |
| `$type` | its type |
| `$values` | the values the list read (`title` and `list_fields`) |
| `$fields` | the card fields (`list_fields` without `title`), `key → ContentField` |
| `$url` | the address of the node (`/{type.slug}/{node.slug}`) |

`default/content/preview` shows `title` as a link and `@fields($values)`.

`$title` — the document title without the site name suffix; the suffix is added by a hook or in the theme's `layout`. The meta data get into the variable `$meta` of each template (the keys `title`, `description`, `image`; each value of `PageMeta` is taken from the node's own `meta_title`/`meta_description`/`meta_image`, then from the fields selected in the type's "Meta data" block (the "SEO" tab), then (for the title) from the node's `title`, then from the type's own `meta_*` (for the title, when the type has no `meta_title`, its Section title), then from the site settings: the description, the large logo, and as a fallback the small one — see [public-site.md](public-site.md#page-meta-data)) and are output in `<head>` by the template `html` ([public-site.md](public-site.md#layout-and-template-data)); another theme may not output them.

Tests: `tests/Feature/Public/PublicRoutesTest.php` (fields, sorting, paginator, meta data, batch queries), `tests/Feature/Theme/CandidateCascadeTest.php` (the cascade of preview).

## Languages

Every public template receives the language variables from `PublicData` (empty or single-language values while multilingual mode is off, [public-site.md](public-site.md#language-prefix)); there is no directive:

- `$lang` — the code of the document language (`<html lang="{{ $lang }}">`): the reading language of the address.
- `$site` — the site settings in the reading language, each missing text replaced by the default one (`Webtack\Ecms\Settings\SiteSettingsView`: `name()`, `shortName()`, `description()`, `address()`, `logoLarge()`, `logoSmall()`, `emails()`, `phones()`, `socials()`, `schedule()`, `indexable()`, without a language argument).
- `$homeUrl` — the address of the home page in the reading language (`/` or `/en`). Use it instead of a hard-coded `href="/"`.
- `$languages` — a list for a switcher, the base language first and then the active ones by weight: `code`, `lang` (`default` or the code), `name`, `current`, `translated` (the page has a stored value in it), `url` (the address of the same page in that language, relative, with the query string; for an untranslated language it leads to the page with the base-language content). Empty on a single-language site.
- `$alternates` and `$canonical` — the data of the `<link rel="alternate" hreflang>` and `<link rel="canonical">` tags (see below). Both are empty on a single-language site and on the 404.

The core prefixes the internal addresses it produces (menus, breadcrumbs, cards, references, `url` fields, search results, the sitemap page, paginator links, internal links in rich text). A template that builds an address by hand uses the same service:

```blade
<a href="{{ app(\Webtack\Ecms\Content\LocalizedUrl::class)->path('/contacts') }}">{{ __('Contacts') }}</a>
```

`path()` prefixes only internal addresses that start with `/`; absolute URLs, anchors, `mailto:`, service paths and addresses that already name a language are returned unchanged.

**Head signals.** The `html` template of every theme prints, from the variables above:

```blade
@if ($canonical)<link rel="canonical" href="{{ $canonical }}">@endif
@foreach ($alternates as $alternate)<link rel="alternate" hreflang="{{ $alternate['hreflang'] }}" href="{{ $alternate['url'] }}">@endforeach
```

`$alternates` lists the base language and every language the page is translated into, plus `x-default` at the base-language address. A page served in a language without a translation is canonical to the base-language address and is not an alternate. Pages without a node (home, lists, search, the sitemap page) are self-canonical and listed in every language.

**Switcher.** `themes/default/views/partials/language-switcher.blade.php` prints a `nav.language-switcher` with one link per language from `$languages` (the current one is a `span[aria-current]`); `layout` includes it after the main menu and it prints nothing for fewer than two languages. Another theme replaces only that file. The theme `dcculture` receives the same data but has no switcher in its markup yet.

Tests: `tests/Feature/Public/PublicHeadTest.php`, `tests/Feature/Public/PublicLinksTest.php`.

## Breadcrumbs

Every public template (the document shell, the layout, pages, and every partial they include) receives two variables from `PublicData`; there is no directive:

- `$breadcrumbs` — a list of `['title' => string, 'url' => string|null]`. `url` is absolute (scheme and host of the request) and, on a page in an additional language, carries its prefix (`/en/news`); the last item is the current page and has `url` of `null`. The list is empty on the home page and on 404.
- `$breadcrumbsJsonLd` — a ready `BreadcrumbList` JSON string for `<script type="application/ld+json">`, or `null` when the trail has fewer than two items. It is encoded with `JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP`, so a title can never close the script element; every item except the last has `item` (the absolute URL), the last has only `name`.

The trail is built by `Webtack\Ecms\Theme\Breadcrumbs` from the content-type hierarchy, never from menus:

| Page | Trail |
| --- | --- |
| Record (`content`) | Home, ancestor types (root first), its type, the record |
| Type list | Home, ancestor types, the type (current) |
| Page (`kind = page`) | Home, the page |
| Home, 404 | empty |

Ancestors follow `parent_type`; a type without a slug is skipped. The title of every type item is its public title (`section_title`, with the fallbacks described in [public-site.md](public-site.md#layout-and-template-data)), the record's title is its `title` value (its slug when empty), and the home item is the translation of "Home".

**Hook.** `ThemeManager::hooks()->applyPreprocess('breadcrumbs', ...)` runs before the variables are exposed (this name is not a template name). The callback receives `$vars['breadcrumbs']` and may add, remove, retitle, or clear items; both variables are built from the list as the hook leaves it, so the visible trail and the structured data cannot disagree:

```php
$hooks->preprocess('breadcrumbs', function (array &$vars) {
    $vars['breadcrumbs'][0]['title'] = 'Start';   // or: $vars['breadcrumbs'] = [];
});
```

### Default templates for breadcrumbs

- `themes/default/views/partials/breadcrumbs.blade.php`: `nav.breadcrumbs[aria-label="Breadcrumbs"]` > `ol` > `li.breadcrumbs__item`; an item with a `url` is a link followed by a `/` separator (`aria-hidden`), the last item is a `span[aria-current="page"]`. It prints nothing for an empty trail. `layout` includes it at the top of `<main>` (`@include('theme::partials.breadcrumbs')`), so another theme replaces only that file.
- `themes/default/views/html.blade.php` prints `<script type="application/ld+json">{!! $breadcrumbsJsonLd !!}</script>` in `<head>` when the variable is not `null`; a theme that overrides only the partial keeps the structured data, and one that hides the trail clears the list in the `breadcrumbs` hook.

Tests: `tests/Feature/Theme/BreadcrumbsTest.php`, `tests/Feature/Theme/TypeSectionTest.php`.

## Blocks

A block (a node of a type of kind `block`) has no address of its own: it appears where the template explicitly calls it with the directive `@block('slug')`, where `slug` is the full slug of the node together with the prefix `block-`.

```blade
<footer class="site-footer">
    @block('block-footer')
</footer>
```

`Webtack\Ecms\Theme\BlockRenderer` shows only a **visible** block (active, published, not expired). A block that does not exist, is disabled, scheduled, expired, or does not belong to the kind `block`, outputs nothing (`''`) and does not break the page. The candidates, from specific to general (`{t}` — the key of the block's type, `{slug}` — the slug without the prefix `block-`), the hook `block`:

| № | Candidate |
| --- | --- |
| 1 | `blocks/block-{t}--{slug}` |
| 2 | `blocks/block-{t}` |
| 3 | `blocks/block` |

**A block in the context of its owner.** When a block is the `content` of a `node_ref` item ([Node reference content](#node-reference-content)), it has an **owner**: the node whose field holds the reference. Then the directory of the owner's type is searched first (`{owner}` — the key of the owner's type, the same `views/{owner}/` that holds `{owner}/preview` or `{owner}/field-*`):

| № | Candidate |
| --- | --- |
| 1 | `{owner}/block-{t}--{slug}` |
| 2 | `{owner}/block-{t}` |
| 3 | `blocks/block-{t}--{slug}` |
| 4 | `blocks/block-{t}` |
| 5 | `blocks/block` |

So a theme gives a block type another template for the pages of one type (`views/institutions_sources/block-contacts.blade.php`) and keeps the shared one for every other owner; the owner's files come before `blocks/…` as in the other cascades. A block placed by `@block('slug')` has no owner and keeps the three candidates above.

The block's template receives `$node`, `$type`, `$values`, `$fields` (the type's fields by `page_fields`, otherwise all added ones) and `$owner` (the owner node; `null` for a block placed by `@block`), so the fields are output by the same `@field`/`@fields` as on the page. `default/blocks/block` wraps them in `<section class="block block--{slug}">` with a frame and margins. A block does not create a public address: `/block-footer` remains 404 ([public-site.md](public-site.md#routes)).

Tests: `tests/Feature/Theme/BlockRendererTest.php` (visible, invisible, missing, the cascade, the owner candidates, the directive without an owner, the address 404); the owner templates inside a reference: `tests/Feature/Theme/NodeContentRendererTest.php`.

## Node reference content

Every presented item of a `node_ref` field has `content`: the HTML of the target rendered by the target's own template, ready to print. The item is `['uuid', 'title', 'url', 'content']` ([content-values.md](content-values.md#public-presentation)); `content` is `''` when nothing can be rendered (see the guards below). There is no separate embed template, no new template name and no directive for it: the `node_ref` field template decides how the content is wrapped.

```blade
@foreach ($items as $item)
    {!! $item['content'] !!}
@endforeach
```

`FieldRenderer` adds `content` through `Webtack\Ecms\Theme\NodeContentRenderer`. Only a **visible** target (active, published, not expired) has content; a target that does not exist, is not visible, or is deleted gives no item at all, as before. Producing the content does not count a view and creates no public address. The values are read in the reading language without substitution by another language, and only for the fields the template shows.

**A block target.** `content` is the output of the block template, by the candidates of `@block('slug')`: `blocks/block-{t}--{slug}`, `blocks/block-{t}`, `blocks/block` ([Blocks](#blocks)), preceded by the candidates of the owner's type `{owner}/block-{t}--{slug}`, `{owner}/block-{t}` ([A block in the context of its owner](#blocks)); the variables are the same plus `$owner` (`$fields` are the type's `page_fields`). The owner is the node whose field is being rendered, so a reference placed inside another target takes that target as its owner. Without files in the owner's directory a block looks the same wherever it is placed; the item has no address. A card (node target) does not use the owner.

**A node target.** `content` is the card of the target, by the card candidates `{t}/preview--{id}`, `{t}/preview`, `content/preview` ([Pages, list, and details](#pages-list-and-details)), `{t}` being the key of the TARGET's type. The card gets the card variables and `$url`, the address of the target as the item presents it. The card fields are the `list_fields` of the type without `title` (kind `content`), or the `page_fields` for a kind that has no `list_fields` (a page); `title` is always read.

**The cascade of the target's type.** The fields the template prints with `@fields` / `@field` are output by the same field templates as on the target's own page, in the context of the TARGET: `{t}` in `{t}/field-{ftype}--{key}`, ..., `fields/field` is the key of the target's type, wherever the reference is placed. The hooks `preprocess('field')` apply as on a page, and the references inside the target are presented in turn. To change how a target looks inside a reference, override its block template or its card by the files above; a per-reference override does not exist.

**Guards.** A node that is already being rendered gets `content` equal to `''`: the node whose page or field holds the reference (`$node` of the field template), and any node rendered higher in the same chain. The template is rendered inside the chain, so the references placed in a block template or a card are guarded too. So a self-reference and a cycle A → B → A show the content once. Content nested deeper than 3 levels (`NodeContentRenderer::MAX_DEPTH`) is `''` too: for the chain A → B → C → D → E on the page of A, the content of B, C, and D is produced and that of E is not. An empty `content` does not remove the value: the template still has the title and the address and can print a link. The chain is kept for the request and released in `finally`, so a failing template cannot leave it dirty.

**Cost.** Content is produced for every `node_ref` item, also when a custom template ignores it. Each target costs its own queries (the node, its values, the references inside them); a page with many references pays per target, bounded by the depth limit. The `list_fields` of the target's type (the `page_fields` for a block or a page) control how heavy the content is.

`default/fields/field-node_ref` prints the content alone and, only when it is empty, the title (a link when `url` is set).

Tests: `tests/Feature/Theme/NodeContentRendererTest.php`, `tests/Feature/Theme/DefaultFieldTemplatesTest.php`.

## Menus

A menu ([menus.md](menus.md)) is placed with the directive `@menu('key')`, where `key` is the menu's key:

```blade
<header class="site-header">
    @menu('main')
</header>
```

`Webtack\Ecms\Theme\MenuRenderer` takes the tree of **visible** items `MenuTree::tree($key, 'default')` (a hidden item takes the whole branch with it) and renders it with a list template, the hook `menu`:

| № | Candidate |
| --- | --- |
| 1 | `menus/menu--{key}` |
| 2 | `menus/menu` |

A menu that does not exist, and a menu without visible items, output nothing (`''`) and do not break the page. The template receives `$menu` (the model), `$key`, and `$items` — the nested array of items `['uuid', 'title', 'description', 'url', 'target', 'css_class', 'image', 'is_external', 'is_active', 'is_trail', 'children']`; `title` is taken in the reading language without substitution. An address that `MenuUrl::isValid` does not pass (written bypassing the admin panel, for example `javascript:`) is removed from the item before rendering: an item without an address is output as a heading without a link.

**Active state.** `MenuRenderer` marks every item for the current request: `is_active` is `true` for the item that matches the requested path and `is_trail` for each ancestor of it; every other item has both `false`, and at most one item is active. The matching is done by `Webtack\Ecms\Theme\MenuActive` on the path of the item's stored address and the request path, ignoring the query string, the fragment and a trailing slash. An item matches when its path equals the request path or is a prefix of it that ends at a segment boundary (`/news` matches `/news/hello`, not `/newsletter`); `/` matches only the home page. The longest path wins, the first in menu order on a tie. Items without an address, external ones, `mailto:`, `tel:`, and addresses that start with `#` or `?` never match. A template only reads the two booleans (see also [menus.md](menus.md#reading-the-tree-in-the-frontend)).

**Absolute addresses.** A stored address that starts with `/` (not `//`) reaches the template as an absolute URL (`url()`), so `url` is ready to print; `is_external` and the match are decided from the stored address first, so such an item stays internal. Other addresses (`https://…`, `mailto:`, `tel:`, `#…`, `?…`) are passed unchanged.

The default menu templates add the states to the existing hook classes: the link of the active item gets the class `is-active` and `aria-current="page"`, and every item in the trail gets `is-trail` on its `li.menu__item`. `themes/default/assets/app.css` styles them (`.menu a.is-active`: accent colour, bold, underlined; `.menu .is-trail > a`: ink colour, bold).

`default/menus/menu` wraps the list in `<nav class="menu menu--{key}">` (on the right in the header). The first level is a horizontal row that wraps on a narrow screen; the nested levels are a column with an indent and a line on the left, and the parent item is highlighted in bold. The levels are built by the partial template `menus/list`, which includes itself for the children, so the depth is arbitrary (this is not a candidate: a theme that overrides `menus/menu--{key}` may include `theme::menus.list` or write its own markup). External links and items with `target = _blank` get `rel="noopener noreferrer"`; `target="_blank"` is added only for `_blank`.

### The `main` menu in the header

The key `main` is a convention of the theme `default`: `layout` calls `@menu('main')` in the header, so the menu is visible on every public page, including 404. If the menu `main` is not created (or has no visible items), the header is output without a menu. Another theme may use a different key or place several menus. An application theme can render it with its own template `menus/menu--main`.

Tests: `tests/Feature/Theme/MenuRendererTest.php` (nested items, hidden branches, a missing menu, `menu--footer`, the hook, the directive), `tests/Feature/Public/PublicRoutesTest.php` (the header with and without the `main` menu).

### How to add a new field type

1. The type's class with `present()` ([content-values.md](content-values.md#how-to-add-a-new-type)).
2. The template `themes/default/views/fields/field-{ftype}.blade.php`. The renderer, the directives, and the controllers do not change.

The `hooks.php` namespace changed with the packaging: [package-structure.md](package-structure.md#namespace-mapping).

Tests: `tests/Feature/Theme/` (`PresentTest`, `FieldRendererTest`, `DefaultFieldTemplatesTest`, `ThemeCheckCommandTest`, `BlockRendererTest`, `MenuRendererTest`).
