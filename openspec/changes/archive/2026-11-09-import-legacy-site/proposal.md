# Proposal

## Why

The production site of the department (kultura-poltava) runs on an older CMS where every field is a separate table keyed by `entity_type + entity_id + entity_field`. The new CMS must replace it with the same content and the same public addresses: 4414 nodes (3516 news, 736 events, institutions, collectives and others), 75 blocks, one page, about 29 thousand files (6.8 GB), the view counters, and the editors' accounts. There is no migration path today. This change adds a repeatable import command so the data can be moved, checked with a dry run, and re-run until the result is accepted.

It depends on three earlier changes: `add-node-views` (the `views` column and the initial value in `ContentNodeCreator`), `nested-type-slugs` (type slugs such as `common/institutions`, prefixed by the parent's slug), and `field-hide-label` (the field setting `hide_label` that carries the legacy "hidden label").

## What Changes

- New artisan command `legacy:import` with options `--only`, `--dry-run`, `--fresh` (with confirmation), `--limit`, and `--source`; it reads the old database through a separate `legacy` connection (settings in `.env`) and the old files from `storage/app/public/_old`.
- **Imports:** content types (`node_type` as kind `content`, `block_type` as `block`, `page_type` as `page`), their fields (declared ones plus fields found only in the value tables), nodes of all three kinds with their original `uuid`, `slug`, authors, dates, `views` and values, the files that the data refers to, node and type metadata, and two users (ids 2 and 5). **Does not import:** menus, site settings, activity log, telegram messages, comments, sitemap, OAuth tables, image styles, spam accounts, the unreferenced files, `styles/` and `default/` directories.
- **Value mapping:** titles, rich text (with the old `/storage/...` image addresses rewritten to the new files), plain text, links, YouTube, dates (begin and, only when it differs from the begin, end as values of one multiple `datetime` field `date`), type and block references, images and documents with their order; language `ua` becomes `default`.
- **Idempotent and resumable:** nodes are matched by the old `uuid`, files by a stored map of old file ids to new media; a second run changes nothing but fills what is missing.
- **Report:** counts of created, updated, skipped and failed items, orphans, missing files, and the HTML removed by the sanitizer, printed and written to `storage/app/legacy-import/`.
- **Legacy slugs:** the 478 node slugs that break the strict format (`---`, `_`, a trailing `-`) are kept so the old addresses keep working: the importer may save them, the node form can save an unchanged legacy slug, and the public route accepts them. Creating a new slug stays strict.
- **File profiles:** `image` and `document` accept files up to 30 MB, and `document` accepts `.doc` (`application/msword`).
- **Rich text:** `www.google.com/maps/embed` is added to the allowed iframe hosts (maps in contact blocks).
- **Legacy field settings:** the legacy `hidden_label` becomes the field setting `hide_label`; the legacy `hidden` flag becomes the type's "Public display" (`page_fields` without the hidden fields, saved only for types that have a hidden field and no `page_fields` yet). The legacy `show_label` is ignored: the legacy code never reads it.
- A mapping table `legacy_import_map` (old id to new uuid) is added for idempotency.
- Out of scope: redirects, menu and site settings import, view tracking (`record-node-views`), removing the importer after the migration (a later cleanup).

## Capabilities

### New Capabilities

- `legacy-import`: the import command, what it moves and how values are mapped, idempotency, dry run, fresh start, the report.

### Modified Capabilities

- `file-storage`: "Upload profiles" (30 MB limits for `image` and `document`, `.doc`) and "Checking a file against the profile" (the example limit).
- `content-model`: new requirement for legacy slugs of imported nodes (the strict format is relaxed only for the import).
- `public-site`: new requirement that public addresses accept legacy node slugs.
- `admin-content-nodes`: new requirement that the edit form saves an unchanged legacy slug.

## Impact

- Code: `config/database.php` (connection `legacy`), new `config/legacy.php`, `App\Legacy\*` (importer, steps, value mapper, rich text rewriter, report, source reader, map), `App\Console\Commands\LegacyImportCommand`, a migration for `legacy_import_map`, `ContentNode` / `ContentNodeCreator` (legacy slug mode), `StoreContentNodeRequest` / `UpdateContentNodeRequest` (unchanged legacy slug), `routes/web.php` (segment pattern), `config/files.php`, `config/content.php` (`iframe_hosts`), `.env.example`.
- Tests: new `tests/Feature/Legacy/*` on a small legacy schema in an in-memory SQLite connection; updates to the file-storage, node request and public route tests. This change adds a migration, so the full PHP suite runs once. No e2e: the importer has no UI and the route change is covered by feature tests.
- Docs: new `docs/legacy-import.md` (linked from `docs/README.md`), `docs/file-storage.md` (limits, `.doc`, the PHP limit note), `docs/content-model.md` (legacy slugs), `docs/public-site.md` (addresses), `docs/content-values.md` (iframe hosts).
- Operations: the old database must be reachable from the `app` container (`host.docker.internal:3307`), and the old files copied into `storage/app/public/_old`. Credentials stay in `.env`.
