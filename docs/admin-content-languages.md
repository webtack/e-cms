# Content languages page

The admin page `/admin/settings/languages` manages the languages of **content**: the multilingual switch, the base language code, and the list of additional languages. It never changes the language of the admin interface ([admin-i18n.md](admin-i18n.md)). The domain rules (validity, normalization, deletion guard) live in [content-languages.md](content-languages.md); this page only exposes them.

It is a sub-page of [Site settings](admin-site-settings.md) (linked from it; the sidebar keeps "Site" highlighted) and needs the `settings` permission: a user without it gets 403 for viewing and for every change, a guest is redirected to `/login`.

## Routes

All routes are in the `can:settings` group (`routes/web.php`), controller `Webtack\Ecms\Http\Controllers\Admin\ContentLanguageController`. A language in the URL is its normalized `code` (`Route::bind('language', …)`; an unknown code answers 404).

| Method and path | Name | Action |
| --- | --- | --- |
| `GET /admin/settings/languages` | `admin.settings.languages.index` | the page |
| `PUT /admin/settings/languages` | `admin.settings.languages.settings` | the switch and the base code |
| `POST /admin/settings/languages` | `admin.settings.languages.store` | add a language |
| `PUT /admin/settings/languages/{language}` | `admin.settings.languages.update` | name, activity, weight |
| `DELETE /admin/settings/languages/{language}` | `admin.settings.languages.destroy` | delete |

## Page response (`Admin/Settings/Languages`)

| Prop | Value |
| --- | --- |
| `multilingual` | `bool`, the switch |
| `baseLang` | the effective base code: the saved one, or the configured default `content.base_lang_default` |
| `savedBaseLang` | the saved code or `null` (the page shows the effective value as unsaved) |
| `languages` | `[{code, name, is_active, weight}]` ordered by weight, then creation |
| `limits` | `{code: 16, name: 255}` |

## Validation

- **Switch and base code** (`UpdateContentLanguageSettingsRequest`): `multilingual` is a boolean; `base_lang` is optional, normalized (`UK` → `uk`), in the language code format, not `default`. An empty `base_lang` keeps the saved code. The code is saved first, then the switch, in one transaction: a rejection of either rolls back both. Errors: `base_lang` for a malformed code or a code of an existing additional language; `multilingual` when turning the switch on without a saved code.
- **Add** (`StoreContentLanguageRequest`): `code` is required, normalized, in the code format, not `default`, unique, not equal to the saved base code; `name` is required, up to 255 characters; `is_active` is optional and false when absent. Errors appear under `code` and `name`.
- **Edit** (`UpdateContentLanguageRequest`): `name` (required, up to 255), `is_active` (required boolean), `weight` (integer, at least 0). The code is not editable.
- **Delete**: rejected with an error under `language` while any translation exists (content values, term translations, menu item translations, and every registered [language usage](content-languages.md#language-usages)); the message suggests deactivating the language instead. Deactivation keeps all translations.

## Messages

Success messages go to the `success` flash: "Languages saved.", "Language added.", "Language saved.", "Language deleted." They and the error texts are translated in `lang/en.json` and `lang/uk.json`.

## Tests

PHP: `tests/Feature/Admin/ContentLanguageAdminTest.php`. Browser: `e2e/admin-content-languages.spec.js` ([e2e-testing.md](e2e-testing.md)).
