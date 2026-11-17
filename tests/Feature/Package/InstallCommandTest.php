<?php

namespace Webtack\Ecms\Tests\Feature\Package;

use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Env;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\After;
use PHPUnit\Framework\Attributes\DataProvider;
use Webtack\Ecms\Models\Role;
use Webtack\Ecms\Tests\Concerns\OverridesHostFiles;
use Webtack\Ecms\Tests\Fixtures\User;
use Webtack\Ecms\Tests\TestCase;

/**
 * `php artisan ecms:install` against a host that has its own tables and none of the package's (the test has no
 * `RefreshDatabase`: only the host's default migrations ran).
 */
class InstallCommandTest extends TestCase
{
    use OverridesHostFiles;

    private const PASSWORD = 'install-test-long-password-1';

    /** @var list<string> Temporary directories with user model classes written by the test. */
    private array $modelDirectories = [];

    protected function setUp(): void
    {
        parent::setUp();

        // The installer publishes into the skeleton; the files go after the test.
        foreach (['content', 'files', 'theme', 'social_networks', 'limits', 'ecms'] as $name) {
            $this->forgetHostPath("config/{$name}.php");
        }

        $this->forgetHostPath('public/storage');
        $this->forgetHostPath('public/vendor/ecms');
        $this->setAdminEnv(null, null);
    }

    #[After]
    protected function removeModelDirectories(): void
    {
        foreach ($this->modelDirectories as $directory) {
            (new Filesystem)->deleteDirectory($directory);
        }

        $this->setAdminEnv(null, null);
    }

    /** The administrator variables of the process (the installer reads them with `env()`). */
    private function setAdminEnv(?string $email, ?string $password): void
    {
        foreach (['ADMIN_EMAIL' => $email, 'ADMIN_PASSWORD' => $password] as $name => $value) {
            Env::getRepository()->clear($name);

            if ($value !== null) {
                Env::getRepository()->set($name, $value);
            }
        }
    }

    /**
     * @param  array<string, mixed>  $options
     * @return array{int, string} Exit status and output (ANSI free).
     */
    private function install(array $options = ['--admin-email' => 'owner@example.com', '--admin-password' => self::PASSWORD]): array
    {
        $status = Artisan::call('ecms:install', $options + ['--no-interaction' => true]);

        return [$status, preg_replace('/\e\[[0-9;]*m/', '', Artisan::output())];
    }

    private function assertStep(string $step, string $result, string $output): void
    {
        $this->assertMatchesRegularExpression('/'.preg_quote($step, '/').'\s*\.*\s*'.preg_quote($result, '/').'/', $output, "[{$step}] did not report [{$result}]:\n{$output}");
    }

    /**
     * Writes a user model class of the given shape to a temporary file, loads it and makes it the host's user model.
     *
     * @return array{class-string, string} The class and the path of its file.
     */
    private function userModel(string $classLine = 'class %s extends Authenticatable', string $traits = 'use HasFactory, Notifiable;', string $extra = '', string $table = 'users'): array
    {
        $name = 'TmpUser'.uniqid();
        $directory = sys_get_temp_dir().'/ecms-install-'.uniqid();
        mkdir($directory);
        $this->modelDirectories[] = $directory;

        $source = "<?php\n\nnamespace Webtack\\Ecms\\Tests\\Tmp;\n\n"
            ."use Illuminate\\Database\\Eloquent\\Factories\\HasFactory;\n"
            ."use Illuminate\\Foundation\\Auth\\User as Authenticatable;\n"
            ."use Illuminate\\Notifications\\Notifiable;\n\n"
            .sprintf($classLine, $name)."\n{\n    {$traits}\n\n    protected \$table = '{$table}';\n{$extra}}\n";

        file_put_contents($file = "{$directory}/{$name}.php", $source);
        require $file;

        $class = "Webtack\\Ecms\\Tests\\Tmp\\{$name}";
        config(['auth.providers.users.model' => $class]);

        return [$class, $file];
    }

    public function test_the_first_run_installs_everything(): void
    {
        [$status, $output] = $this->install();

        $this->assertSame(0, $status, $output);
        $this->assertStep('User model', 'ok', $output);
        $this->assertStep('Configuration', 'done', $output);
        $this->assertStep('Migrations', 'done', $output);
        $this->assertStep('User model preparation', 'already done', $output);
        $this->assertStep('Administrator', 'done', $output);
        $this->assertStep('Storage link', 'done', $output);
        $this->assertStep('Admin assets', 'done', $output);

        $admin = User::where('email', 'owner@example.com')->sole();
        $this->assertSame(Role::admin()->id, $admin->role_id);
        $this->assertNotNull($admin->email_verified_at);
        $this->assertTrue(Hash::check(self::PASSWORD, $admin->password));
        $this->assertFileExists(config_path('ecms.php'));
        $this->assertTrue(file_exists(public_path('storage')) || is_link(public_path('storage')));
        $this->assertFileExists(public_path('vendor/ecms/build/manifest.json'));
    }

    public function test_a_repeated_run_reports_every_step_as_done_already_and_changes_nothing(): void
    {
        $this->install();
        $admin = User::where('email', 'owner@example.com')->sole();
        $before = ['hash' => $admin->password, 'updated_at' => (string) $admin->updated_at, 'users' => User::count(), 'roles' => Role::count(), 'migrations' => DB::table('migrations')->count()];

        [$status, $output] = $this->install(['--admin-email' => 'owner@example.com', '--admin-password' => self::PASSWORD]);

        $this->assertSame(0, $status, $output);
        foreach (['Configuration', 'Migrations', 'User model preparation', 'Administrator', 'Storage link'] as $step) {
            $this->assertStep($step, 'already done', $output);
        }

        $admin->refresh();
        $this->assertSame($before, ['hash' => $admin->password, 'updated_at' => (string) $admin->updated_at, 'users' => User::count(), 'roles' => Role::count(), 'migrations' => DB::table('migrations')->count()]);

        // Without arguments: an administrator exists, so nothing is asked and nothing is needed.
        [$status, $output] = $this->install([]);
        $this->assertSame(0, $status, $output);
        $this->assertStep('Administrator', 'already done', $output);
    }

    public function test_a_stock_user_model_is_edited_once(): void
    {
        [$class, $file] = $this->userModel();

        [$status, $output] = $this->install();

        $source = file_get_contents($file);
        $this->assertSame(0, $status, $output);
        $this->assertStep('User model preparation', 'done', $output);
        $this->assertStringContainsString('use Webtack\Ecms\Concerns\HasEcmsAccess;', $source);
        $this->assertStringContainsString('use Webtack\Ecms\Contracts\EcmsUser;', $source);
        $this->assertMatchesRegularExpression('/extends Authenticatable implements EcmsUser/', $source);
        $this->assertStringContainsString('use HasEcmsAccess, HasFactory, Notifiable;', $source);
        $this->assertSame(0, (int) exec('php -l '.escapeshellarg($file).' >/dev/null 2>&1; echo $?'), 'The edited model is valid PHP');

        [$status, $output] = $this->install([]);

        $this->assertSame(0, $status, $output);
        $this->assertStep('User model preparation', 'already done', $output);
        $this->assertSame($source, file_get_contents($file), 'A second run does not edit the file again');
        $this->assertSame(1, $class::where('email', 'owner@example.com')->count());
    }

    public function test_a_stock_user_model_that_already_implements_an_interface_keeps_it(): void
    {
        [, $file] = $this->userModel('class %s extends Authenticatable implements \\Countable', extra: "    public function count(): int\n    {\n        return 0;\n    }\n");

        [$status, $output] = $this->install();

        $this->assertSame(0, $status, $output);
        $this->assertStringContainsString('implements \\Countable, EcmsUser', file_get_contents($file));
    }

    public function test_a_user_model_of_another_shape_is_not_edited_and_the_command_fails_with_instructions(): void
    {
        [, $file] = $this->userModel(traits: 'use Notifiable;');
        $before = file_get_contents($file);

        [$status, $output] = $this->install();

        $this->assertSame(1, $status);
        $this->assertStep('User model preparation', 'failed', $output);
        $this->assertStringContainsString('use Webtack\Ecms\Concerns\HasEcmsAccess;', $output);
        $this->assertStringContainsString('use Webtack\Ecms\Contracts\EcmsUser;', $output);
        $this->assertSame($before, file_get_contents($file), 'The model file is untouched');
        // The other steps still ran.
        $this->assertStep('Migrations', 'done', $output);
        $this->assertStep('Administrator', 'done', $output);
    }

    #[DataProvider('environments')]
    public function test_there_are_no_credentials_without_interaction_in_any_environment(string $environment): void
    {
        // Production refuses `migrate` without confirmation, so the host migrated before; the point is the administrator.
        Artisan::call('migrate', ['--force' => true]);

        $this->app->detectEnvironment(fn () => $environment);

        try {
            [$status, $output] = $this->install([]);
        } finally {
            // Testbench rolls the database back when the test ends, and that command asks for confirmation in production.
            $this->app->detectEnvironment(fn () => 'testing');
        }

        $this->assertSame(1, $status);
        $this->assertStep('Administrator', 'failed', $output);
        $this->assertStringContainsString('ADMIN_EMAIL', $output);
        $this->assertSame(0, User::count(), 'No account is created without credentials');
    }

    /** @return array<string, array{string}> */
    public static function environments(): array
    {
        return ['testing' => ['testing'], 'local' => ['local'], 'production' => ['production']];
    }

    public function test_a_new_administrator_needs_a_password(): void
    {
        [$status, $output] = $this->install(['--admin-email' => 'owner@example.com']);

        $this->assertSame(1, $status);
        $this->assertStringContainsString('ADMIN_PASSWORD', $output);
        $this->assertSame(0, User::count());
    }

    public function test_the_credentials_can_come_from_the_environment(): void
    {
        $this->setAdminEnv('env-admin@example.com', self::PASSWORD);

        [$status, $output] = $this->install([]);

        $this->assertSame(0, $status, $output);
        $this->assertSame(Role::admin()->id, User::where('email', 'env-admin@example.com')->sole()->role_id);
    }

    public function test_an_existing_account_is_promoted_only_with_force_and_keeps_its_password(): void
    {
        DB::table('users')->insert(['name' => 'Existing', 'email' => 'existing@example.com', 'password' => Hash::make('old-password-123'), 'created_at' => now(), 'updated_at' => now()]);

        [$status, $output] = $this->install(['--admin-email' => 'existing@example.com']);

        $this->assertSame(1, $status);
        $this->assertStringContainsString('confirm the promotion', $output);
        $this->assertNull(User::where('email', 'existing@example.com')->sole()->role_id, 'Nothing changed without confirmation');

        [$status, $output] = $this->install(['--admin-email' => 'existing@example.com', '--force' => true]);

        $promoted = User::where('email', 'existing@example.com')->sole();
        $this->assertSame(0, $status, $output);
        $this->assertSame(Role::admin()->id, $promoted->role_id);
        $this->assertNotNull($promoted->email_verified_at);
        $this->assertTrue(Hash::check('old-password-123', $promoted->password));
    }

    public function test_other_existing_users_stay_without_a_role(): void
    {
        DB::table('users')->insert(['name' => 'Other', 'email' => 'other@example.com', 'password' => 'x', 'created_at' => now(), 'updated_at' => now()]);

        $this->install();

        $this->assertNull(User::where('email', 'other@example.com')->sole()->role_id);
    }

    public function test_a_user_model_with_a_uuid_key_is_refused_before_anything_is_created(): void
    {
        $this->userModel(extra: "    public \$incrementing = false;\n\n    protected \$keyType = 'string';\n");

        [$status, $output] = $this->install();

        $this->assertSame(1, $status);
        $this->assertStep('User model', 'failed', $output);
        $this->assertStringContainsString('integer', $output);
        $this->assertFileDoesNotExist(config_path('ecms.php'));
        $this->assertFalse(Schema::hasTable('roles'), 'No migration ran');
        $this->assertSame(0, DB::table('users')->count());
    }

    public function test_a_user_model_with_another_table_is_refused(): void
    {
        $this->userModel(table: 'accounts');

        [$status, $output] = $this->install();

        $this->assertSame(1, $status);
        $this->assertStringContainsString('only [users]', $output);
        $this->assertFalse(Schema::hasTable('roles'));
    }
}
