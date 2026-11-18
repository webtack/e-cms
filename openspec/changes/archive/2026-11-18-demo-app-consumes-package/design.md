# Design

## Context

Observed in the demo: `bootstrap/app.php` wires the CMS (middleware, redirects, 404), `routes/web.php` holds all CMS routes, `app/` holds everything, `config/` holds `content`, `files`, `theme`, `social_networks`, `legacy` plus Laravel defaults, `database/` holds 35 migrations/13 factories/1 seeder, `resources/js|css|views`, `lang/`, `tests/` (with a Legacy part), `docs/`, `e2e/` (the counts are refreshed by the pre-start audit, task 0.1 of `package-scaffold-and-namespace`), `themes/dcculture` with `hooks.php` using `App\Theme\ThemeHooks`, `public/build` (admin) and `public/themes/{default,dcculture}`. The legacy code (`app/Legacy`, `LegacyImportCommand`, `LegacyImportMap`) imports ~13 CMS classes: `Models\{User,ContentField,ContentType,ContentNode,File,Role,RolePermission}`, `Content\{ContentValueWriter,HtmlSanitizer,ContentNodeCreator,ValueOwner,ImageSrcSanitizer,FieldTypes\RichTextType,FieldTypeRegistry}`, `Files\{FileStorage,ImageStyles}`, `Access\AccessResolver`. The container mounts `../e-cms-package` at `/var/www/e-cms-package` (step 1). See proposal.md for motivation.

The demo database (MySQL `ecms`) already contains all CMS migrations in its `migrations` table under the file names the package kept (step 3).

## Goals / Non-Goals

**Goals:** one source of truth; demo identical in behaviour; a clean demo repo.

**Non-Goals:** deployment to production; changing the dcculture design; renaming legacy-import commands; Packagist publication.

## Decisions

**Path repository with symlink.** `repositories: [{"type":"path","url":"../e-cms-package","options":{"symlink":true}}]`, `"webtack/e-cms": "@dev"`. Composer resolves `../e-cms-package` relative to the project root inside the container (`/var/www/html/../e-cms-package` = the mount). Alternative: VCS repository — rejected for the dev loop, edits need commits.

**Order of operations (reversible until committed).**
1. Require the package with `composer update webtack/e-cms --no-scripts`. The flag is required: `post-autoload-dump` runs `package:discover`, which boots the application, and with the old CMS in `app/` and the package both registered it would not boot (duplicate routes, `AppServiceProvider`). Without scripts the package classes are autoloaded, but `bootstrap/cache/packages.php` is not refreshed, so the package provider stays unregistered and the application still boots as before. The asset publish waits until step 4.
2. Rename the host code (user model trait, legacy namespaces, dcculture hooks, e2e class names) while `app/` CMS code still exists. From here until step 3 the application does not boot; this is expected, only `php -l` and `grep` checks run.
3. The switch itself: replace `bootstrap/app.php`, delete `routes/web.php` content, `AppServiceProvider` content, delete CMS `app/` code, CMS config and admin `lang` files (`{en,uk}.json`, `auth.php`, `validation.php`; `dcculture.php` and `months.php` stay), then run `composer dump-autoload` so `package:discover` registers the package provider.
4. Delete CMS database files, frontend, tests, docs; publish `ecms-assets`.
5. Boot: `php artisan about`, `migrate:status`, `route:list` compared with a saved pre-switch route list (the names and URIs must match).
6. Acceptance runs (below).
The pre-switch state is the git working tree; nothing is committed without the owner's request, so rollback is `git restore`/`git clean` on the listed paths. A saved `route:list --json` and a DB dump taken before step 2 are the safety net.

**User model.** `app/Models/User.php` becomes the stock model plus `use HasEcmsAccess` and `implements EcmsUser` (via `ecms:install`'s user-model step, run with `--force` semantics for the model only, or by hand); `AUTH_MODEL` keeps `App\Models\User`. The package must tolerate the existing NOT NULL `users.role_id` of this database (step 3 risk).

**Legacy import stays in the demo.** Only its imports change (`App\Models\X` → `Webtack\Ecms\Models\X` etc.); `app/Legacy`, `LegacyImportCommand`, `LegacyImportMap`, `config/legacy.php`, the `legacy_import_map` migration, `tests/Feature/Legacy` and `docs/legacy-import.md` remain. `LegacyImportCommand` is auto-discovered from `app/Console/Commands` (stock behaviour).

**Config.** The package merges `content`, `files`, `theme`, `social_networks`; the demo needs no copy. `theme.php` previously used `base_path('themes')`; the package derives the application themes path from `base_path('themes')` by default (step 2). `.env.example`: `APP_THEME=dcculture`.

**Theme `default`.** The demo's `themes/default` directory is deleted; the package theme is found through the two theme roots of step 2 and its built assets arrive with `ecms-assets`. The demo does not publish `ecms-theme`. `vite.theme.config.js` and `scripts/theme.mjs` stay in the demo as the copies of the package tag `ecms-theme-tooling` and must be identical to the package files.

**Frontend tooling in the demo.** `package.json` keeps only what the theme build and e2e need: `vite`, `laravel-vite-plugin`, `@tailwindcss/vite`, `tailwindcss`, `sass`, `@playwright/test`, `concurrently`; drops React, Inertia, Tiptap, dnd-kit, Radix/shadcn, TypeScript. The `build` script becomes `theme:build`. `vite.theme.config.js` resolves its paths relative to the demo, as now. The dev server for the admin is the package's (`ECMS_HOT_FILE` → demo `public/vendor/ecms/hot`).

**Assets.** `php artisan vendor:publish --tag=ecms-assets --force` is added to `composer.json` `post-update-cmd`; `.gitignore` gets `/public/vendor/ecms` and `/public/themes/default`. `public/build` is deleted. `public/themes/dcculture` stays as the theme build output (still ignored as today).

**Docs and rules.** `CLAUDE.md` keeps Git, Docker (+ the mount), e2e rules and documentation language; the PHP test section says CMS tests run in the package (`docker compose exec -w /var/www/e-cms-package app vendor/bin/phpunit …`) and demo tests are only Legacy. `docs/README.md` becomes a short index of three demo docs plus a link to the package docs. The OpenSpec `config.yaml` rules about docs paths are adjusted to the demo's remaining `docs/`.

**OpenSpec.** After steps 1–6 are archived (their delta specs are synced to the demo's main specs), a final re-copy of `openspec/specs` and `openspec/changes/archive` into the package completes its history; then the demo's main specs for package capabilities are removed (the six demo specs stay: `e2e-testing`, `legacy-import`, `dcculture-theme`, `dcculture-accessibility`, `dcculture-events`, `demo-application`). This change's own archive happens in the demo and is copied once more. Because the re-copy needs this change in `archive/`, it is not a task of this change; see "Post-archive follow-up".

### Post-archive follow-up

Done by the owner after `/opsx:archive` of this change (the archive syncs the deltas of this change into the demo's main specs and commits):

1. Re-copy `openspec/specs` (without the six demo specs) and `openspec/changes/archive` into the package and regenerate `docs/HISTORY.md` (`node scripts/history.mjs` in the package).
2. Remove the package capability specs from the demo's `openspec/specs`, keeping the six demo specs.
3. Verify: `openspec list --specs` in the demo shows exactly the six demo specs, and the package archive count equals the demo's.

**Acceptance.** `bootstrap/app.php` no longer prepends `ResolvePublicLanguage`; the package provider registers it (step 2). Full `npm run test:e2e` (justified by the rule: bootstrapping, authentication, shell/sidebar change), `docker compose exec app php artisan test` (demo tests: Legacy only), `php artisan theme:check` with `APP_THEME=dcculture`, `php artisan route:list` equal to the saved one, `migrate:status` with nothing pending, manual look at the public site.

Docs touched: `docs/README.md`, `docs/legacy-import.md`, `docs/theme-dcculture.md`, `docs/e2e-testing.md`, `README.md`.

## Risks / Trade-offs

- [The switch is large and a mid-way state does not boot] → Single scripted switch, a saved DB dump and route list, git as rollback, and the order above.
- [Removing `app/` deletes an uncommitted work-in-progress] → The working tree currently has uncommitted dcculture edits (`themes/dcculture`, `blocks/`); they are in files that stay, and the owner is asked to commit or stash before the switch.
- [Behaviour differences from `role_id` nullability or middleware order] → Covered by the full e2e run and `route:list` comparison.
- [The `public/themes/default` published build differs from the former one] → `theme:check` and an e2e public-site run.
- [Symlinked package confuses Vite/IDE in the demo] → Admin builds happen in the package only; the demo's Vite never reads the symlinked sources.
- [Legacy import silently breaks on a missed namespace] → The Legacy PHPUnit tests (9 files) run in this change.

## Open Questions

### Audit 2026-11-17 (series deferred), resolved

- **e2e uses CMS class names.** 22 files under `e2e/` (including `global-setup.js`, which creates the e2e administrator through `App\Models\User` and `App\Models\Role`) hold 379 `App\…` references inside `tinker` code strings (measured on 2026-11-18; 52 of them are the host classes that stay, so about 327 are renamed). Resolved: task 2.4 renames them to `Webtack\Ecms\…` (host classes `App\Models\User` and `App\Models\LegacyImportMap` stay). Without it the full e2e run of task 5.2 fails.
- **Demo-only specs are six.** `e2e-testing`, `legacy-import`, `dcculture-theme`, `dcculture-accessibility`, `dcculture-events` stay in the demo, and this change adds `demo-application`. Resolved (2026-11-18): the proposal and the post-archive follow-up list all six; earlier text said five and left out `demo-application`.
- **Legacy import.** Its CMS imports also include `App\Models\User` and `App\Models\LegacyImportMap` (host classes, unchanged). The `legacy-import` delta of this change was rebased on the main spec after `--type` and the add-only behaviour were added.
- **Demo theme rename.** `themes/dcculture` has a `hooks/` directory and about 34 files with `App\`: the executable part is the `use` imports in `hooks.php` and `hooks/*.php` (without them the theme fails), the rest are template docblocks (IDE hints only). Task 2.2 covers both with one scripted rename and a `grep`.
- **Host user model.** After the contract/trait decision of step 1 (`EcmsUser extends Authenticatable`, `avatarUrl()` and `MEDIA_OWNER` provided by the package), the demo `User` drops its own copies (task 2.3).
- **`users.role_id` stays NOT NULL in the demo database.** Checked on 2026-11-18: the demo column is `NOT NULL`, while the package migration `2026_11_03_100000_create_roles_and_permissions` leaves it nullable. The migration is already recorded as run in the demo, so it does not run again and the column stays `NOT NULL`. This is harmless as long as every package path that creates a user sets a role: `StoreUserRequest` requires `role_id` and `AdminSeeder` assigns it through `forceFill`. Other creation paths were not searched; the full e2e run of task 5.2 covers them.
- **Counts.** Not recorded here: they go stale quickly and the audit of step 1 refreshes them.

#### Pre-start audit (2026-11-17, task 0.1)

Baseline commit: `45d2095`. `git diff --stat 124812e..45d2095` outside `openspec/` is empty: no code, config, route, middleware or docs changed since the security-hardening addendum, so the facts and counts of this series stay valid (532 files reference `App\` in `app/`, `config/`, `database/`, `routes/`, `themes/`, `resources/`, `tests/` and `bootstrap/` (564 with `docs/`); the next audit starts from this commit).
