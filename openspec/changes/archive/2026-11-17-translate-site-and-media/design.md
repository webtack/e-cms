# Design

## Context

`App\Settings\SiteSettings` stores each translatable setting as an object keyed by language code (`{"default": ...}`); lists keep a `label` object per item and are identified by a key (`identity()`: `value`, or `network` + `url`). `formValues()` and `save()` are base-only. `media.description` is one column of the file, edited in the node form through `file_meta` (`name`, `description`) and shown in the media library details (`docs/file-storage.md`, `docs/media-library.md`). The translation contract and the node/type translation actions come from the two previous changes. Components touched: see `docs/admin-site-settings.md`, `docs/file-storage.md`, `docs/media-library.md`.

## Goals / Non-Goals

**Goals:**
- Site settings and file descriptions translatable through the same contract as every other entity.
- Alternative texts of images translatable in the context where editors already see the image (the node and type forms).

**Non-Goals:**
- Translating logos, file names, `indexable`, contact values, or adding rows in a translation.
- Public fallback and rendering (next change).
- A separate translation screen in the media library (the details panel stays read-only).

## Decisions

**Site settings: `saveTranslation($lang, $data)` over the existing storage.** It reuses `storeTranslated` to write or remove one language key and drops values equal to `default` (strings by trimmed equality, schedule as a whole table). Lists: a translation payload is `labels` keyed by item identity; unknown identities are ignored, so a stale form cannot recreate removed rows. The page response in translation mode provides `rows` with the identity and the prefilled label per base row. Alternative: edit the lists fully per language; rejected, rows are base data and fixed-row editing prevents the languages from drifting.

**Settings page: one controller, `?lang=`, routes `PUT/DELETE /admin/settings/translations/{lang}`** under the existing `can:settings`.

**The translation mode keeps the layout of the base form.** Established by `translate-nodes-and-types` and applied here to every form of this change: one form in both modes, with the language switcher above it. Only the translatable controls are editable; every other control (logos, the indexing checkbox, identity of contact and social rows, and in the node and type forms the file itself) stays in its place, shown disabled (`inert`, dimmed) with its base value. Controls are never hidden or moved between blocks, because a field that jumps from a side block to the main one confuses users. "Save" stays disabled until a translatable value changes, and "Clear translation" sits in the form's own action bar (`ClearTranslationButton`). `TranslationFormShell` no longer exists.

**File names and descriptions in `media_translations`, not in `media`.** Columns: `file_uuid` (`char(32)`, FK to `media.uuid`, cascade on delete), `lang` (binary collation like `content_values_*`), `name` (string 255, null), `description` (text, null; a row holds at least one of them), unique (`file_uuid`, `lang`). `File::original_name` and `File::description` stay the base. The translated name is for display only: the original name, the path and the extension never change. A `FileDescriptions` service reads and writes one language and applies the equal-to-base rule to both fields. Alternative: a JSON column on `media`; rejected because the language usage check and the cascade are simpler with a table, and the table mirrors terms and menu items.

**Editing file descriptions in the node and type translation modes, not in the media library.** Editors write the alternative text where they see the image. `file_meta` in the translation save request carries only `name` and `description` per file uuid; the server accepts only uuids that occur in the values of the node (or type) being saved, so one request cannot edit arbitrary files. The base name and description of the file are untouched by the translation mode.

**Non-translatable file fields still expose descriptions.** The description belongs to the file, not the field; hiding it would leave images without an alternative text in the other language. The form shows the file read-only and only the name and description inputs.

**Media details are read-only for translations.** The panel lists translated descriptions with language names; editing stays in the content forms, which keeps the single editing path.

**Usage checkers.** `media_translations` and the site settings (any translatable key holding the language) are registered with the usage registry from `content-languages-admin`.

## Risks / Trade-offs

- [A file description edited in one node changes every node that uses the file] → inherent to the shared description (the same as the base); the translation mode shows the shared value as prefill.
- [Row identity of list items changes when the base value is edited, orphaning a label] → the label is read by the current identity; an orphaned label is ignored and removed on the next save of that language; a test covers it.
- [`file_meta` abuse] → uuids are checked against the saved owner's values.
- [Schedule equality] → compared as the normalized table, so cosmetic differences (trailing blanks) do not create a translation.

## Migration Plan

Create `media_translations`. Rollback drops it (data loss of translated descriptions only). No change to existing rows.
