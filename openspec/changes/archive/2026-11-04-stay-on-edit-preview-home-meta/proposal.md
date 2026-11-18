# Proposal

## Why

After saving changes, the admin throws the user from the form back to the list, so to adjust something else or to check the result the form has to be opened again. The row of a record in the table has no quick link to the public page, and the home page of the site does not output metadata from the settings of the site (the description and the image for search engines and social networks).

## What Changes

- After a successful **update** (of the edit forms) the user stays on the same page with a success toast instead of moving to the list. This applies to all edit forms of the admin: content type, page type, block type, field of a type, record, menu, menu item, vocabulary, term, role, user. The tabs of the type settings («Відображення» ("Display"), «Значення» ("Values"), «SEO») and the site settings already stay on the page; the behaviour is only recorded in the specification.
- Creation and deletion do not change (after creation the user goes to the list, as before).
- In the «⋮» menu of a record row (types `content` and `page`) an item «Перегляд» ("Preview") appears — a link to the public address of the record in a new tab (like the button «Перегляд» in the edit form). For blocks and for `content` types without a public slug there is no item.
- The public pages (the home page, pages, records, 404) output in `<head>` the metadata (`<title>`, `meta description`, `og:image`) by the chain: own `meta_*` of the record → the fields chosen in the settings of the type → the settings of the site (name, description, large logo, fallback small). Each value is taken separately.
- On the tab «Відображення» of the types `content` and `page`, in «Публічне відображення» ("Public display"), a sub-block «Мета-дані» ("Meta data") appears: the choice of a field for the heading (string), the description (text / formatted text) and the image (image / YouTube video). For multiple fields the first value is taken; the HTML is stripped from the description, and the text is shortened to 160 characters; for YouTube the screenshot of the video is taken, if it exists. It is saved in `settings.public.meta`. For the types `block` there is no sub-block.

## Capabilities

### New Capabilities

None.

### Modified Capabilities

- `admin-shell`: a new requirement — a successful saving of an edit form leaves the user on the same page.
- `admin-content-types`: «Збереження змін типу» ("Saving the changes of a type") — the user stays on the form, not moved to the list; «Секція «Публічне відображення»…» ("Section «Public display»…") — the sub-block «Мета-дані».
- `admin-page-types`: «Редагування типу сторінки» ("Editing a page type") — the same.
- `admin-block-types`: «Редагування типу блоку» ("Editing a block type") — the same.
- `admin-content-fields`: «Збереження змін поля» ("Saving the changes of a field") — the user stays on the form of the field.
- `admin-content-nodes`: «Збереження змін запису» ("Saving the changes of a record") (stays on the form) and «Створення запису з таблиці» ("Creating a record from the table") (the item «Перегляд» in the row menu).
- `public-site`: a new requirement — the chain of metadata: record → chosen fields of the type → settings of the site.

## Impact

- Backend: `update` methods of the controllers in `app/Http/Controllers/Admin/` (`ContentTypeController`, `ContentFieldController`, `ContentNodeController`, `MenuController`, `MenuItemController`, `VocabularyController`, `TermController`, `RoleController`, `UserController`) → `back()`; `ContentNodeController::row()` adds the public address of the row; `App\Theme\PublicData` and a new resolver of metadata; `PublicDisplaySettings`, `UpdateContentTypeDisplayRequest`, `ContentType::assertValidPublicSettings()`, `ContentTypeDisplayController` — the key `settings.public.meta`; `PublicDisplaySettingsSection.tsx` / `TypeDisplayForm.tsx` — the sub-block «Мета-дані»; `config/content.php` — the length of the description.
- Frontend: `resources/js/Components/Admin/ContentNodes/ContentNodeRowActions.tsx`, `resources/js/Pages/Admin/ContentNodes/Index.tsx`; the template `themes/default/views/html.blade.php` (if needed).
- Tests: update the Feature tests and e2e specs that expect a redirect after the update; add tests of the item «Перегляд» and of the metadata (the chain, the fields of the type, YouTube, shortening); the translation «Перегляд» already exists.
- Docs: `docs/admin-content-types.md`, `docs/admin-page-types.md`, `docs/admin-block-types.md`, `docs/admin-content-fields.md`, `docs/admin-content-nodes.md`, `docs/admin-menus.md`, `docs/admin-taxonomy.md`, `docs/admin-roles.md`, `docs/admin-users.md`, `docs/admin-shell.md`, `docs/public-site.md`, `docs/theme-templates.md`, `docs/e2e-testing.md` (if needed).

## Assumptions

- «Усі форми» ("All forms") = all edit forms of the admin, not only the forms of content types; creation and deletion remain as they were.
- «image» in the settings of the site = the large logo (`logo_large`), if it is absent — the small one.
- The pages inherit the description and the image of the site (the owner's decision); the `<title>` already has a fallback — the name of the site.
- The maximum length of the meta description is 160 characters (a common limit for search snippets), it is set in the configuration; shortening at a word boundary with «…». For the heading the shortening is not applied.
- The meta fields are chosen among the added fields (`is_system = false`) by the value type; for the pages `page` the sub-block is available too, for `block` — no (there is no address of its own).
