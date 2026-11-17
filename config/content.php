<?php

use Webtack\Ecms\Content\FieldTypes\BooleanType;
use Webtack\Ecms\Content\FieldTypes\DateTimeType;
use Webtack\Ecms\Content\FieldTypes\DateType;
use Webtack\Ecms\Content\FieldTypes\DecimalType;
use Webtack\Ecms\Content\FieldTypes\FileType;
use Webtack\Ecms\Content\FieldTypes\ImageType;
use Webtack\Ecms\Content\FieldTypes\IntegerType;
use Webtack\Ecms\Content\FieldTypes\NodeRefType;
use Webtack\Ecms\Content\FieldTypes\RichTextType;
use Webtack\Ecms\Content\FieldTypes\StringType;
use Webtack\Ecms\Content\FieldTypes\TermType;
use Webtack\Ecms\Content\FieldTypes\TextType;
use Webtack\Ecms\Content\FieldTypes\TypeRefType;
use Webtack\Ecms\Content\FieldTypes\UrlType;
use Webtack\Ecms\Content\FieldTypes\YoutubeType;

return [

    /*
    |--------------------------------------------------------------------------
    | Content type kinds
    |--------------------------------------------------------------------------
    |
    | Allowed values of content_types.kind. All kinds share fields, values,
    | translations and querying; the kind only classifies a type. Adding a kind
    | later is a new line here.
    |
    */

    'kinds' => ['content', 'page', 'block'],

    /*
    |--------------------------------------------------------------------------
    | Field type registry
    |--------------------------------------------------------------------------
    |
    | Field type key => class implementing Webtack\Ecms\Content\FieldTypes\FieldType.
    | A new value type is one class, one content_values_{key} table migration and
    | one line here; the core read/write/query code does not change.
    |
    */

    'field_types' => [
        'string' => StringType::class,
        'text' => TextType::class,
        'richtext' => RichTextType::class,
        'integer' => IntegerType::class,
        'decimal' => DecimalType::class,
        'boolean' => BooleanType::class,
        'date' => DateType::class,
        'datetime' => DateTimeType::class,
        'image' => ImageType::class,
        'file' => FileType::class,
        'youtube' => YoutubeType::class,
        'term' => TermType::class,
        'url' => UrlType::class,
        'type_ref' => TypeRefType::class,
        'node_ref' => NodeRefType::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Base language
    |--------------------------------------------------------------------------
    |
    | Value of content_values_*.lang for base (untranslated) values.
    |
    */

    'default_lang' => 'default',

    /*
    |--------------------------------------------------------------------------
    | Base language code (unsaved)
    |--------------------------------------------------------------------------
    |
    | The code the base language reads as while `content.base_lang` is not saved
    | (see Webtack\Ecms\Content\ContentLanguages::baseLang()). It is separate from
    | `app.locale` on purpose: the SetLocale middleware overwrites `app.locale`
    | with the interface locale of the user on every request.
    |
    */

    'base_lang_default' => env('APP_LOCALE', 'en'),

    /*
    |--------------------------------------------------------------------------
    | Node querying limits
    |--------------------------------------------------------------------------
    |
    | max_filters bounds the number of EXISTS conditions in one query.
    |
    */

    /*
    |--------------------------------------------------------------------------
    | Reserved public paths
    |--------------------------------------------------------------------------
    |
    | First URL segments owned by the application itself: service routes and the
    | directories of public/. A page slug and a content type slug share one
    | first-segment namespace with them, so neither may equal one of these
    | (see Webtack\Ecms\Content\PublicPaths). A new service route or public/ directory
    | needs a line here; tests/Feature/Content/ReservedPathsGuardTest.php fails
    | until it is added. Language prefixes are not listed: the site languages
    | change adds them. `search` is the site search page (`GET /search`).
    |
    */

    'reserved_paths' => ['admin', 'login', 'logout', 'locale', 'email', 'up', 'storage', 'build', 'app', 'themes', 'images', 'search', 'sitemap', 'sitemap.xml'],

    /*
    |--------------------------------------------------------------------------
    | Site search
    |--------------------------------------------------------------------------
    |
    | Limits of the term of the public search page (`GET /search?q=`), in
    | characters. A shorter term (or an empty one) shows the form without
    | querying nodes; a longer term is cut to max_length. Shared by the
    | controller, the hint text of the theme and the tests.
    |
    */

    'search' => [
        'min_length' => 3,
        'max_length' => 100,
    ],

    'max_filters' => 10,

    // Longest meta description of a public page taken from a field (characters, the trailing "…" included).
    'meta_description_max' => 160,

    'per_page' => 15,

    'max_per_page' => 100,

    // Ceiling of the "all nodes" mode (per_page = 0), separate from max_per_page.
    'max_all' => 1000,

    /*
    |--------------------------------------------------------------------------
    | Node view counter
    |--------------------------------------------------------------------------
    |
    | The public site adds one to `content_nodes.views` for a successful GET of a
    | node page (see Webtack\Ecms\Content\ViewCounter).
    |
    | enabled: master switch (CONTENT_VIEWS_ENABLED); turn it off in development
    |          or behind a full-page cache that hides visits.
    | ttl: seconds during which the same visitor (IP + User-Agent) is counted once
    |      per node (CONTENT_VIEWS_TTL). 0 (default) turns the dedup off: every
    |      visit counts. When positive, the dedup key lives in the cache store,
    |      which must persist between requests.
    | count_authenticated: count visits of signed-in users too
    |      (CONTENT_VIEWS_COUNT_AUTHENTICATED, default true); false skips them.
    | bot_agents: case-insensitive substrings of the User-Agent that mark a bot;
    |             an empty User-Agent is a bot too. Not counted.
    |
    */

    'views' => [
        'enabled' => (bool) env('CONTENT_VIEWS_ENABLED', true),
        'ttl' => (int) env('CONTENT_VIEWS_TTL', 0),
        'count_authenticated' => (bool) env('CONTENT_VIEWS_COUNT_AUTHENTICATED', true),
        'bot_agents' => [
            'bot', 'crawl', 'spider', 'slurp', 'facebookexternalhit', 'preview',
            'headless', 'curl', 'wget', 'python-requests', 'monitor',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Rich text embeds
    |--------------------------------------------------------------------------
    |
    | iframe_hosts: where an iframe in a richtext value may point (https only).
    | An entry is a host, optionally followed by a path prefix, e.g.
    | 'www.facebook.com/plugins/' lets only the plugins of that site through.
    | The host must match exactly, so 'youtube.com.evil.example' never passes.
    | The same list is shared with the editor (Inertia prop richtext.iframeHosts).
    |
    | iframe_allow: names of Permissions Policy features an iframe `allow`
    | attribute may keep; everything else (camera, microphone ...) is dropped.
    |
    | max_input_length: the longest HTML (in characters) the sanitizer reads.
    | The library cuts longer input at 20,000 characters, which silently drops
    | the end of a long text (a table of results, an imported news body).
    |
    */

    'richtext' => [
        'max_input_length' => 1_000_000,
        'iframe_hosts' => [
            'www.youtube-nocookie.com/',
            'www.youtube.com/embed/',
            'player.vimeo.com/',
            'drive.google.com/',
            'docs.google.com/',
            'www.google.com/maps/embed',
            'www.facebook.com/plugins/',
            'www.instagram.com/',
            'platform.twitter.com/',
            'www.tiktok.com/embed/',
            't.me/',
        ],

        'iframe_allow' => [
            'accelerometer',
            'autoplay',
            'clipboard-write',
            'encrypted-media',
            'fullscreen',
            'gyroscope',
            'picture-in-picture',
            'web-share',
        ],
    ],

];
