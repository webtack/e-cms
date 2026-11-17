# Design

## Context

Node and type values already store `lang` in `content_values_*`; `ContentValueWriter::write($owner, $values, $lang)` replaces only the passed fields of one language and `ContentValueReader::load/loadForType` read them (docs: `docs/content-values.md`). Required fields are checked only in the base language. Field `settings` is currently whitelisted per value type (`StoreContentFieldRequest::settingsRules()` rejects settings for most types) and `ContentFieldController` rebuilds `settings` from the known keys on save. The node list is a server-mode DataTable whose columns are not filterable (`docs/admin-content-nodes.md`). The shared translation contract comes from `translation-ui-foundation`. Touched docs: `docs/admin-content-nodes.md`, `docs/admin-content-types.md`, `docs/admin-content-fields.md`, `docs/content-values.md`, `docs/content-model.md`.

## Goals / Non-Goals

**Goals:**
- Translation mode for nodes, type values and field labels, identical to terms and menu items.
- Translated markers and a missing-translation filter that do not cost a query per node.

**Non-Goals:**
- Translating the descriptions of files (change `translate-site-and-media`).
- Per-item fallback of multiple fields, or translating slugs (decided: one slug for all languages).
- Public reading and fallback (change `public-language-routing`).

## Decisions

**A `TranslationSaver` service for node and type values on top of `ContentValueWriter`.** It filters the input to translatable fields of the owner, validates the submitted values without the required rule, drops values equal to the base (comparing `FieldType::prepare()` output of each side; a list compared whole and in order), turns emptied fields into removals and calls the writer once in a transaction. The writer itself stays unchanged and strict. Alternative: put the logic into the controller; rejected, because the equal-to-base rule must be shared by nodes, types and every future caller.

**Prefill is built on the server.** The edit action returns translatable field values merged per field (translation if non-blank by `FieldType::isBlank`, else base). The form never receives the base separately.

**Saving a translation touches `updated_at` of the node.** It is a real change of what the site shows (sitemaps and caches use it). Type values have no modification time to touch.

**"Translated" marker and list filter from one query.** A `TranslatedLanguages` service takes node ids (or a type) and runs one `UNION ALL` over the seven value tables selecting distinct (`content_node_id`, `lang`) with `lang <> 'default'` and `entity = 'node'`. The "without translation in" filter uses `NOT EXISTS` over the same tables. Alternative: a denormalized column on `content_nodes`; rejected, it would have to be maintained by every writer path and can drift.

**The Translations column and the filter are added to the list payload only when languages are active.** Single-language sites receive an unchanged payload, so their performance and tests are unaffected. The filter reuses the DataTable `quickFilter` slot (as the media library does) and a new list parameter validated against the active languages. If the quick filter cannot carry it, the implementation falls back to a dedicated select above the table; this does not change the behavior in the spec.

**Field labels in `settings.labels`, saved through their own route.** `PUT .../fields/{field}/translations/{lang}` writes only `settings.labels[lang]`; the base form request and `ContentFieldController` are changed so `labels` is accepted for every type and is carried over when the controller rebuilds `settings` for the type's own keys. The `ValidatesSettings` hook runs only when the type's own settings change, so a labels-only save is not checked by the type (a test fixes this for `term` fields with values). Alternative considered: a `content_field_translations` table; rejected by the owner as heavier than one JSON key.

**Language-code keys in JSON.** Codes such as `pt-BR` contain a hyphen; the usage checker for labels uses `whereJsonContainsKey("settings->labels->{$lang}")`, which quotes the path, and a test covers `pt-BR` on both MySQL and SQLite.

**Type values tabs share one backend action each, with `lang`.** `PUT .../{section}/{type}/values/translations/{lang}` saves the values of the tab (`section_*` or `meta_*`) it is sent from; the Display and SEO tabs pass their own field keys. Clearing from one tab clears only that tab's values; the marker covers both.

## Risks / Trade-offs

- [A translatable image or file field is prefilled with the base file; the user may think it is already translated] → it is stored only if changed; the "translated" marker counts only stored values.
- [`UNION ALL` over seven tables on a very large site] → indexes on (`content_type`, `field_key`, `lang`, `value`) and the owner unique index already exist; only the page's ids are queried.
- [The required rule is skipped, so a user can empty a required translatable field] → intended: an empty translation means "use the base" (public fallback in the next change).
- [Normalizing `settings` in the controller may drop unknown keys in future] → `labels` is carried over explicitly and covered by tests for every value type.
- [Large richtext forms in translation mode] → one editor per field, as in the base mode.

## Migration Plan

No schema change and no data change. The feature is inert on sites where multilingual mode is off.
