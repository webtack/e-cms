<?php

/*
|--------------------------------------------------------------------------
| E-CMS package settings
|--------------------------------------------------------------------------
|
| Feature switches and lists the package reads at boot. Every key can be
| overridden by the host's own `config/ecms.php` (merged key by key at the
| top level, see Webtack\Ecms\EcmsServiceProvider).
|
*/

return [

    // Register the public site: the routes `/`, `/search`, `/sitemap`, `/sitemap.xml`, `/{path}`, the
    // language prefix of public addresses and the theme 404 page. Turn it off for a host that uses
    // only the admin panel. The public routes are always registered after every route of the host.
    'public_site' => (bool) env('ECMS_PUBLIC_SITE', true),

    // Register `/login`, `/logout` and the email verification notice, and send guests and signed-in users to
    // them (`/login`, `/admin/dashboard`). Turn it off when the host has its own authentication.
    'auth_routes' => (bool) env('ECMS_AUTH_ROUTES', true),

    // Languages of the admin interface: code => the language's own name. A code needs a `lang/{code}.json`
    // file in the host (`lang_path()`) or in the package. The host may extend or replace the list.
    'locales' => [
        'en' => 'English',
        'uk' => 'Українська',
    ],

];
