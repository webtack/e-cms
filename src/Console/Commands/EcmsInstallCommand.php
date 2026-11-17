<?php

namespace Webtack\Ecms\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use ReflectionClass;
use RuntimeException;
use Throwable;
use Webtack\Ecms\Concerns\HasEcmsAccess;
use Webtack\Ecms\Contracts\EcmsUser;
use Webtack\Ecms\Database\Seeders\AdminSeeder;
use Webtack\Ecms\Models\Role;
use Webtack\Ecms\Support\UserModel;

/**
 * Installs the CMS in a host application and is safe to run again: every changing step reports "done" (did the work) or
 * "already done" (nothing to do), and a repeated run changes nothing.
 *
 * Steps: (1) check the user model (reported as "ok", it changes nothing) (table `users`, integer key); (2) publish `config/*` (`ecms-config`);
 * (3) run the migrations; (4) make the user model use {@see EcmsUser} and {@see HasEcmsAccess} (a default `User`
 * is edited, any other model gets printed instructions and the command exits with a failure status after the
 * remaining steps); (5) create or promote the administrator ({@see AdminSeeder}); (6) create the storage link; (7) publish the admin assets (`ecms-assets`).
 *
 * The administrator email and password come from `--admin-email` / `--admin-password`, then `ADMIN_EMAIL` /
 * `ADMIN_PASSWORD`, then a prompt. There are no defaults in any environment: without interaction and without the
 * values the step fails naming them and creates nothing. An existing account is promoted only after confirmation
 * (or `--force`) and keeps its password unless one is given.
 *
 * @example
 *   php artisan ecms:install
 *   php artisan ecms:install --admin-email=owner@example.com --admin-password=secret-password --no-interaction
 *   php artisan ecms:install --admin-email=existing@example.com --force   // promote an existing account
 */
#[Signature('ecms:install
    {--admin-email= : Email of the administrator (default: ADMIN_EMAIL, then a prompt)}
    {--admin-password= : Password of a new administrator (default: ADMIN_PASSWORD, then a prompt)}
    {--force : Overwrite published files, run migrations in production and promote an existing account without asking}')]
#[Description('Install the CMS: publish the config, migrate, prepare the user model, create the administrator')]
class EcmsInstallCommand extends Command
{
    /** The trait line of the default Laravel user model; the installer adds the CMS trait to it. */
    private const STOCK_TRAITS = 'use HasFactory, Notifiable;';

    private bool $failed = false;

    public function handle(): int
    {
        // The command object is reused when the command is called again in the same process (a test, a queued job).
        $this->failed = false;

        $this->components->info('Installing the CMS');

        if (! $this->checkUserModel()) {
            return self::FAILURE;
        }

        $this->publishConfig();

        // Later steps read the migrated tables.
        if (! $this->migrate()) {
            return self::FAILURE;
        }

        $this->prepareUserModel();
        $this->setUpAdministrator();
        $this->linkStorage();
        $this->publishAssets();

        if ($this->failed) {
            $this->newLine();
            $this->components->error('The installation is incomplete; fix the steps marked above and run the command again.');

            return self::FAILURE;
        }

        $this->newLine();
        $this->components->info('The CMS is installed. Sign in at '.url('/login'));

        return self::SUCCESS;
    }

    /**
     * Step 1: the package supports a user table named `users` with an integer primary key. Checked before anything
     * is created.
     */
    private function checkUserModel(): bool
    {
        $class = UserModel::class();

        if (! is_string($class) || ! class_exists($class)) {
            return $this->failStep('User model', 'auth.providers.users.model does not name an existing class.');
        }

        $model = new $class;
        $problem = match (true) {
            $model->getTable() !== 'users' => "the table is [{$model->getTable()}], the package supports only [users]",
            ! $model->getIncrementing() || $model->getKeyType() !== 'int' => 'the primary key is not an auto-incrementing integer (UUID and string keys are not supported)',
            default => $this->tableKeyProblem($model->getConnectionName(), $model->getKeyName()),
        };

        if ($problem !== null) {
            return $this->failStep('User model', "{$class}: {$problem}.");
        }

        $this->components->twoColumnDetail('User model', "<fg=green>ok</> {$class} uses the table [users] with an integer key");

        return true;
    }

    /**
     * When the users table already exists, its key column must be an integer too (the model may only claim it).
     */
    private function tableKeyProblem(?string $connection, string $key): ?string
    {
        $schema = Schema::connection($connection);

        if (! $schema->hasTable('users')) {
            return null;
        }

        $type = strtolower($schema->getColumnType('users', $key));

        return str_contains($type, 'int') ? null : "the column users.{$key} is [{$type}], an integer is required";
    }

    /** Step 2: `config/*.php`; existing files stay unless `--force`. */
    private function publishConfig(): void
    {
        if (! $this->option('force') && file_exists(config_path('ecms.php'))) {
            $this->already('Configuration', 'config/ecms.php exists (use --force to overwrite the published files)');

            return;
        }

        $this->call('vendor:publish', ['--tag' => 'ecms-config', '--force' => (bool) $this->option('force')]);
        $this->done('Configuration', 'published with the tag ecms-config');
    }

    /** Step 3: the host's and the package's migrations (the provider registers the package path). */
    private function migrate(): bool
    {
        $migrator = app('migrator');
        $paths = array_merge($migrator->paths(), [database_path('migrations')]);
        $ran = $migrator->repositoryExists() ? $migrator->getRepository()->getRan() : [];
        $pending = array_diff(
            array_map($migrator->getMigrationName(...), array_keys($migrator->getMigrationFiles($paths))),
            $ran,
        );

        if ($pending === []) {
            return $this->already('Migrations', 'nothing to migrate');
        }

        $status = $this->call('migrate', ['--force' => (bool) $this->option('force')]);

        if ($status !== self::SUCCESS) {
            return $this->failStep('Migrations', 'the migrate command failed');
        }

        return $this->done('Migrations', count($pending).' migration(s) ran');
    }

    /**
     * Step 4: the user model gets the contract and the trait. Only the default Laravel `User` shape is edited;
     * the edit is idempotent (the trait name is checked first).
     */
    private function prepareUserModel(): void
    {
        $class = UserModel::class();
        $reflection = new ReflectionClass($class);

        $file = $reflection->getFileName();
        $source = $file && is_writable($file) ? File::get($file) : null;

        // The class in memory may predate an edit made by an earlier run of this process, so the file is read too.
        $prepared = ($reflection->implementsInterface(EcmsUser::class) && in_array(HasEcmsAccess::class, class_uses_recursive($class), true))
            || ($source !== null && str_contains($source, 'HasEcmsAccess') && str_contains($source, 'EcmsUser'));

        if ($prepared) {
            $this->already('User model preparation', 'already uses HasEcmsAccess');

            return;
        }

        $edited = $source === null ? null : $this->editStockModel($source, $reflection->getShortName());

        if ($edited === null) {
            $this->failStep('User model preparation', "{$class} is not the default shape and was not edited.");
            $this->line('  Make the model implement the contract and use the trait:');
            $this->line('    use Webtack\Ecms\Concerns\HasEcmsAccess;');
            $this->line('    use Webtack\Ecms\Contracts\EcmsUser;');
            $this->line("    class {$reflection->getShortName()} extends Authenticatable implements EcmsUser   // keep your other interfaces");
            $this->line('    {');
            $this->line('        use HasEcmsAccess;   // next to your other traits');
            $this->line('    }');

            return;
        }

        File::put($file, $edited);
        $this->done('User model preparation', 'added EcmsUser and HasEcmsAccess to '.$reflection->getShortName());
    }

    /**
     * The source of a default Laravel user model with the contract and the trait added, or null when the
     * file does not have the default shape (so it must not be edited).
     */
    private function editStockModel(string $source, string $name): ?string
    {
        $classLine = '/^class '.preg_quote($name, '/').' extends Authenticatable((?: implements [\w\\\\, ]+)?)\s*$/m';

        if (substr_count($source, self::STOCK_TRAITS) !== 1
            || preg_match_all($classLine, $source) !== 1
            || ! preg_match('/^use [^;]+;$/m', $source)) {
            return null;
        }

        $source = preg_replace_callback($classLine, function (array $m) use ($name) {
            $implements = $m[1] === '' ? ' implements EcmsUser' : $m[1].', EcmsUser';

            return "class {$name} extends Authenticatable{$implements}";
        }, $source);
        $source = str_replace(self::STOCK_TRAITS, 'use HasEcmsAccess, HasFactory, Notifiable;', $source);

        // The imports go after the last one, so existing ones keep their order.
        preg_match_all('/^use [^;]+;$/m', $source, $imports, PREG_OFFSET_CAPTURE);
        $last = end($imports[0]);
        $at = $last[1] + strlen($last[0]);

        return substr($source, 0, $at)."\nuse Webtack\\Ecms\\Concerns\\HasEcmsAccess;\nuse Webtack\\Ecms\\Contracts\\EcmsUser;".substr($source, $at);
    }

    /** Step 5: create the administrator, or promote an existing account. */
    private function setUpAdministrator(): void
    {
        $email = $this->option('admin-email') ?: env('ADMIN_EMAIL');
        $password = $this->option('admin-password') ?: env('ADMIN_PASSWORD');
        $adminId = Role::admin()->id;

        // Nothing was asked for and an administrator exists: a repeated run.
        if (! $email && ! $password && UserModel::query()->where('role_id', $adminId)->exists()) {
            $this->already('Administrator', 'an administrator exists');

            return;
        }

        $interactive = $this->input->isInteractive();

        if (! $email && $interactive) {
            $email = $this->ask('Administrator email');
        }

        if (! $email) {
            $this->failStep('Administrator', 'ADMIN_EMAIL (or --admin-email) is required and was not given; no administrator was created.');

            return;
        }

        $existing = UserModel::query()->where('email', $email)->first();

        if ($existing === null && ! $password && $interactive) {
            $password = $this->secret("Password of the new administrator [{$email}]");
        }

        if ($existing === null && ! $password) {
            $this->failStep('Administrator', 'ADMIN_PASSWORD (or --admin-password) is required and was not given; no administrator was created.');

            return;
        }

        if ($existing !== null && (int) $existing->role_id !== $adminId && ! $this->option('force')) {
            $confirmed = $interactive && $this->confirm("The account [{$email}] exists. Give it the administrator role?", false);

            if (! $confirmed) {
                $this->failStep('Administrator', "the account [{$email}] exists; confirm the promotion or run with --force. Nothing was changed.");

                return;
            }
        }

        try {
            $result = (new AdminSeeder)->apply($email, $password);
        } catch (RuntimeException $e) {
            $this->failStep('Administrator', $e->getMessage());

            return;
        }

        match ($result) {
            AdminSeeder::CREATED => $this->done('Administrator', "created [{$email}] with a verified email"),
            AdminSeeder::PROMOTED => $this->done('Administrator', "[{$email}] is an administrator now"),
            default => $this->already('Administrator', "[{$email}] is an administrator"),
        };
    }

    /** Step 6: `public/storage`, used by the public disk (uploaded files). */
    private function linkStorage(): void
    {
        if (file_exists(public_path('storage')) || is_link(public_path('storage'))) {
            $this->already('Storage link', 'public/storage exists');

            return;
        }

        try {
            $status = $this->callSilently('storage:link');
        } catch (Throwable $e) {
            $this->failStep('Storage link', $e->getMessage());

            return;
        }

        if ($status !== self::SUCCESS) {
            $this->failStep('Storage link', 'php artisan storage:link failed');

            return;
        }

        $this->done('Storage link', 'public/storage created');
    }

    /**
     * Step 7: the compiled admin frontend and the built assets of the theme `default` (`ecms-assets`), so the host needs
     * no Node. The files are the package's, so an existing copy is refreshed on every run.
     */
    private function publishAssets(): void
    {
        try {
            $status = $this->callSilently('vendor:publish', ['--tag' => 'ecms-assets', '--force' => true]);
        } catch (Throwable $e) {
            $this->failStep('Admin assets', $e->getMessage());

            return;
        }

        if ($status !== self::SUCCESS) {
            $this->failStep('Admin assets', 'php artisan vendor:publish --tag=ecms-assets failed');

            return;
        }

        $this->done('Admin assets', 'published to public/vendor/ecms with the tag ecms-assets');
    }

    private function done(string $step, string $detail): bool
    {
        $this->components->twoColumnDetail($step, "<fg=green>done</> {$detail}");

        return true;
    }

    private function already(string $step, string $detail): bool
    {
        $this->components->twoColumnDetail($step, "<fg=gray>already done</> {$detail}");

        return true;
    }

    /** Reports a failed step; always returns false so a precondition can `return $this->failStep(...)`. */
    private function failStep(string $step, string $detail): bool
    {
        $this->failed = true;
        $this->components->twoColumnDetail($step, "<fg=red>failed</> {$detail}");

        return false;
    }
}
