# Proposal

## Why

Translations of terms and menu items are edited today as extra name/description blocks for every language inside the main form. The same approach does not scale to nodes with rich text, files and many fields, and the owner wants one behavior for every entity. This change defines that behavior once (a "translation mode": one language at a time, switched by `?lang=`) and applies it to the two entities whose storage and writers already exist, so the mode is proven before nodes, site settings and media adopt it.

## What Changes

- New shared contract **translation mode** for admin edit pages: a language switcher above the form (shown only when multilingual mode is on and at least one additional language is active), the address parameter `?lang=<code>`, only translatable fields shown, values prefilled from the base language when the translation is empty, saving by an explicit button, values equal to the base not stored, "Clear translation", confirmation before leaving unsaved changes, markers of languages that already have a translation.
- Terms and menu items move to this contract: the per-language blocks disappear from their create and edit forms; translations are edited in the translation mode of the edit page.
- **BREAKING (admin UI):** translations are no longer entered on the create form and no longer submitted together with the base form; the base form never changes translations.
- In non-base languages the term name and the item title become **optional**, so a translation may hold only one field and the other falls back to the base later (field-level fallback). The translation row exists only while at least one of its fields has a value.
- Writers get a "save one language" operation used by the translation mode.

## Capabilities

### New Capabilities
- `admin-translation-mode`: the shared behavior of editing the translations of one entity in one language at a time.

### Modified Capabilities
- `admin-taxonomy`: term create/edit forms lose the translation blocks; terms are translated in the translation mode.
- `admin-menus`: menu item create/edit forms lose the translation blocks; items are translated in the translation mode.
- `taxonomy`: term translation fields are individually optional outside the base language; one-language save semantics.
- `menus`: item translation fields are individually optional outside the base language; one-language save semantics.

## Impact

- Code: shared React pieces (`LanguageSwitcher`, `TranslationFormShell`, unsaved-changes guard), a controller concern that resolves `?lang=`, routes for saving and clearing one language of a term or an item, changes in `TermWriter`, `MenuItemWriter`, `TermController`, `MenuItemController`, `TermForm`, `MenuItemForm`.
- Database: a migration making `taxonomy_term_translations.name` and `menu_item_translations.title` nullable (full PHP test run required).
- Docs: new `docs/admin-translation-mode.md`; update `docs/admin-taxonomy.md`, `docs/admin-menus.md`, `docs/taxonomy.md`, `docs/menus.md`, `docs/README.md`, `docs/e2e-testing.md` (shared-code table: the new components and their consumers).
- Tests: Feature tests for the writers and controllers; e2e `admin-taxonomy` and `admin-menus` updated.
- Depends on `content-languages-admin` (languages and the base code exist in the admin). Later changes reuse the contract for nodes, types, field labels, site settings and file descriptions.
