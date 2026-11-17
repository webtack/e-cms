<?php

namespace Webtack\Ecms\Tests\Feature\Auth;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Env;
use Webtack\Ecms\Database\Seeders\DatabaseSeeder;
use Webtack\Ecms\Tests\Fixtures\User;
use Webtack\Ecms\Tests\TestCase;

class DatabaseSeederTest extends TestCase
{
    use RefreshDatabase;

    private const PASSWORD = 'seeder-test-long-password-1';

    protected function tearDown(): void
    {
        $this->setAdminEnv(null, null);

        parent::tearDown();
    }

    /**
     * Set or clear the seeder credentials for the current process.
     */
    private function setAdminEnv(?string $email, ?string $password): void
    {
        $repository = Env::getRepository();

        foreach (['ADMIN_EMAIL' => $email, 'ADMIN_PASSWORD' => $password] as $name => $value) {
            $repository->clear($name);

            if ($value !== null) {
                $repository->set($name, $value);
            }
        }
    }

    public function test_seeder_creates_verified_administrator_who_can_log_in(): void
    {
        $this->setAdminEnv('seeded-admin@example.org', self::PASSWORD);

        $this->seed(DatabaseSeeder::class);

        $user = User::where('email', 'seeded-admin@example.org')->firstOrFail();

        $this->assertNotNull($user->email_verified_at);
        $this->assertSame('admin', $user->role->name);
        $this->assertTrue($user->is_active);

        $this->post('/login', [
            'email' => $user->email,
            'password' => self::PASSWORD,
        ])->assertRedirect('/admin/dashboard');
    }

    public function test_seeder_is_idempotent(): void
    {
        $this->setAdminEnv('seeded-admin@example.org', self::PASSWORD);

        $this->seed(DatabaseSeeder::class);
        $this->seed(DatabaseSeeder::class);

        $this->assertSame(1, User::count());
    }

    public function test_seeder_fails_without_email_and_creates_no_user(): void
    {
        $this->setAdminEnv(null, self::PASSWORD);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('ADMIN_EMAIL');

        try {
            $this->seed(DatabaseSeeder::class);
        } finally {
            $this->assertSame(0, User::count());
        }
    }

    public function test_seeder_fails_without_password_and_creates_no_user(): void
    {
        $this->setAdminEnv('seeded-admin@example.org', null);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('ADMIN_PASSWORD');

        try {
            $this->seed(DatabaseSeeder::class);
        } finally {
            $this->assertSame(0, User::count());
        }
    }

    public function test_seeder_does_not_create_default_account(): void
    {
        $this->setAdminEnv('seeded-admin@example.org', self::PASSWORD);

        $this->seed(DatabaseSeeder::class);

        $this->assertDatabaseMissing('users', ['email' => 'admin@example.com']);
    }
}
