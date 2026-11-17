# Design

## Context

`App\Content\ContentTypeCloner` clones inside one `DB::transaction` and is called from `ContentTypeController::clone` (`docs/admin-content-types.md`, section "Cloning"). Themes live in `themes/<name>/` (`docs/site-themes.md`); the type key is a template path segment in `{t}/list`, `{t}/preview`, `{t}/details`, `{t}/page`, `{t}/field-*` ([theme-templates.md](../../../docs/theme-templates.md), [public-site.md](../../../docs/public-site.md#template-candidates)) and in `blocks/block-{t}` / `blocks/block-{t}--{slug}`. See proposal.md for motivation.

## Goals / Non-Goals

**Goals:**
- The clone keeps the public look of the source in every theme that overrides it at type level.
- Keep the all-or-nothing guarantee already given for the database part.

**Non-Goals:**
- Reading or rewriting file content, renaming CSS classes or partials.
- `pages/page-{t}`, node-bound files, hooks that name the key.
- Copying on any later change of the key (keys are immutable).

## Decisions

1. **A separate collaborator copies the files; the cloner calls it as the last step inside the transaction.** The theme directory root comes from `config('theme.path')`, and every direct subdirectory is a theme (no dependence on `APP_THEME`, so the result is the same whatever the active theme). Alternative: copy only the active theme: rejected, because the clone would depend on `.env`.

2. **Order: database work first, files last.** Files are copied after the type, fields and settings are saved but before the transaction commits. Any exception rolls the database back, and the cleanup below removes the files. The rare failure of the commit itself after a successful copy is covered by the same cleanup in the `catch`.

3. **Selection rules (names only, no content).** In `views/{old}/` skip files matching `--<digits>` before the extension (`preview--7.blade.php`). In `views/blocks/` take exactly `block-{old}.blade.php`, never `block-{old}--*`. Sub-directories of `views/{old}/` are copied recursively with the same file rule. Alternative: copy `--{id}` and `--{slug}` files too: rejected, they are tied to one node that the clone does not have.

4. **No overwrite, fail fast.** Before copying, the collaborator plans the full list of target paths and fails if any exists. Then it copies and records each created path. On any error it removes exactly the recorded files and directories it created (never a pre-existing path) and rethrows. Alternative: skip existing targets with a warning: rejected, it hides a stale directory of a deleted type.

5. **Error to the user.** A dedicated exception is thrown; the controller catches it and returns `back()->withInput()` with an `error` message (translated with `__()`), like the other admin guards. The message names the failed path's theme and file, not the server path of the project root.

6. **Plain file copy.** `File::copy` per file, so bytes and nothing else are copied; file permissions follow the process umask.

## Risks / Trade-offs

- [The web process cannot write to `themes/` (read-only image, mount permissions)] → the clone fails with the explained error and nothing is created; the container mount for `themes/` must be writable where cloning is used. To be checked in the first task before coding.
- [Copied templates still mention the old key in comments, class names or includes] → accepted: content is copied as is by decision; the editor adjusts what is needed.
- [File and database state cannot be one real transaction] → the files are the last step and are cleaned up on failure; a crash of the PHP process between copy and commit may leave files of a type that was not created, which then block a later clone with the same key (the "target exists" error names them).

- [Checked] `themes/` is writable from the `ecms_app` container (`test -w themes` → `ok`; the process runs as root, directory owned by root). Production mounts must be checked where cloning is used.

## Docs

`docs/admin-content-types.md` ("Cloning"), `docs/site-themes.md` (theme structure: note on clone), cross-link to `docs/theme-templates.md#candidates`.
