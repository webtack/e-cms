# Configuration

Every configuration key and environment variable of `webtack/e-cms`, with its default and meaning. The six files are `config/ecms.php`, `content.php`, `files.php`, `theme.php`, `limits.php` and `social_networks.php`. Run `node scripts/check-config-docs.mjs` in the package: it fails when a key of these files is missing from this page.

## How the host overrides a key

The provider merges each file under its own name (`mergeConfigFrom`), so the package defaults work without publishing anything. Laravel's merge is **shallow**: a top-level key that the host defines replaces the whole package value of that key. For example a host `config/content.php` with `'views' => ['ttl' => 60]` drops `views.enabled`, `views.count_authenticated` and `views.bot_agents`, so repeat the full array when you override a nested one.

To change settings, publish the files and edit them:

```bash
php artisan vendor:publish --tag=ecms-config
```

`ecms:install` does this on the first run. Most scalar settings read an environment variable first; the variables are listed with their keys below and in the [summary table](#environment-variables).

## `ecms.php`

Feature switches and lists read at boot ([package-bootstrap.md](package-bootstrap.md#feature-switches-configecmsphp)).

| Key | Environment | Default | Meaning |
|-----|-------------|---------|---------|
| `ecms.public_site` | `ECMS_PUBLIC_SITE` | `true` | Registers the public routes (`/`, `/search`, `/sitemap`, `/sitemap.xml`, `/{path}`), the language prefix of public addresses and the theme 404 page. Turn it off for a host that uses only the admin panel. |
| `ecms.auth_routes` | `ECMS_AUTH_ROUTES` | `true` | Registers `/login`, `/logout` and the verification notice and the redirects to `/login` and `/admin/dashboard`. Turn it off when the host has its own authentication ([integration.md](integration.md#login)). |
| `ecms.locales` | — | `en` => `English`, `uk` => `Українська` | The admin interface languages, code => the language's own name. A code needs a `lang/{code}.json` file in the host or in the package ([integration.md](integration.md#translations)). The host may extend or replace the list. |

## `content.php`

The content model, querying, the public site and rich text.

| Key | Environment | Default | Meaning |
|-----|-------------|---------|---------|
| `content.kinds` | — | `content`, `page`, `block` | Allowed values of a type's `kind` ([content-model.md](content-model.md)). |
| `content.field_types` | — | the 15 built-in types | Field type key => class implementing `FieldType`. A new value type is one class, one `content_values_{key}` table and one line here ([content-values.md](content-values.md)). |
| `content.default_lang` | — | `default` | The `lang` value of base (untranslated) values. Not meant to change. |
| `content.base_lang_default` | `APP_LOCALE` | `en` | The code the base language reads as until a base language is saved in the admin ([content-languages.md](content-languages.md)). |
| `content.reserved_paths` | — | `admin`, `login`, `logout`, `locale`, `email`, `up`, `storage`, `build`, `app`, `themes`, `images`, `search`, `sitemap`, `sitemap.xml` | First URL segments owned by the application. A page slug or a type slug may not equal one ([content-model.md](content-model.md#reserved-paths)). |
| `content.search` | — | see below | Limits of the public search term. |
| `content.search.min_length` | — | `3` | A shorter term shows the form without querying. |
| `content.search.max_length` | — | `100` | A longer term is cut. |
| `content.max_filters` | — | `10` | The most `EXISTS` conditions in one node query ([content-querying.md](content-querying.md)). |
| `content.meta_description_max` | — | `160` | The longest meta description taken from a field, in characters, with the trailing `…`. |
| `content.per_page` | — | `15` | Default page size of node lists. |
| `content.max_per_page` | — | `100` | The largest page size a request may ask for. |
| `content.max_all` | — | `1000` | The ceiling of the "all nodes" mode (`per_page` = `0`). |
| `content.views` | — | see below | The node view counter. |
| `content.views.enabled` | `CONTENT_VIEWS_ENABLED` | `true` | Master switch of the counter. Turn it off in development or behind a full-page cache that hides visits. |
| `content.views.ttl` | `CONTENT_VIEWS_TTL` | `0` | Seconds during which the same visitor (IP and User-Agent) is counted once per node. `0` counts every visit. A positive value keeps the dedup key in the cache store, which must persist between requests. |
| `content.views.count_authenticated` | `CONTENT_VIEWS_COUNT_AUTHENTICATED` | `true` | Count the visits of signed-in users. |
| `content.views.bot_agents` | — | `bot`, `crawl`, `spider`, `slurp`, `facebookexternalhit`, `preview`, `headless`, `curl`, `wget`, `python-requests`, `monitor` | Case-insensitive User-Agent substrings that mark a bot; an empty User-Agent is a bot too. Bots are not counted. |
| `content.richtext` | — | see below | Rich text sanitizing. |
| `content.richtext.max_input_length` | — | `1000000` | The longest HTML the sanitizer reads, in characters. The library cuts longer input at 20 000, which would silently drop the end of a long text. |
| `content.richtext.iframe_hosts` | — | YouTube, Vimeo, Google Drive/Docs/Maps, Facebook, Instagram, Twitter, TikTok, Telegram | Where an `iframe` in a rich text value may point (https only). An entry is a host, optionally with a path prefix. The list is shared with the editor. |
| `content.richtext.iframe_allow` | — | `accelerometer`, `autoplay`, `clipboard-write`, `encrypted-media`, `fullscreen`, `gyroscope`, `picture-in-picture`, `web-share` | Permissions Policy features an `iframe` `allow` attribute may keep; the rest (camera, microphone, …) is dropped. |

## `files.php`

Uploads, image processing and image styles ([file-storage.md](file-storage.md), [image-styles.md](image-styles.md)).

### Upload profiles

An upload profile is set by the developer, never by the user. Each profile has a `disk` (a key of `config/filesystems.php`), `mimes` (allowed types detected from the content, `mime => stored extension`), `max_size` in kilobytes and, for images, `max_width` and `max_height` in pixels (a larger image is scaled down proportionally; `null` lifts the limit). Executables and SVG are always rejected.

| Key | Environment | Default | Meaning |
|-----|-------------|---------|---------|
| `files.profiles` | — | `image`, `avatar`, `document` | The profiles. |
| `files.profiles.image` | — | see below | General images. |
| `files.profiles.image.disk` | — | `public` | Disk of the files. |
| `files.profiles.image.mimes` | — | JPEG, PNG, WebP, GIF | Allowed types. |
| `files.profiles.image.max_size` | `FILES_IMAGE_MAX_SIZE` | `30720` (30 MB) | Size limit in KB. |
| `files.profiles.image.max_width` | `FILES_IMAGE_MAX_WIDTH` | `1920` | Width limit in pixels; `null` lifts it. |
| `files.profiles.image.max_height` | `FILES_IMAGE_MAX_HEIGHT` | `1920` | Height limit in pixels; `null` lifts it. |
| `files.profiles.avatar` | — | see below | User pictures. |
| `files.profiles.avatar.disk` | — | `public` | Disk of the files. |
| `files.profiles.avatar.mimes` | — | JPEG, PNG, WebP | Allowed types (no GIF). |
| `files.profiles.avatar.max_size` | `FILES_AVATAR_MAX_SIZE` | `2048` (2 MB) | Size limit in KB. |
| `files.profiles.avatar.max_width` | `FILES_AVATAR_MAX_WIDTH` | `512` | Width limit in pixels. |
| `files.profiles.avatar.max_height` | `FILES_AVATAR_MAX_HEIGHT` | `512` | Height limit in pixels. |
| `files.profiles.document` | — | see below | Documents. |
| `files.profiles.document.disk` | — | `public` | Disk of the files. |
| `files.profiles.document.mimes` | — | PDF, DOC, DOCX, XLSX | Allowed types. |
| `files.profiles.document.max_size` | `FILES_DOCUMENT_MAX_SIZE` | `30720` (30 MB) | Size limit in KB. |

### Image processing

| Key | Environment | Default | Meaning |
|-----|-------------|---------|---------|
| `files.image` | — | see below | Settings of `ImageResizer`. |
| `files.image.driver` | `FILES_IMAGE_DRIVER` | `imagick` | The Intervention Image driver, `imagick` or `gd`. |
| `files.image.quality` | — | see below | Quality of re-encoded images. |
| `files.image.quality.jpeg` | `FILES_IMAGE_JPEG_QUALITY` | `85` | JPEG quality, 1–100. |
| `files.image.quality.webp` | `FILES_IMAGE_WEBP_QUALITY` | `85` | WebP quality, 1–100. PNG is lossless. |
| `files.image.max_pixels` | `FILES_IMAGE_MAX_PIXELS` | `100000000` | Width × height above which an image is rejected before it is decoded, so a small compressed file cannot exhaust the memory. |

### Image styles

A style is a preset or an arbitrary size such as `300x250`, `300x` or `x300`.

| Key | Environment | Default | Meaning |
|-----|-------------|---------|---------|
| `files.styles` | — | see below | Derived image sizes. |
| `files.styles.presets` | — | `thumb` | `name => [width, height, upload]`. A name must not look like a size. A preset with `upload` set to `true` is created when the image is stored, the others on the first request. |
| `files.styles.max_dimension` | `FILES_STYLE_MAX_DIMENSION` | `4096` | The largest side of an arbitrary size, in pixels. |
| `files.styles.blur` | `FILES_STYLE_BLUR` | `30` | Blur strength of the background that fills the margins of a two-sided style. |
| `files.styles.format` | `FILES_STYLE_FORMAT` | `webp` | `webp` (JPEG, PNG and WebP become WebP, GIF stays GIF) or `original`. |

The `thumb` preset reads `FILES_STYLE_THUMB_WIDTH` and `FILES_STYLE_THUMB_HEIGHT` (both `200`, created on upload).

### Relation owners

| Key | Environment | Default | Meaning |
|-----|-------------|---------|---------|
| `files.owners` | — | nodes, types, taxonomy terms, menu items, site settings, users | `media_relations.owner_type` => class implementing `MediaOwner`. The media library asks it for a label and an edit link; an unlisted owner type is shown as `type #id` ([media-library.md](media-library.md#owner-registry)). |

## `theme.php`

The public site theme ([site-themes.md](site-themes.md#active-theme)).

| Key | Environment | Default | Meaning |
|-----|-------------|---------|---------|
| `theme.active` | `APP_THEME` | `default` | The directory name of the active theme. It is checked at the first public request, so a wrong value never breaks the admin or the console. |
| `theme.path` | — | `base_path('themes')` | The host's themes directory. The package's own `themes/` is added by the provider. |
| `theme.fallback` | — | `default` | Always the last source of templates. Required; not meant to change. |

## Request limits (`limits.php`)

Rate limits for the endpoints that cost real resources. A `*_per_minute` value is the number of requests per minute, per IP address for guests and per user id for signed-in users. Behind a reverse proxy configure trusted proxies, otherwise every visitor shares one IP ([installation.md](installation.md#production-notes)).

| Key | Environment | Default | Meaning |
|-----|-------------|---------|---------|
| `limits.search_per_minute` | `LIMIT_SEARCH_PER_MINUTE` | `60` | Public site search (`throttle:search`), per IP. |
| `limits.upload_per_minute` | `LIMIT_UPLOAD_PER_MINUTE` | `120` | `POST /admin/files` (`throttle:upload`), per user. |
| `limits.lookup_per_minute` | `LIMIT_LOOKUP_PER_MINUTE` | `120` | The autocomplete endpoints `GET /admin/lookup/*` (`throttle:lookup`), per user. |
| `limits.youtube_per_minute` | `LIMIT_YOUTUBE_PER_MINUTE` | `20` | YouTube lookup (`throttle:youtube`), per user; lower because a call may spend the external API quota. |
| `limits.login_ip_attempts` | `LIMIT_LOGIN_IP_ATTEMPTS` | `20` | Failed logins from one IP address across all emails allowed within the window. |
| `limits.login_ip_window` | `LIMIT_LOGIN_IP_WINDOW` | `300` | The window of `login_ip_attempts`, in seconds. |

## `social_networks.php`

`social_networks` is the fixed list of networks a site can link to (`site.socials` in [site settings](admin-site-settings.md)): `key => ['name' => …]`. The key is stored with the link, the name is shown in the admin select and the icon is picked by the frontend from the key. The default list has 23 networks, from `facebook` to `vk`. A new network is added in this file, not from the admin.

## Environment variables

| Variable | Used by | Default |
|----------|---------|---------|
| `APP_LOCALE` | `content.base_lang_default` | `en` |
| `APP_THEME` | `theme.active` | `default` |
| `ECMS_PUBLIC_SITE` | `ecms.public_site` | `true` |
| `ECMS_AUTH_ROUTES` | `ecms.auth_routes` | `true` |
| `CONTENT_VIEWS_ENABLED` | `content.views.enabled` | `true` |
| `CONTENT_VIEWS_TTL` | `content.views.ttl` | `0` |
| `CONTENT_VIEWS_COUNT_AUTHENTICATED` | `content.views.count_authenticated` | `true` |
| `FILES_IMAGE_MAX_SIZE` | `files.profiles.image.max_size` | `30720` |
| `FILES_IMAGE_MAX_WIDTH` | `files.profiles.image.max_width` | `1920` |
| `FILES_IMAGE_MAX_HEIGHT` | `files.profiles.image.max_height` | `1920` |
| `FILES_AVATAR_MAX_SIZE` | `files.profiles.avatar.max_size` | `2048` |
| `FILES_AVATAR_MAX_WIDTH` | `files.profiles.avatar.max_width` | `512` |
| `FILES_AVATAR_MAX_HEIGHT` | `files.profiles.avatar.max_height` | `512` |
| `FILES_DOCUMENT_MAX_SIZE` | `files.profiles.document.max_size` | `30720` |
| `FILES_IMAGE_DRIVER` | `files.image.driver` | `imagick` |
| `FILES_IMAGE_JPEG_QUALITY` | `files.image.quality.jpeg` | `85` |
| `FILES_IMAGE_WEBP_QUALITY` | `files.image.quality.webp` | `85` |
| `FILES_IMAGE_MAX_PIXELS` | `files.image.max_pixels` | `100000000` |
| `FILES_STYLE_THUMB_WIDTH` / `FILES_STYLE_THUMB_HEIGHT` | `files.styles.presets` (`thumb`) | `200` / `200` |
| `FILES_STYLE_MAX_DIMENSION` | `files.styles.max_dimension` | `4096` |
| `FILES_STYLE_BLUR` | `files.styles.blur` | `30` |
| `FILES_STYLE_FORMAT` | `files.styles.format` | `webp` |
| `LIMIT_SEARCH_PER_MINUTE` | `limits.search_per_minute` | `60` |
| `LIMIT_UPLOAD_PER_MINUTE` | `limits.upload_per_minute` | `120` |
| `LIMIT_LOOKUP_PER_MINUTE` | `limits.lookup_per_minute` | `120` |
| `LIMIT_YOUTUBE_PER_MINUTE` | `limits.youtube_per_minute` | `20` |
| `LIMIT_LOGIN_IP_ATTEMPTS` | `limits.login_ip_attempts` | `20` |
| `LIMIT_LOGIN_IP_WINDOW` | `limits.login_ip_window` | `300` |
| `ADMIN_EMAIL`, `ADMIN_PASSWORD` | the installer and `AdminSeeder` | none (never defaulted) |
| `ECMS_HOT_FILE` | the package dev server only ([package-frontend.md](package-frontend.md#development-with-hot-reload)) | `dist/hot` |
| `THEME_DEV_PORT` | the theme dev server ([site-themes.md](site-themes.md#commands-on-the-host)) | `5174` |
| `THEME` | the theme build ([theming.md](theming.md#building-the-assets)) | set by `scripts/theme.mjs` |
