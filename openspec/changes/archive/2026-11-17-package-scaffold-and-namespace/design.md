# Design

## Context

The demo application (`e-cms-intertia`) holds the CMS in `app/` (namespace `App\`, 400 files reference it), `config/`, `database/`, `lang/`, `resources/`, `routes/`, `themes/`. See proposal.md for why the code is being packaged. Observed facts that shape this step:

- Models use `HasFactory` with `@use HasFactory<XFactory>`; factory discovery by name would look for `Database\Factories\` (11 models plus `User`).
- `App\Models\User` is referenced from ~25 places: policies, `AccessResolver`, `LastAdminGuard`, `SidebarNavigation`, `YoutubeLookup`, `FileStorage`, `UserController`, `Gate::define(... fn (User $user))` in `AppServiceProvider`, `config/auth.php`, the `Role::users()`/`ContentNode::user()`/`File::user()` relations, `UserFactory`, `ContentNodeFactory`, `DatabaseSeeder` and the migration `2026_10_31_100000_add_service_attributes_to_content_nodes_table`.
- `config/files.php` and `config/content.php` list classes by `::class` imports; `themes/*/hooks.php` and template docblocks mention `App\Theme\…`, `App\Models\…`.
- Paths: `base_path('themes')` (`config/theme.php`), `lang_path("{locale}.json")` (`HandleInertiaRequests`), `public_path('themes|app/…')` (`ThemeManager`) and `database_path('migrations/…')` in tests. These are resolved in later steps; this step only records them.
- The Docker `app` container mounts only the demo directory (`./:/var/www/html`).
- Legacy import (`app/Legacy`, `LegacyImportCommand`, `LegacyImportMap`, `config/legacy.php`) is specific to the owner's old site.

## Goals / Non-Goals

**Goals:**
- A package directory whose source compiles and autoloads under `Webtack\Ecms\`.
- No class in the package depends on the host's `App\` namespace or on a concrete user class.
- The demo application keeps running unchanged while the package is built (copy, not move).

**Non-Goals:**
- Service provider, routes, middleware, migrations loading, installer, built frontend, tests: steps 2–5.
- Renaming config keys (`content.*`, `files.*`, `theme.*` stay as they are).
- Changing any runtime behaviour.

## Decisions

**Copy, then rename (script-assisted), not `git mv`.** The package is a new repository without the demo's history (history is kept by OpenSpec, see step 6). Copying leaves the demo as a working safety net until step 7. Alternative: move and break the demo now — rejected, it removes the only way to compare behaviour.

**Mechanical rename with a verified mapping.** One script (kept out of the package) applies: `namespace App\` → `namespace Webtack\Ecms\`; `use App\…` → `use Webtack\Ecms\…`; `Database\Factories` → `Webtack\Ecms\Database\Factories`; `Database\Seeders` → `Webtack\Ecms\Database\Seeders`. Afterwards a search for `App\\` (including strings and docblocks) must be empty. `app/` maps to `src/` with the same sub-directories (`src/Models`, `src/Http/Controllers/Admin`, …). Alternative: manual refactor with an IDE — rejected, error-prone over 400 files.

**User decoupling via a resolver, a contract and a trait.**
- `Webtack\Ecms\Support\UserModel::class()` returns `config('auth.providers.users.model')`; relations use it (`belongsTo(UserModel::class(), 'user_id')`).
- `Webtack\Ecms\Contracts\EcmsUser` **extends** `Illuminate\Contracts\Auth\Authenticatable`. Code that works with a CMS user (policies, `AccessResolver`, `LastAdminGuard`, …) type-hints `EcmsUser` alone. `Authenticatable` stays only where the user is arbitrary (`Auth::user()` before an `instanceof EcmsUser` check). The contract declares the methods `role()`, `favoriteTypes()` and `avatarUrl()` and the constant `MEDIA_OWNER`; `role_id`, `is_active` and `locale` are attributes, which an interface cannot declare under PHP ^8.3, so they are documented as `@property` in the contract docblock.
- `Webtack\Ecms\Concerns\HasEcmsAccess` provides `role()`, `favoriteTypes()`, `avatarUrl()` and the `is_active` cast helper; the host model adds it (installer in step 3 does this for the default `User`).
- Alternative: ship `Webtack\Ecms\Models\User` and override `auth.providers.users.model` — rejected, it breaks hosts that already have their own user model.
- The contract and trait land here (not in step 2) because the moved code and the factories cannot compile coherently without them; step 2 only wires them into the provider and `Gate`.

**Explicit factory binding.** Each model gets `#[UseFactory(XFactory::class)]` (Laravel attribute) next to `@use HasFactory<…>`. Alternative: a global `Factory::guessFactoryNamesUsing` in the provider — rejected, hidden global state and not visible in the model.

**Excluded from the package:** `App\Models\User`, legacy import, Laravel-default `users`/`cache`/`jobs` migrations (the hosts already have them), `themes/dcculture`, `e2e/`, `lang/{en,uk}/dcculture.php` and `lang/{en,uk}/months.php` (read only by `dcculture` templates). The rule: the package copies what belongs to the admin panel and the theme `default`; everything else stays in the demo. From `lang/` the package takes `{en,uk}.json` (admin UI), `{en,uk}/auth.php` (`LoginRequest`) and `{en,uk}/validation.php` (rules the admin uses). The migration `2026_10_31_100000_add_service_attributes…` references `User`; in the package it uses the resolver, and the migration set is finalised in step 3.

**Docker visibility.** `docker-compose.yml` mounts `../e-cms-package` into the `app` container at `/var/www/e-cms-package`. Without it PHP tooling cannot run against the package (the project rule forbids host PHP), and a Composer path repository symlink would dangle inside the container in step 7.

**Layout of `themes/default`.** It is copied into the package root as `themes/default`; how `ThemeManager` finds it (package themes directory plus application themes directory) is decided in step 2.

## Risks / Trade-offs

- [Two copies of the code diverge while the demo still has `app/`] → The freeze rule in the Decision below; any fix made in the demo in the meantime is re-applied to the package by repeating the rename script on the changed files (the script is idempotent).
- [A string class name is missed by the rename] → Search for `App\\\\` and `'App` in strings, plus an autoload check that instantiates/loads every class (`class_exists`) and `php -l` on all files.
- [Existing themes break on `App\Theme\ThemeHooks`] → The demo theme `dcculture` is updated in step 7; `UPGRADING.md` (step 6) documents the one-line change.
- [Factories not found after rename] → A test in step 5 asserts `Model::factory()` for every model; for now `class_exists` plus manual `factory()` call in `tinker` inside the container.
- [`Authenticatable` hides `role_id` etc. from static analysis] → The contract documents the required properties; runtime behaviour is unchanged.

## Open Questions

- None. (Resolved: `EcmsUser` extends `Authenticatable`, see "User decoupling" above. The alternative, a standalone contract, would force intersection types `Authenticatable&EcmsUser` or `instanceof` checks in the ~15 classes of task 3.2.)

### Audit 2026-11-17 (series deferred)

The series was planned on 2026-11-10; 47 changes were archived in the demo after that, so the observed facts above are stale. Re-check them before this change starts. Findings that affect this change:

- **Series strategy (resolved 2026-11-17, see the Decision below).** The "demo `app/` is frozen during steps 1–7" risk mitigation did not hold: 53 new classes were added to `app/` in one week. Before restarting, decide between a feature freeze for the duration of the series, a "move, not copy" step 1, or a fresh audit right before the start.
- **User coupling grew.** The host `User` now has an `avatar` column, `avatarUrl()` (called from `HandleInertiaRequests`, `UserController`, `ProfileController`, `MediaController`, `ContentNodeController`) and the constant `User::MEDIA_OWNER` (used by `UserController`, `ProfileController`). `HasEcmsAccess`/`EcmsUser` must provide them. New `App\Models\User` references to add to task 3.2: `Dashboard/DashboardData`, `Admin/ProfileController`; `Files/Owners/UserOwner` (registered in `config/files.php`) reads the `users` table.
- **Counts.** Files referencing `App\`: 400 → 517 (532 after the security-hardening addendum below). Models with factories: 13 factories now (new `AppLanguageFactory`, `MenuItemTranslationFactory`, `TermTranslationFactory`).
- **Themes.** `themes/dcculture` now has a `hooks/` directory and many templates with `App\…` docblocks (`PublicData`, `ContentNodeQuery`, `SiteSettings`, …); the rename of the demo theme in step 7 is larger than one `hooks.php` line.

**Documentation follows the code.** The topic docs are copied into the package in this step with the same rename script (not in step 6), because steps 2 to 5 edit them (`site-themes.md`, `public-site.md`, `admin-auth.md`, `admin-i18n.md`, `admin-shell.md`, `access-control.md`, the index `README.md`) and a late copy would overwrite those edits. Step 6 only cleans the demo wording, adds the new guides and writes the README. The four demo-only files (`e2e-testing.md`, `legacy-import.md`, `theme-dcculture.md` and `deployment.md`) are not copied.

Docs: the new `docs/package-structure.md` (package) describes the layout, namespace rules and the exclusion list; `docs/site-themes.md` and `docs/theme-templates.md` are updated for the new `ThemeHooks` namespace.

#### Addendum (after security hardening, 2026-11-17)

- **More user-typed classes.** `Policies/UserPolicy` (bound with `Gate::policy(User::class, …)` in `AppServiceProvider`; the binding must use `UserModel::class()`), `Files/AttachedFileGuard` and `Files/UsageAccess` type-hint `App\Models\User`; `AccessResolver::isAdministrator()` is new. Task 3.2 lists them.
- **New config file.** `config/limits.php` (request limits, `LIMIT_*` env) is part of the package config set; task 2.1 copies it. Its limiters are registered in step 2.
- **Counts.** Files referencing `App\`: 532.

#### Decision (2026-11-17): series strategy

Owner decision: **feature freeze plus a quick run**, keeping "copy, then rename".

- **Freeze.** From the start of step 1 until step 7 is archived, the demo's `app/`, `config/`, `database/`, `routes/`, `lang/`, `resources/js`, `themes/default` and the topic docs in `docs/` receive no new functionality. Only fixes that cannot wait are allowed; each one is re-applied to the package by re-running the idempotent rename script on the changed files, and the commit message names the package follow-up. Demo-only code (`themes/dcculture`, legacy import, `e2e/`, docs of those) is not frozen. A doc fix made in the demo is re-applied to the package copy the same way.
- **Pre-start audit.** Immediately before the first copy, a short audit compares `git log` since the last recorded audit commit with the artifacts (new classes, config files, middleware, routes, counts) and updates them; task 0.1.
- **Rejected:** "move, not copy" (the demo would stay broken until step 7 and the safety net is lost) and "no freeze with an audit before every step" (the most repeated work and the highest risk of missed changes).

#### Pre-start audit (2026-11-17, task 0.1)

Baseline commit: `45d2095`. `git diff --stat 124812e..45d2095` outside `openspec/` is empty: no code, config, route, middleware or docs changed since the security-hardening addendum, so the facts and counts of this series stay valid (532 files reference `App\` in `app/`, `config/`, `database/`, `routes/`, `themes/`, `resources/`, `tests/` and `bootstrap/` (564 with `docs/`); the next audit starts from this commit).
