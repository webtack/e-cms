# Content languages

Content languages are the languages of field **values** (see [content-values.md](content-values.md)). They are not tied to the language of the admin interface ([admin-i18n.md](admin-i18n.md)): neither affects the other (`users.locale`, the locale middleware, and `lang/*` do not change).

The admin page that manages them is described in [admin-content-languages.md](admin-content-languages.md).

## List of languages (`app_languages`)

Model `Webtack\Ecms\Models\AppLanguage`: `code`, `name`, `is_active`, `weight`.

- A new language is **inactive** (`is_active = false`) until it is explicitly activated.
- The code has the BCP-47 format (`^[a-z]{2,3}(-[A-Za-z0-9]{2,8})*$`), up to 16 characters; `default` is reserved for base values and cannot be added as a language.
- The code is stored only in normalized form: the language in lowercase, a 4-letter script in title case, a 2-letter region in uppercase (`PT-br` → `pt-BR`). `AppLanguage::normalize()` is applied both to codes and to `lang` when writing, reading, and querying values, so `pt-br` and `pt-BR` are one language. The columns `app_languages.code` and `content_values_*.lang` have a binary collation, so that MySQL and SQLite compare them the same way.
- The code cannot equal the saved [base language code](#base-language-code) (`ValidationException` under `code`).
- Deactivation keeps the language values. A language for which **any translation exists** (content values, term translations, menu item translations, or the translations of any feature that registers a [usage checker](#language-usages)) **cannot be deleted** (`ValidationException` under `code`).

## Multilingual switch

The single global switch is stored in [`app_settings`](app-settings.md) under the key `content.multilingual` and is disabled by default. It is managed by the service `Webtack\Ecms\Content\ContentLanguages`:

```php
$languages = app(\Webtack\Ecms\Content\ContentLanguages::class);

$languages->isMultilingual();        // false by default
$languages->setMultilingual(true);   // saves content.multilingual
$languages->active();                // ['uk', 'en'] — active languages by weight; [] when disabled
```

Disabling and re-enabling do not change or delete saved translations. Turning the switch on needs a saved base language code: otherwise `setMultilingual(true)` throws a `ValidationException` under `multilingual`.

## Base language code

The code of the language in which the `default` values are written is stored in `app_settings` under `content.base_lang` (see [app-settings.md](app-settings.md)). It names the base language for the outside world (`<html lang>`, `hreflang`, URL prefixes); it never changes the `default` storage key or any saved value.

```php
$languages->setBaseLang('UK');    // 'uk' (normalised); ValidationException under `base_lang` for a malformed, reserved or additional-language code
$languages->savedBaseLang();      // 'uk', or null while nothing is saved
$languages->baseLang();           // the saved code, or config('content.base_lang_default') while nothing is saved
```

While nothing is saved, the base language reads as `config('content.base_lang_default')` (`env('APP_LOCALE', 'en')`). `config('app.locale')` is not used: the `SetLocale` middleware overwrites it on every request with the interface locale of the user.

The code never equals an additional language: `setBaseLang()` rejects a code listed in `app_languages`, and `AppLanguage` rejects a code equal to the saved base code.

## Language usages

Before a language is deleted, `AppLanguage::deleting` asks `ContentLanguages::isInUse($lang)`, which asks every checker tagged `content.language-usages` (`ContentLanguages::USAGE_TAG`). A checker implements `Webtack\Ecms\Content\LanguageUsage::exists(string $lang): bool`. Registered checkers:

| Checker | Stored translations |
| --- | --- |
| `ContentValuesUsage` | rows of every `content_values_*` table |
| `TermTranslationsUsage` | `taxonomy_term_translations` |
| `MenuItemTranslationsUsage` | `menu_item_translations` |

A feature that stores translations keyed by a language code registers its checker in a service provider, otherwise deleting a language would orphan its data:

```php
$this->app->tag([LabelTranslationsUsage::class], ContentLanguages::USAGE_TAG);
```

## Language validity rules

| Action | `default` | Another language |
| --- | --- | --- |
| Writing values (`assertWritable`) | always | the switch is on + the language is in `app_languages` + `is_active` |
| Reading / querying (`assertReadable`) | always | the switch is on + the language is in `app_languages` (active or not) |

A violation → `ValidationException` with an error under the key `lang`. Both methods return the normalized code.

Additionally, writing to another language is possible only for fields with `is_translatable`, see [content-values.md](content-values.md#translations).

## Public routing and fallback

The public site serves each **active** additional language under its code as the first path segment (`/en/about-us`); the base language is unprefixed and `/{base}/...` redirects (`301`) to the unprefixed address. The prefix is resolved by `ResolvePublicLanguage` before routing and the language is taken from the address only. Details, the table of addresses and the interface language rules: [public-site.md](public-site.md#language-prefix).

- **Reading language:** `Webtack\Ecms\Content\LocalizedUrl::lang()` is `default` for the base language and the code of an additional one. `PresentContext` carries it with the `fallback` flag (`readIn($lang, fallback: true)`).
- **Fallback:** the public reading replaces every value missing in the reading language by the base-language value of the same field; the fallback is an explicit mode of the readers (`ContentValueReader`, `ContentNodeQuery`, `MenuTree`, `TermTree`, `FileDescriptions::resolve()`, `SiteSettings::forLang()`), off by default ([content-values.md](content-values.md#reading-with-fallback)).
- **Reserved codes:** the codes of all content languages, active or not, and the saved base code are reserved first segments; no page slug or `content` type first segment may equal one, and no language code may equal a reserved path, a page slug or a type first segment ([content-model.md](content-model.md#language-codes)).
- **Cache:** the list of active languages is cached (`PublicLanguages`) and dropped when a language is saved or deleted and when the switch or the base code changes.
