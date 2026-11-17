# Tasks

## 0. Pre-start audit and freeze

- [x] 0.1 Run a short audit: list the commits since the last audit commit of this series (`git log --stat <commit>..HEAD`), compare them with the artifacts of steps 1–7 (new classes typed on `User`, config files, middleware, routes, reserved paths, counts) and update the artifacts. Verify: the audit notes in the `design.md` files name the new baseline commit.
- [x] 0.2 Record the freeze rule from design.md in the demo's `CLAUDE.md` (no new functionality in the shared code and the shared topic docs until step 7 is archived; fixes are re-applied to the package with the rename script). Verify: the rule is present and names the frozen directories.

## 1. Package skeleton and tooling access

- [x] 1.1 Create `/Users/ruslan/Documents/WebSites/e-cms/e-cms-package` with `composer.json` (`webtack/e-cms`, PSR-4 `Webtack\Ecms\` → `src/`, `Webtack\Ecms\Database\Factories\` / `Seeders\` → `database/…`, `Webtack\Ecms\Tests\` in `autoload-dev`, the runtime dependencies from the proposal). Verify: `docker compose exec app composer validate --working-dir=/var/www/e-cms-package` reports a valid manifest (after task 1.2).
- [x] 1.2 Mount `../e-cms-package` into the `app` service at `/var/www/e-cms-package` in `docker-compose.yml`, recreate the container and verify `docker compose exec app ls /var/www/e-cms-package` lists `composer.json`.
- [x] 1.3 Initialise the package git repository (no history import) with a `.gitignore` (vendor, node_modules, build caches) and verify `git status` is clean and `git log` is empty or has only the initial commit when the owner asks for it.

## 2. Copy and rename code

- [x] 2.1 Write the rename script (outside the package, in the scratchpad) that copies `app/` → `src/`, `database/{factories,seeders}`, `database/migrations`, `config/{content,files,theme,social_networks,limits}.php`, `lang/{en,uk}.json` and `lang/{en,uk}/{auth,validation}.php`, `routes/web.php`, `resources/`, `themes/default/` and `docs/*.md` (minus `e2e-testing.md`, `legacy-import.md`, `theme-dcculture.md` and `deployment.md`) into the package and applies the namespace mapping from design.md (in `docs/` only `App\` becomes `Webtack\Ecms\`; demo wording is left for step 6), excluding `Models/User.php`, `Legacy/`, `Console/Commands/LegacyImportCommand.php`, `Models/LegacyImportMap.php`, `config/legacy.php`, Laravel-default `users`/`cache`/`jobs` migrations, `themes/dcculture` and `lang/{en,uk}/{dcculture,months}.php`. Verify: re-running the script on an unchanged source yields no diff (idempotent).
- [x] 2.2 Run the script and verify with a search that no `App\` reference remains in `src/`, `config/`, `database/`, `routes/`, `themes/default/` and `resources/views` (strings, docblocks and class lists included).
- [x] 2.3 Update `themes/default/hooks.php` and the docblocks of `themes/default/views` to `Webtack\Ecms\…` and verify `php -l` passes on every PHP file of the package (`docker compose exec app sh -c 'find /var/www/e-cms-package -name "*.php" -not -path "*/vendor/*" | xargs -n1 php -l'`).

## 3. Decouple from the host user and bind factories

- [x] 3.1 Add `Support/UserModel`, `Contracts/EcmsUser` (extends `Illuminate\Contracts\Auth\Authenticatable`; declares `role()`, `favoriteTypes()`, `avatarUrl()` and the constant `MEDIA_OWNER`; `role_id`/`is_active`/`locale` as `@property`) and `Concerns/HasEcmsAccess` (with `role()`, `favoriteTypes()`, `avatarUrl()` and the `is_active` cast support), documented with English docblocks and `@example`. Verify: the three classes autoload (`class_exists`) and a fixture class using the trait and implementing the contract satisfies `instanceof Authenticatable`.
- [x] 3.2 Replace every `App\Models\User` reference (policies, `AccessResolver`, `LastAdminGuard`, `SidebarNavigation`, `YoutubeLookup`, `FileStorage`, `UserController`, `UpdateUserRequest`, `Policies/UserPolicy` (with its `Gate::policy(User::class, …)` binding, which becomes `UserModel::class()`), `Files/AttachedFileGuard`, `Files/UsageAccess`, relations in `Role`, `ContentNode`, `File`, `UserFactory`, `ContentNodeFactory`, `DatabaseSeeder`, the `2026_10_31_100000…` migration, `config/auth.php` is not part of the package) with the resolver or the contract. Verify: a search for `Models\User` in the package returns only the `UserFavoriteType`/`EcmsUser` hits that are intended.
- [x] 3.3 Add `#[UseFactory(...)]` to every model with `HasFactory`. Verify: with the installed vendor of the demo container, a PHP one-liner loading the package autoloader reports for each model that `class_exists(<factory>)` is true and `(new <Model>)->newFactory()`-resolution points to `Webtack\Ecms\Database\Factories\`.
- [x] 3.4 Run Pint on the package (`docker compose exec app vendor/bin/pint /var/www/e-cms-package --format agent`) and verify it reports no remaining style issues.

## 4. Verification and documentation

- [x] 4.1 Add an autoload check script (scratchpad, not shipped) that `class_exists`/`interface_exists`/`trait_exists` for every file under `src/` and verify it reports zero failures.
- [x] 4.2 Write `docs/package-structure.md` in the package (layout table, namespace mapping, exclusion list, user-model contract, copy-not-move rule), add it to the index of the copied `docs/README.md` (the full rewrite of the index is step 6), and cross-link it from `docs/site-themes.md` and `docs/theme-templates.md` with the renamed `ThemeHooks` namespace. Verify: relative links resolve (`grep` each link target exists).
- [x] 4.3 PHP tests: no demo PHP tests are run (the demo code is untouched); report in the summary that the package suite is introduced in change `package-tests-on-testbench`.
