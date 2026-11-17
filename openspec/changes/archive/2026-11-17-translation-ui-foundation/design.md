# Design

## Context

Terms and menu items keep translations in their own tables (`taxonomy_term_translations`, `menu_item_translations`, both unique per (`*_uuid`, `lang`), both with `name`/`title` NOT NULL). `TermWriter` and `MenuItemWriter` accept a `translations` map for all languages in one call and delete a translation when its name/title is blank. The admin forms (`TermForm`, `MenuItemForm`) render one name/description block per active language and submit everything together. See proposal.md for motivation and the later changes that reuse the contract (`docs/admin-taxonomy.md`, `docs/admin-menus.md`, `docs/taxonomy.md`, `docs/menus.md` describe the touched components).

## Goals / Non-Goals

**Goals:**
- One translation-mode contract (spec `admin-translation-mode`) implemented as shared frontend pieces and a shared backend concern.
- Migrate terms and menu items to it, with field-level optional translations.

**Non-Goals:**
- Nodes, type values, field labels, site settings, file descriptions (later changes adopt the contract).
- Public reading and fallback (change `public-language-routing`).
- Bulk translation, machine translation, import/export of translations.

## Decisions

**`?lang=` on the edit page, translation saved by separate routes.** Edit pages read `lang`; saving a translation is `PUT .../{entity}/translations/{lang}` and clearing is `DELETE .../translations/{lang}`; the base form keeps its own `PUT`. Alternative: one `PUT` with a `lang` field; rejected because it lets one request change shared attributes and a language together, which the contract forbids. The routes reuse the entity's existing right (`can:taxonomy`, `can:menus`).

**Server resolves the language.** A controller concern (`ResolvesTranslationLanguage`) reads `lang`, validates it with `ContentLanguages::assertWritable`-style rules, answers 404 otherwise, and builds the shared Inertia prop `translation: { lang, baseLang, languages: [{code, name, translated}] } | null` (null when the switcher is not shown). `translated` comes from one per-entity method that follows the single definition in the spec.

**Prefill on the server.** In the translation mode the controller returns the form values already merged (translation, else base). The client never needs the base values separately; "dirty" is a comparison with the initial values.

**Equal-to-base is dropped on the server, not the client.** `saveTranslation($entity, $lang, $data)` compares each field with the `default` value and stores only differing, non-empty ones. The client only disables the button when nothing changed. This keeps the rule in one place for every future entity and for non-UI callers.

**Optional name/title outside the base language needs a migration.** `taxonomy_term_translations.name` and `menu_item_translations.title` become nullable; a row is deleted when all of its fields are null. Without this a translation with only a description could not exist, and a differing description would be impossible to save when the name equals the base. `default` rows stay required by the writers (not by the schema).

**Shared React pieces.** `LanguageSwitcher` (tabs/links with markers, unsaved-changes confirmation through the Inertia router), `TranslationFormShell` (header, switcher, save and clear buttons, disabled-until-dirty), and a small `useDirtyGuard` hook. Entity forms pass their translatable fields as children. Alternative: copy the switcher into each form; rejected, the owner requires identical behavior for every entity.

**Base writers keep accepting `translations`.** `TermWriter::create/update` and the menu writer still accept the map (used by importers and tests), but the admin controllers stop passing it. Removing the capability would force rewrites of seeders and tests without user value.

## Risks / Trade-offs

- [Nullable columns break readers that assume a non-null title/name] → readers treat a null as "missing"; the public readers do not fall back yet, so a null field behaves like a missing translation. Tests cover `MenuTree` and the `term` field type with a partial translation. The migration requires a full PHP test run.
- [The user presses save on an untouched form] → the button is disabled and the server drops equal values anyway.
- [Identical translation is indistinguishable from "not translated"] → documented limitation; "translated" means at least one differing stored value.
- [A user edits the base name after translating] → translations are independent; the translation mode of other languages shows their stored values.
- [Existing e2e specs for taxonomy and menus assert the translation blocks] → rewritten in this change.

## Migration Plan

Run the migration (columns nullable; no data change). Rollback: refuse if nullable rows exist, otherwise restore NOT NULL. No data conversion is needed because every existing row has a name/title.
