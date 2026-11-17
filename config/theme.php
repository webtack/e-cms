<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Active theme
    |--------------------------------------------------------------------------
    |
    | Name of the directory in `path` that holds the public site theme
    | (`themes/<name>/{theme.json, hooks.php, assets, views}`). The theme is
    | checked on the first public request, so a wrong value never breaks the
    | admin or console commands.
    |
    */

    'active' => env('APP_THEME', 'default'),

    /*
    |--------------------------------------------------------------------------
    | Themes directory
    |--------------------------------------------------------------------------
    */

    'path' => base_path('themes'),

    /*
    |--------------------------------------------------------------------------
    | Fallback theme
    |--------------------------------------------------------------------------
    |
    | Always the last source of templates, so another theme holds only what it
    | overrides. Required; not meant to be changed.
    |
    */

    'fallback' => 'default',

];
