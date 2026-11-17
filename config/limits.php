<?php

/*
|--------------------------------------------------------------------------
| Request limits
|--------------------------------------------------------------------------
|
| Rate limits for the endpoints that cost real resources. Each `*_per_minute` value is the number of
| requests allowed per minute (per IP address for guests, per user id for signed-in users); the
| named limiters are registered in Webtack\Ecms\EcmsServiceProvider and used as `throttle:<name>`.
| Behind a reverse proxy or CDN, configure trusted proxies, otherwise every visitor shares one IP.
|
*/

return [

    // Public site search (`throttle:search`), per IP address.
    'search_per_minute' => (int) env('LIMIT_SEARCH_PER_MINUTE', 60),

    // File upload, `POST /admin/files` (`throttle:upload`), per user.
    'upload_per_minute' => (int) env('LIMIT_UPLOAD_PER_MINUTE', 120),

    // Reference autocomplete endpoints, `GET /admin/lookup/*` (`throttle:lookup`), per user.
    'lookup_per_minute' => (int) env('LIMIT_LOOKUP_PER_MINUTE', 120),

    // YouTube lookup (`throttle:youtube`), per user; lower because each call may spend the external API quota.
    'youtube_per_minute' => (int) env('LIMIT_YOUTUBE_PER_MINUTE', 20),

    // Failed logins from one IP address across all emails: the number allowed within the window (seconds).
    'login_ip_attempts' => (int) env('LIMIT_LOGIN_IP_ATTEMPTS', 20),
    'login_ip_window' => (int) env('LIMIT_LOGIN_IP_WINDOW', 300),

];
