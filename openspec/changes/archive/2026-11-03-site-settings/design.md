# Design

## Context

- `app_settings` (`App\Support\AppSettings`) can already store JSON values under the key `модуль.назва` ("module.name"), but it caches in the memory of the instance and has no transactional guarantees (`set` does an `upsert` without its own transaction). Description — `docs/app-settings.md`.
- The system right `settings` exists (`AccessResolver::SYSTEM`, `can:settings`); the item Settings in `navigation.ts` is a mock-up (`mock: true`, `/admin/settings`).
- The file links are kept via `FileRelations::sync(ownerType, ownerId, role, lang, uuids)`; the type of the owner is registered in `config/files.php` (`owners`) by the class `MediaOwner`. `owner_id` is `unsigned bigint`, without FK. The image of a menu item is the sample: the value `{uuid}.{ext}`, the check by the rule `FileValue('image', …)`, the link in the same transaction (`MenuItemWriter`), on the form `FileUpload profile="image"` ([media-library.md](../../../docs/media-library.md), [file-storage.md](../../../docs/file-storage.md)).
- Drag-and-drop: `@dnd-kit/core` and `@dnd-kit/sortable` are already used (`NodeFieldList.tsx`, `ContentFields/Index.tsx`).
- Languages: the base language is `default`; the language UI is not done in this change. See proposal.md.

## Goals / Non-Goals

**Goals:**
- One page that saves the whole set of `site.*` atomically.
- A data format in which adding languages does not require a migration of the values.
- One reader for the templates with a fallback to `default`.
- The accounting of the logos in `media_relations` without a new table.

**Non-Goals:**
- The language UI (a switcher, translation of the fields), output of `meta robots`/`X-Robots-Tag`/`robots.txt`.
- An inter-process cache of the settings, the history of changes, versioning.
- A change of `AppSettings` (its API stays as it is).

## Decisions

### 1. Storage in `app_settings`, 9 keys `site.*`
There is no separate table and no migration. The alternative — a «pseudo-type» on the engine of fields (`content_values_*`) — was rejected in the explore: it drags `ContentType` and `SystemFields` for an entity that is not a type, and there is no need for dynamic fields. The rows are stored with the language key even when there is one language.

```
site.name        {"default": "Мій сайт"}
site.short_name  {"default": "Сайт"}
site.logo_large  {"default": "0190….png"}
site.logo_small  {"default": "0190….png"}
site.emails      [{"value": "a@b.c", "label": {"default": "Загальні"}}]
site.phones      [{"value": "+380 44 000", "label": {"default": "Відділ продажів"}}]
site.socials     [{"network": "instagram", "url": "https://…", "label": {"default": "…"}}]
site.schedule    {"default": [["Пн–Пт", "9:00", "18:00", "обід 13–14"], ["Сб", "10:00", "14:00", ""]]}
site.indexable   false
```

The logo is stored as `{uuid}.{ext}` (and not as a bare `uuid`), because file values are stored in this format throughout the project and `FileStorage::urlForValue` builds the URL exactly from this format.

An empty value: an empty name/logo/schedule — the key `default` is removed, and if no translations remain — the setting is `forget`. An empty `label` — the key is absent. Empty lists are stored as an absent setting.

### 2. The class `App\Settings\SiteSettings`: the reader and the writer in one
A singleton over `AppSettings`. Reading: `name($lang = null)`, `shortName()`, `logoLarge()`, `logoSmall()`, `emails()`, `phones()`, `socials()`, `schedule()`, `indexable()` — each method returns the value of the requested language with a fallback to `default` (the default language is the current locale of the application). Writing — `save(array $data)`: it accepts the already validated structure of the form (one language `default`), converts it into the format of item 1, writes all the keys in `DB::transaction` together with the synchronization of the logos (item 3). The alternative — to split reading and writing into two classes — is redundant: one class knows the format.

If the transaction was rolled back, the cache of `AppSettings` contains new values that are not in the DB. `save()` in `catch` calls `AppSettings::flush()` and rethrows the exception, so that the further reading in this process does not lie.

The saving does not touch the foreign prefixes (only the nine keys `site.*` are written), and the «wiping out» of the values that are absent from the form is excluded because the form sends the whole set, and the language keys other than `default` are kept by `save()` when writing: the new value of `default` is merged into the saved object, rather than replacing it.

### 3. Logos: the owner `site_setting`, `owner_id = 0`
The site settings are a single entity without a row in the DB, so `owner_id` is the constant `0` (the column is `unsigned`, no FK; 0 does not match any real `id`, which start from 1). The owner type `site_setting`, the role is the key of the logo (`logo_large`/`logo_small`), the language is `default`. `SiteSettings::save()` in the same transaction calls `FileRelations::sync('site_setting', 0, $role, 'default', $uuids)`. `App\Files\Owners\SiteSettingOwner::describe()` returns for `0` the caption «Налаштування сайту» ("Site settings") and the `url` `/admin/settings`; the class is registered in `config/files.php`. The check of the file is the rule `FileValue('image', …)`, as in the menu. The alternative «owner_id = hash of the key» is rejected as artificial; the alternative «a separate row in the DB for the settings» — a redundant migration.

The deletion of the «owner» is impossible (there is none), so a hook for cleaning the links is not needed. The validation error of the file is caught by the form request before the writing.

### 4. The directory of social networks in `config/social_networks.php`
An associative array `ключ => ['name' => 'Facebook']` (the frontend takes the icon by the key, here we do not store it). The keys: `facebook`, `instagram`, `x`, `linkedin`, `youtube`, `tiktok`, `telegram`, `viber`, `whatsapp`, `threads`, `pinterest`, `github`, `behance`, `dribbble`, `discord`, `snapchat`, `reddit`, `medium`, `twitch`, `vimeo`, `skype`, `vk`, `ok`. The list is fixed; a new one is added by editing the configuration. The form receives it as the prop `networkOptions` (`[{value, label}]`), the validation — `Rule::in(array_keys(config('social_networks')))`. The alternative «an arbitrary name + url» was rejected by the user.

### 5. Validation
One `UpdateSiteSettingsRequest`. The limits are taken from reasonable values, not from the user's requirements (here this is an assumption, it is changed in one form request without a change of the specification): `name` ≤ 255, `short_name` ≤ 100; a logo — `string`, `max:255`, `FileValue('image')`; `emails`/`phones`/`socials` ≤ 20 records; `email` — the rule `email` ≤ 255; a phone — `regex:/^\+?[0-9\s()\-]{3,32}$/` (digits, spaces, brackets, a hyphen, an optional `+`); `url` — `url:http,https` ≤ 2048 (the rule of the field `url` of the content, `UrlType::isValid`, does not fit here: it admits the relative links, `mailto:` and `tel:`, while the social networks must be only absolute `http(s)`); `label` ≤ 255; `schedule` ≤ 20 rows of exactly 4 cells ≤ 255, `nullable`. `network` — `required` + `in` of the directory. A row with an empty `value`/`url` is an error (the key `emails.0.value` etc.); a row of the schedule with all the empty columns is discarded **before** the validation (`prepareForValidation`), so it gives no error.

### 6. Routes and the controller
`GET /admin/settings` (`admin.settings.edit`) and `PUT /admin/settings` (`admin.settings.update`), both under `['auth','verified']` + `can:settings` (a guest — `/login`, without the right — 403). One `Admin\SiteSettingsController` (`edit`, `update`); after `update` — a redirect back with the flash «Settings saved.». The controller gives the props: `values` (the language `default`, the structure of the form), `logoFiles` (the description of the uploaded files, like `imageInfo` of the menu), `networkOptions`, `limits` (the maxima for the client hints). In `navigation.ts` we remove `mock: true` from the item Settings; the heading of the breadcrumbs is taken from the item itself, so `extraTitles` does not change.

### 7. UI: one form, a shared list component
`Pages/Admin/Settings/Site.tsx` on Inertia `useForm`, a sticky `FormActionBar` (as in the other forms). The blocks are sections: «Загальне» ("General") (the name, the short name), «Логотипи» ("Logos") (two `FileUpload profile="image"`), «Контакти» ("Contacts") (emails, phones), «Соцмережі» ("Social networks"), «Графік роботи» ("Working hours"), «Індексація» ("Indexing") (`Switch`). To avoid duplicating four lists, one shared `SortableList` in `Components/Admin/Settings/SortableList.tsx` on `@dnd-kit` (a handle + up/down with the keyboard, as in `NodeFieldList`), which accepts an array of rows with a stable client-level `id`, a render-prop for the content of a row, `onChange`, the button «Додати» ("Add"). The elements of the form need a temporary client `id` for stable keys during the drag; it is not sent to the server. Each list has a `data-testid`. The drag-and-drop library is the existing one, a second one is not added.

### 8. Language readiness
The form operates with flat values of the language `default` (`values.name`, `values.emails[i].label` …); `SiteSettings` wraps them into `{ default: … }` when writing and unwraps them when reading the form. Adding the language UI later means changing only the form and the mapping of `save()`/`edit()`, not the data format.

## Risks / Trade-offs

- **One entity with `owner_id = 0`** → the single exception to «owner_id is a real id». Mitigation: the constant and its documentation in `docs/media-library.md` and `docs/admin-site-settings.md`; `SiteSettingOwner` is the single place that knows it.
- **The cache of `AppSettings` after the rollback of the transaction** → `save()` resets the cache in `catch` (see item 2); covered by a test of the rollback.
- **A discrepancy between the saved `default` and the future other languages when editing only `default`** → `save()` merges the value of `default` with the saved other languages, without wiping them.
- **The fixed directory of social networks becomes outdated** → an edit of the configuration. A saved network that is no longer in the directory is read, and the form adds it to `networkOptions` as the current value, so that editing does not lose the record; on saving such a network is rejected by the validation until it is changed.
- **The limits of the validation are assumptions** → changed in one form request without a change of the specification.
- **Client `id`s of the rows of the list** → are not stored; after saving the page is reloaded, the `id`s are generated again.

The description of the components that this change creates is kept in `docs/admin-site-settings.md` (new), `docs/app-settings.md` (the prefix `site.`), `docs/media-library.md` (the owner `site_setting`), `docs/access-control.md` (the right `settings`), `docs/admin-shell.md` (the item Settings), `docs/e2e-testing.md` (the new spec).
