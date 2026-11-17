# Design

## Context

Language rules live in `App\Content\ContentLanguages` (switch in `app_settings` under `content.multilingual`, list in `app_languages`); they are described in `docs/content-languages.md`. `AppLanguage::deleting` currently refuses deletion only when a `content_values_*` table holds the language, so translations that live elsewhere (`taxonomy_term_translations`, `menu_item_translations`) can be orphaned. Admin settings sub-pages already exist (`Pages/Admin/Settings/Site.tsx`, `Navigation.tsx`, routes under `can:settings`). See proposal.md for motivation. The page is described later in `docs/admin-content-languages.md`; the domain rules in `docs/content-languages.md`.

## Goals / Non-Goals

**Goals:**
- Manage content languages, the switch and the base language code from the admin panel.
- One place that answers "does any translation of this language exist", extensible by later changes.

**Non-Goals:**
- No translation editing UI (changes 2-4), no public language routing (change 5).
- No change of the admin interface language and its switcher.
- No import/export of languages, no language-specific settings (flags, locales for date formats).

## Decisions

**Base language code in `app_settings` (`content.base_lang`), not a column or a `default` row in `app_languages`.** The `default` key in value tables must stay a code-less placeholder so that renaming the base language never rewrites values. A settings key names it for the outside world (`<html lang>`, `hreflang`, URL logic). Alternative: a row in `app_languages` with a flag `is_base`; rejected because every language query would need to exclude it and the "no `default` code" rule would blur.

**Unsaved base language reads as the configured default locale (`config('content.base_lang_default')`, `env('APP_LOCALE', 'en')`), but multilingual cannot be enabled until a code is saved.** `config('app.locale')` is not used: the `SetLocale` middleware overwrites it on every request with the interface locale of the user, so it would make the value depend on the admin language. A separate config key is not touched by the middleware and does not depend on resolution order. Existing single-language sites keep working without a decision; the explicit save guarantees that a multilingual site never relies on a possibly colliding implicit value (the locale may equal an additional language such as `en`).

**Base code must differ from every additional language.** Otherwise `/uk/...` would be both "base" and "a translation". Checked on both sides: when saving the base code and when saving a language.

**Registry of language usages.** `ContentLanguages` gets a list of "usage checkers" (container tag `content.language-usages`; each implements `exists(string $lang): bool`). The deletion guard asks all of them. This change registers three: content values (existing check moved into a checker), term translations, menu item translations. Changes 3 and 4 register field labels, site settings and file descriptions. Alternative: hard-code every table in `AppLanguage::deleting`; rejected because every later feature would have to edit the model and forgetting one orphans data.

**Page is a sub-page of Site under `can:settings`, no new permission.** The switch and languages are site-level configuration and the `settings` right already covers it; a new right would force a role migration for no gain.

**One form for switch + base code, separate small forms for adding/editing/deleting languages.** The switch and the base code depend on each other (the switch needs the code), so they are saved together; language rows are independent records. Routes: `GET /admin/settings/languages`, `PUT /admin/settings/languages` (switch and base code), `POST /admin/settings/languages` (add), `PUT /admin/settings/languages/{language}` and `DELETE` (language bound by `code`).

**Few rows, so no DataTable.** The list is a plain table with row actions; languages number in single digits and the data-table machinery (server mode, column visibility) adds nothing.

## Risks / Trade-offs

- [A site with multilingual already on and no saved base code] → it reads the application locale; the page shows the effective value as unsaved and asks to save it; nothing breaks until the user edits it.
- [Base code collides with an existing language on first save] → rejected with an error next to the field; the user must delete or rename the language first.
- [A feature forgets to register its usage checker] → covered by the docs rule in `docs/content-languages.md` and by a test that lists the registered checkers.
- [e2e runs against the working database] → the e2e spec snapshots the existing languages and the two settings before it starts and restores them afterwards; it deletes only what it created.
- [Deleting becomes slower with many checkers] → deletion is rare and each checker is one `exists` query.
