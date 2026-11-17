# Proposal

## Why

Two kinds of public data still have no way to be translated: the general site settings (name, description, address, schedule, contact labels) and the descriptions of files, which are the alternative text of every public image. Site settings store values by language code but the form writes only `default`; file descriptions are a single column shared by every use of the file.

## What Changes

- **Site settings:** the settings page gets the translation mode: name, short name, description, address, schedule and the labels of emails, phones and socials. List rows are fixed in a translation (identified by their number, address or link), only labels are edited. Logos, the indexing flag and non-translated data stay base-only.
- **File names and descriptions:** a new table of translated display names and descriptions per file and language (the original name is never changed). They are edited in the translation mode of nodes and of type values (Display and SEO tabs) for every image or file attached, even when the field itself is not translatable, and shown read-only in the media library details.
- Both register with the language usage registry, so a language with translated settings or descriptions cannot be deleted.
- The settings form loses its "base only" statement: with languages active it shows the switcher.

## Capabilities

### New Capabilities
- None.

### Modified Capabilities
- `admin-site-settings`: translation mode of the page; the base form no longer claims to be the only mode.
- `site-settings`: saving one language of the translatable settings.
- `media-library`: translated descriptions of files and their display in the details panel.
- `admin-content-nodes`: file descriptions in the translation mode of a node.
- `admin-content-types`: file descriptions in the translation mode of the Display and SEO tabs.

## Impact

- Code: `SiteSettings` (translation read/write by item identity), `SiteSettingsController` and its requests, `Site.tsx`; a `media_translations` migration, a `FileTranslation` model and a description service; extensions of the node and type translation save actions to accept file descriptions; media details panel; usage checkers.
- Database: new table `media_translations` (full PHP test run required).
- Docs: update `docs/admin-site-settings.md`, `docs/file-storage.md`, `docs/media-library.md`, `docs/admin-content-nodes.md`, `docs/admin-content-types.md`, `docs/admin-translation-mode.md`, `docs/e2e-testing.md`.
- Tests: Feature tests for the services and controllers; e2e `admin-site-settings`, `admin-media` (details), `admin-content-nodes` (translation tests only), `admin-content-types`.
- Depends on `translate-nodes-and-types` (the node and type translation modes extended here).
