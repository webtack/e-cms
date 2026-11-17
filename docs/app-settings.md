# Application settings (`app_settings`)

A general key–value store for the core and future modules: no dedicated settings tables or migrations are needed for each module.

## Key format

A key has the form `module.name`: lowercase Latin letters, digits, `_` and `-`, segments separated by dots, at least two segments, up to 191 characters. An invalid key → `ValidationException` (an error under the key `key`).

Each module uses its own prefix:

| Prefix | Owner |
| --- | --- |
| `content.` | content core (`content.multilingual`: the multilingual switch; `content.base_lang`: the base language code, see [content-languages.md](content-languages.md)) |
| `dashboard.` | the admin dashboard (`dashboard.create_types`: ordered list of type keys of the "Create" menu, see [admin-navigation-menu.md](admin-navigation-menu.md#create-menu)) |
| `site.` | general site settings: name, logos, contacts, social networks, schedule, indexing (see [admin-site-settings.md](admin-site-settings.md)) |
| `seo.`, `mail.`, … | future modules |

## Values

Any JSON-compatible value: a string, number, boolean, array, object, `null`. A stored `null` is different from a missing key (`has`).

## API

The service `Webtack\Ecms\Support\AppSettings` is registered as a singleton; get it from the container (there is no facade or helper).

```php
$settings = app(\Webtack\Ecms\Support\AppSettings::class);

$settings->set('content.multilingual', true);        // create or overwrite
$settings->get('content.multilingual', false);       // true; false if the key does not exist
$settings->has('content.multilingual');              // true, even if null is stored
$settings->prefix('content.');                       // ['content.multilingual' => true]
$settings->forget('content.multilingual');           // delete
```

## Caching

Values are read from the DB once and cached in memory on the instance; `set` and `forget` update this cache, so after a write a read immediately returns the current value. There is no inter-process cache: each request reads the table again. `flush()` resets the in-memory cache. An inter-process cache is a separate decision, to be made when the store becomes "hot".
