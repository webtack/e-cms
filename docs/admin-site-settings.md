# Site settings

General information about the site: name, logos, contacts, social networks, working hours, and the indexing flag. They are stored in [`app_settings`](app-settings.md) under the prefix `site.`, edited on the `/admin/settings` page, and read by templates via `Webtack\Ecms\Settings\SiteSettings`.

The content languages (the multilingual switch, the base language and additional languages) are managed on the sub-page [`/admin/settings/languages`](admin-content-languages.md), linked from this page.

## Data format

Eleven keys, no separate migrations. All translatable values are stored under a language code; the base language is `default`; the admin panel shows and edits only it.

| Key | Value |
| --- | --- |
| `site.name`, `site.short_name`, `site.description`, `site.address` | `{"default": "My site"}`; the description and the address are multiline text |
| `site.logo_large`, `site.logo_small` | `{"default": "{uuid}.{ext}"}` — a file of the `image` profile |
| `site.emails`, `site.phones` | `[{"value": "a@b.c", "label": {"default": "General"}}]` |
| `site.socials` | `[{"network": "instagram", "url": "https://…", "label": {"default": "…"}}]` |
| `site.schedule` | `{"default": [["Mon–Fri", "9:00", "18:00", "lunch 13–14"]]}` — rows of 4 columns |
| `site.indexable` | `true`/`false`; without a value it is read as `false` |

- An empty value is not stored: an empty name, logo, or schedule removes the `default` key, and when no languages remain, the whole setting is deleted. An empty `label` — a missing key. An empty list — a missing setting.
- `value`, `network`, `url`, and `indexable` do not depend on the language and are stored without a language key.
- Translations live next to `default` under the language code (`{"default": "Мій сайт", "en": "My site"}`; a list label is `{"default": "…", "en": "…"}`). Only the name, short name, description, address, schedule and list labels are translatable; the logos stay base-only. A value equal to `default` (trimmed; the schedule as a whole table) is not stored.
- The logo is stored as `{uuid}.{ext}`, like other file values of the project ([file-storage.md](file-storage.md)).

## Reader and writer

`Webtack\Ecms\Settings\SiteSettings` (a singleton over `AppSettings`):

```php
$site = app(\Webtack\Ecms\Settings\SiteSettings::class);

$site->name();          // 'My site'; the language is the current locale, fallback to default
$site->name('en');      // the en value or default
$site->address();       // "1 Main St\nKyiv" or null
$site->phones();        // [['value' => '+380 44 000', 'label' => 'Sales'|null]]
$site->socials();       // [['network' => 'instagram', 'url' => '…', 'label' => null]]
$site->schedule();      // [['Mon–Fri', '9:00', '18:00', '']]
$site->indexable();     // false if not saved
$site->logoLarge();     // '{uuid}.{ext}' or null

$site->formValues();    // flat default values for the page form
$site->save($data);     // writes the whole set in one transaction
```

One additional language is read and written with these methods (the language must be an active additional one, otherwise a `ValidationException` under `lang`):

```php
$site->translationValues('en');   // the translation form: stored translation or the base value for each field
$site->saveTranslation('en', [    // only the keys present are written
    'name' => 'My site',
    'phones' => [['value' => '+380 44 000', 'label' => 'Sales']],   // matched by identity: value; network + url for socials
    'schedule' => [['Mon', '9', '18', '']],                          // saved and compared as a whole table
]);
$site->clearTranslation('en');    // removes the language from every translatable setting
$site->hasTranslation('en');      // used by the language usage registry (SiteSettingsUsage)
```

`saveTranslation()` removes a value that is blank or equal to `default`, ignores list rows that match no stored item (rows, their values and order are never changed), and leaves other languages, `default`, the logos and `indexable` untouched. A language with any stored translated setting cannot be deleted.

`save()` writes only the `site.*` keys present in the passed array, so it does not touch foreign prefixes. The `default` value is merged with the stored object: other languages are not overwritten (the label of a list item keeps other languages if the same item — by `value` or `network`+`url` — already existed). If the transaction is rolled back, `save()` resets the `AppSettings` cache and rethrows the exception.

## Logos

Site settings are a single entity without a row in the DB, so the use of logos is tracked in [`media_relations`](media-library.md) with the owner `site_setting` and `owner_id = 0` (real `id`s start from 1). The role is `logo_large` or `logo_small`, the language is `default`. `SiteSettings::save()` calls `FileRelations::sync` in the same transaction as the values write, so a logo in use cannot be deleted from the media library; clearing or replacing removes the relation, and the file remains. `Webtack\Ecms\Files\Owners\SiteSettingOwner` shows in the media library the label "Site settings" with a link to `/admin/settings`.

## Social networks directory

`config/social_networks.php`: `key => ['name' => 'Facebook']`. The list is fixed; a new network is added by editing the configuration; the frontend takes the icon by key. One network may repeat.

## Routes and access

| Method and address | Name | Purpose |
| --- | --- | --- |
| `GET /admin/settings` | `admin.settings.edit` | Form with the current values |
| `PUT /admin/settings` | `admin.settings.update` | Saves the whole set, redirects back with the message "Site settings saved." |

Both routes are in the group `['auth', 'verified']` and under `can:settings` ([access-control.md](access-control.md)): a guest goes to `/login`, a role without the `settings` right gets 403. The controller is `Webtack\Ecms\Http\Controllers\Admin\SiteSettingsController`.

Props of the `Admin/Settings/Site` page:

| Prop | Content |
| --- | --- |
| `values` | Flat values of the `default` language (`SiteSettings::formValues()`): `name`, `short_name`, `description`, `address`, `logo_large`, `logo_small`, `emails`, `phones`, `socials` (rows with `label` — an empty string when there is no label), `schedule`, `indexable` |
| `logoFiles` | Description of the saved logos for preview (`uuid`, `name`, `description`, `mime`, `size`, `url`) or `null` |
| `networkOptions` | `[{value, label}]` from `config/social_networks.php`; a saved network that is no longer in the directory is appended to the end, so editing does not lose it |
| `limits` | Limits for client-side hints: `name`, `short_name`, `description`, `address`, `list`, `text` |

## Validation

`UpdateSiteSettingsRequest` (limits are in the constant `LIMITS`; they can be changed without changing the data structure):

| Field | Rules |
| --- | --- |
| `name` / `short_name` / `description` | string, up to 255 / up to 100 / up to 2000 characters |
| `address` | string, up to 500 characters |
| `logo_large`, `logo_small` | `{uuid}.{ext}` of an existing file of the `image` profile (`FileValue`), up to 255 characters |
| `emails`, `phones`, `socials` | array of up to 20 entries |
| `emails.*.value` | required, `email`, up to 255 |
| `phones.*.value` | required, `^\+?[0-9\s()\-]{3,32}$` |
| `socials.*.network` | required, a key of the directory |
| `socials.*.url` | required, absolute `http`/`https`, up to 2048 |
| `*.label` | optional string up to 255 |
| `schedule` | up to 20 rows; each has exactly 4 columns (a string up to 255 or empty); a row with four empty columns is discarded before validation |
| `indexable` | boolean |

Errors are returned under the field keys: `emails.1.value`, `socials.0.url`, `schedule.2.1`, `emails` (for the list limit).

## Translation mode

`GET /admin/settings?lang=xx` (an active additional language; the base code is the base mode, anything else 404) follows the [translation mode contract](admin-translation-mode.md). The page keeps the layout of the base form. Editable and prefilled with the stored translation or the base value: the name, short name, description, address, the working hours schedule (a whole table; rows may be added and removed because the table is saved as a whole) and the labels of emails, phones and social networks. The logos, the indexing switch and the identity of every contact row (the address or number; the network and the link) stay in place, inert and dimmed, with their base values; email, phone and social rows cannot be added, removed or reordered (`SortableList` with `fixed`). "Save" is disabled until a value changes; "Clear translation" (shown when any translated setting exists) is in the action bar.

| Request | Name | Purpose |
| --- | --- | --- |
| `GET /admin/settings?lang=xx` | `admin.settings.edit` | The form in the translation mode: `values` stay the base values, `translation.values` holds the prefilled translatable ones (`SiteSettings::translationValues()`) |
| `PUT /admin/settings/translations/{lang}` | `admin.settings.translations.save` | Saves one language through `SiteSettings::saveTranslation()` (`SaveSiteSettingsTranslationRequest`: the texts, `schedule` and the list rows with their `label`); redirects to `?lang=xx` with "Translation saved." |
| `DELETE /admin/settings/translations/{lang}` | `admin.settings.translations.clear` | `SiteSettings::clearTranslation()`; same redirect with "Translation cleared." |

All three sit under `can:settings` (403 without the right). A list row of the request is matched to a stored item by its identity (`value`; `network` and `url`); a row that matches nothing is ignored, so a stale form cannot recreate a removed row. The "translated" marker of a language is `SiteSettings::hasTranslation()`.

## Saving

The controller calls `SiteSettings::save($request->validated())`: all keys and logo relations are written in one transaction, so on a validation or write error no value changes. Repeated saving of unchanged values does not change data; values of languages other than `default` and foreign prefixes of `app_settings` are not overwritten.

## Interface

The page `resources/js/Pages/Admin/Settings/Site.tsx` is one form with the blocks "General" (name, short name, an optional description in a `textarea`), "Logos", "Contacts" (email addresses and phones with an optional label, and an optional address in a `textarea`), "Social networks" (a network from the directory, a link, a label), "Schedule" (rows of four text columns), and "Indexing" (a toggle, disabled by default; in the admin panel it changes nothing). At the bottom — the sticky `FormActionBar`. In the base mode the form shows and edits only the `default` language. When multilingual mode is on and an additional language is active, the language switcher is shown above the form and the same form works as the [translation mode](#translation-mode).

The four lists use the shared `Components/Admin/Settings/SortableList.tsx` built on `@dnd-kit`: an "Add" button, a delete button for a row, a handle for dragging with the mouse or the keyboard (space — pick up, arrows — move, space — drop). Each row has a temporary client `id` for stable keys; it is not sent to the server. `data-testid`: the list `{key}` (`emails`, `phones`, `socials`, `schedule`), the row `{key}-item`, the handle `{key}-handle`, the removal `{key}-remove`, the addition `{key}-add`. With the `fixed` prop (the translation mode of the contact and social lists) the handle, the removal and the addition are not drawn.

Logos are selected by the `FileUpload` widget with the `image` profile (`file-upload-logo_large`, `file-upload-logo_small`); clearing removes the relation, and the file stays in the media library. Tests: Feature — `tests/Feature/Admin/SiteSettings*Test.php`, `tests/Feature/Settings/SiteSetting*Test.php`.
