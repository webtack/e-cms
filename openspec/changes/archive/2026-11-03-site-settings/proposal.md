# Proposal

## Why

The CMS has no place for general information about the site: the name, the logos, the contacts, the social networks, the working hours and the global indexing flag. The frontend will need them in the header, the footer and `meta`, and the right of the role `settings` already exists, but is not applied to anything («ще немає» — "there is none yet" in `docs/access-control.md`), and the item Settings in the sidebar remains a mock-up.

## What Changes

- A new page `GET/PUT /admin/settings` («Налаштування сайту» — "Site settings") under the right `settings`; the item Settings in the sidebar stops being a mock-up.
- New settings in `app_settings` with the prefix `site.`:
  - `site.name`, `site.short_name`, `site.schedule` — translatable (`{ "default": ... }`);
  - `site.logo_large`, `site.logo_small` — translatable files of the profile `image` (`{ "default": "{uuid}.{ext}" }`), optional;
  - `site.emails`, `site.phones` — lists `{ value, label? }`, `label` is translatable (`{ "default": ... }`);
  - `site.socials` — a list `{ network, url, label? }`, `network` from a fixed directory in `config/`, duplicates are allowed, `label` is translatable;
  - `site.indexable` — `bool`, by default `false`.
- The working hours are a list of rows, each row has 4 single-line texts (the columns), without a limit on the content; the limit of the number of rows is in the validation.
- The lists (`emails`, `phones`, `socials`, `schedule`) have adding, deleting and the order by drag-and-drop.
- The data format is ready for languages (all translatable values are stored under the key `default`), and the UI shows and edits only `default`; the language UI is a separate future change.
- The reader `SiteSettings` over `AppSettings` for the templates with a fallback to `default`.
- The logos are tracked in `media_relations` by the new owner `site_setting`, so the used file cannot be deleted from the media library; the media library shows the owner with a link to the settings page.
- Out of scope: the output of `meta robots`, `X-Robots-Tag`, `robots.txt` (there is no frontend), the language UI, the effect of `site.indexable` in the admin.

## Capabilities

### New Capabilities

- `admin-site-settings`: the page of the site settings in the admin — the fields, the validation, the lists with drag-and-drop, the saving, the access by the right `settings`.
- `site-settings`: the storage and the reader of the site settings (`site.*`) — the format of the values, the default values, the fallback to `default`, the directory of social networks.

### Modified Capabilities

- `admin-shell`: the item Settings in the sidebar becomes working (no longer a mock-up) and leads to `/admin/settings`.
- `media-library`: the owner of the links `site_setting` appears (the logos), which the media library describes by a caption and a link.

The spec `access-control` does not change: the system right «налаштування сайту» ("site settings") is already defined in it, and its application to the page is described by `admin-site-settings`. Only `docs/access-control.md` is updated.

## Impact

- **Code:** `routes/web.php`; the controller `Admin\SiteSettingsController`; a form request; `App\Settings\SiteSettings` (the reader and the writer) and the directory `config/social_networks.php`; `App\Files\Owners\SiteSettingOwner` and a row in `config/files.php`; `resources/js/Pages/Admin/Settings/Site.tsx` with the components of the lists; `navigation.ts` (remove `mock`, the heading of the page); translations (`lang`).
- **DB:** there are no migrations — the existing `app_settings` and `media_relations` are used.
- **Dependencies:** no new ones; the drag-and-drop — the existing `@dnd-kit`.
- **Docs:** create `docs/admin-site-settings.md`; update `docs/app-settings.md` (the prefix `site.`), `docs/access-control.md` (the right `settings`), `docs/admin-shell.md` (the item Settings), `docs/media-library.md` (the owner `site_setting`), `docs/README.md` (the index), `docs/e2e-testing.md` (the new spec in «Область → спек»).
- **Tests:** Feature tests `tests/Feature/Settings` (the reader) and `tests/Feature/Admin` (the page), a new spec `e2e/admin-site-settings.spec.js`.
