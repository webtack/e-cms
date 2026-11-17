<?php

use Webtack\Ecms\Theme\ThemeHooks;

/**
 * Hooks of the `default` theme; loaded for every theme, before the hooks of the active one.
 *
 * @example
 *   $hooks->preprocess('field.youtube', function (array &$vars) { $vars['autoplay'] = false; });
 *   $hooks->suggest('page', function (array &$candidates) { array_unshift($candidates, 'pages/page-special'); });
 */
return function (ThemeHooks $hooks): void {
    //
};
