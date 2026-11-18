# Design

## Context

See `proposal.md`. The current state (read in the code):

- The `update` methods of the controllers `app/Http/Controllers/Admin/*` return `to_route(...index)` with `success` (types, fields, records, menus, items, vocabularies, terms, roles, users). The tab of the type (`ContentTypeDisplayController`, `ContentTypeValuesController`) and `SiteSettingsController` already return `back()`.
- The edit addresses are stable after saving: types, vocabularies, menus are addressed by the unchanged `key`, records — by `uuid`, fields — by `key` (the key and the owner type do not change), so `back()` does not lead to 404. The changeable slug of a type is not part of the address.
- The button «Перегляд» ("Preview") in the form of a record already exists: `ContentNodeController::publicUrl()` gives the address, `ContentNodeForm` shows the link.
- Home page: `HomeController` → `respond(['home'], 'home')` → `PublicData::make()`; `$meta` is read only from `$extra['values']` (the `meta_*` of the record); for the home page and for pages without `meta_*` it is empty, and `html.blade.php` outputs `description` and `og:image` only from `$meta`.

Documentation: `docs/admin-shell.md` (flash/redirect), `docs/admin-content-types.md`, `docs/admin-page-types.md`, `docs/admin-block-types.md`, `docs/admin-content-fields.md`, `docs/admin-content-nodes.md`, `docs/admin-menus.md`, `docs/admin-taxonomy.md`, `docs/admin-roles.md`, `docs/admin-users.md`, `docs/public-site.md`, `docs/theme-templates.md`.

## Goals / Non-Goals

**Goals:**
- Every `update` in the admin keeps the user on the form; the success toast is shown by the existing `FlashToaster`.
- The item «Перегляд» in the menu of a record row without additional requests.
- The chain of metadata (record → fields of the type → `SiteSettings`) for all public pages without a change of the public candidate templates.
- The choice of meta fields on the tab «Відображення» without new tables and migrations (only `settings.public`).

**Non-Goals:**
- A change of the behaviour after creation and deletion.
- New site settings (a separate SEO image, `og:title` etc.).
- New meta fields of the record (`meta_*` already exist and remain with priority) and the tags `og:title`/`og:description`.

## Decisions

1. **`back()` instead of `to_route(...)` in `update`.** It is simpler than an explicit redirect to `edit` and works the same for the three sections (`content/page/block`). Alternative — `to_route('…edit')` — requires knowing the route name and the parameters in every controller. Risk: `back()` depends on the `Referer`; Inertia always sends it, and for requests without it Laravel returns `/`. To be sure, the edit forms remain the single source of PUT.
2. **The public address in the table row: the field `public_url` in `row()`.** The server logic `publicUrl()` already exists; it must be made to accept kind/type/node (and not `Request`), and be called from `row()` and from `edit()`. The client does not repeat the rules for building the address. Alternative (building on the client from the slug) was rejected: the rule about the slug of the type and the blocks lives on the server.
3. **The item «Перегляд» — `<a target="_blank" rel="noopener noreferrer">` in `DropdownMenuItem asChild`,** between «Редагувати» ("Edit") and the separator before «Видалити» ("Delete"). It renders only when `public_url !== null`. The translation «Перегляд» already exists.
4. **The key `settings.public.meta` = `{title?, description?, image?}`** (the key of a field or absent). Permitted field types: `title` — `string`; `description` — `text`, `richtext`; `image` — `image`, `youtube`. Form validation — `UpdateContentTypeDisplayRequest` (`array:...,meta`, three optional strings); permissibility of the keys and types — `ContentType::assertValidPublicSettings()`; for kind `block` the key is rejected (as the list keys for `page`/`block`). `PublicDisplaySettings::clean()` drops obsolete keys (a deleted field or a changed type), `formOptions()` returns for each choice the list of permitted fields and the current state; an empty `meta` is not saved. Alternative — a separate key `settings.meta` — was rejected: this is the public display, the form already saves `public` in one request and does not overwrite the other keys.
5. **The chain of metadata in the new class `App\Theme\PageMeta`** (it is called by `PublicData::make()`): for each of `title`, `description`, `image` it takes the first non-empty value from the `meta_*` of the record → the chosen field of the type → the settings of the site. Input — `$extra['type']`, `$extra['values']`; without a record (the home page, 404) only the third source applies. `$meta` keeps the three keys; `meta.title` is the value from the record or the field, and when there is none, `null` (the heading of the document is then built as now: `title` of the record + the name of the site). The template `html.blade.php` does not change; a theme can override the output.
6. **Reading the values of the field:** the values are taken from the already loaded `$values` (`ContentValueReader`). For a multiple field — the first element. `string`/`text` — the trimmed text; `richtext` — `HtmlSanitizer::text()`, the spaces are collapsed. The description is shortened to `config('content.meta_description_max')` (160) at a word boundary with «…». `image` — the stored value `{uuid}.{ext}` → URL through `FileStorage::urlForValue('image', …)`, as for `meta_image`; `youtube` — `screen` of the composed value (`ComposedValue`) → the same URL; without `screen` the value is absent and the next source is taken. The alternative — pulling the YouTube preview from `img.youtube.com` — was rejected: the site makes no requests to YouTube while showing a page, the screenshot is already saved in the media library.
7. **The contract of `$meta` does not change** (always three keys), so `docs/theme-templates.md` is only clarified: `$meta` is filled by the chain record → fields of the type → settings of the site.

## Risks / Trade-offs

- [The existing Feature and e2e tests expect a redirect to the list] → update only those that check `update` (see tasks), the rest is not touched.
- [The user no longer sees the list after saving] → this is the desired behaviour; «Назад» ("Back") in the form and in the section menu remain.
- [`back()` after a validation error and after success behaves the same way for Inertia] → the state of the form is updated by the props of the page; check that the form shows the saved values (especially files and `updated_at` of the record).
- [The small logo as `og:image` if the large one is absent] → an acceptable fallback; it is specified by the owner in case of a different wish (see the assumptions in the proposal).
- [The chosen field changed its type or was deleted] → `clean()` ignores obsolete keys, the site falls back to the next source; the form shows «Не вибрано» ("Not selected").
- [Shortening a description of long texts with multibyte characters] → use `mb_*`/`Str::limit` with UTF-8 in mind, cover with a test of Ukrainian text.
