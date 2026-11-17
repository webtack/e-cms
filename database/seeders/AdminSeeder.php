<?php

namespace Webtack\Ecms\Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use RuntimeException;
use Webtack\Ecms\Models\Role;
use Webtack\Ecms\Support\UserModel;

/**
 * Sets up the initial administrator: a user with a verified email and the system role `admin`.
 *
 * The email and the password come only from explicit values (`ADMIN_EMAIL` / `ADMIN_PASSWORD`, or {@see self::with()}
 * for the installer). There are no defaults in any environment, so a re-seed can never recreate a weak account.
 * When either value is missing the seeder fails before any write and names the missing values.
 *
 * An account with the given email is never recreated: it is promoted to `admin`, its email is marked verified and its
 * password is replaced only when it differs from the given one. Rerunning with the same values changes nothing.
 *
 * @example
 *   // ADMIN_EMAIL=owner@example.com ADMIN_PASSWORD=... php artisan db:seed --class='Webtack\Ecms\Database\Seeders\AdminSeeder'
 *   (new AdminSeeder)->with('owner@example.com', 'secret-password')->run();
 */
class AdminSeeder extends Seeder
{
    /** The result of {@see self::apply()}: the account was created. */
    public const CREATED = 'created';

    /** The account existed and received the `admin` role or a new password. */
    public const PROMOTED = 'promoted';

    /** The account already was an administrator with that password. */
    public const UNCHANGED = 'unchanged';

    private ?string $email = null;

    private ?string $password = null;

    /**
     * Use these values instead of the environment variables (a null value falls back to the variable).
     */
    public function with(?string $email, ?string $password): static
    {
        $this->email = $email;
        $this->password = $password;

        return $this;
    }

    /**
     * Create or promote the administrator.
     *
     * @throws RuntimeException When the email or the password is missing; nothing is written.
     */
    public function run(): void
    {
        $email = $this->email ?: env('ADMIN_EMAIL');
        $password = $this->password ?: env('ADMIN_PASSWORD');

        $missing = array_keys(array_filter([
            'ADMIN_EMAIL' => ! $email,
            'ADMIN_PASSWORD' => ! $password,
        ]));

        if ($missing !== []) {
            throw new RuntimeException(implode(' and ', $missing).' must be set to seed the administrator.');
        }

        $this->apply($email, $password);
    }

    /**
     * Create the administrator, or give an existing account with this email the `admin` role.
     *
     * An existing account keeps its password unless a different one is given. A new account needs a password.
     *
     * @return self::CREATED|self::PROMOTED|self::UNCHANGED
     *
     * @throws RuntimeException When the account does not exist and no password is given.
     */
    public function apply(string $email, ?string $password): string
    {
        $adminId = Role::admin()->id;
        $user = UserModel::query()->where('email', $email)->first();

        if ($user === null) {
            if (! $password) {
                throw new RuntimeException("ADMIN_PASSWORD must be set to create the administrator [{$email}].");
            }

            // `forceFill`: the user model may not be prepared for the CMS yet (its `$fillable` lacks `role_id`).
            $user = new (UserModel::class());
            $user->forceFill([
                'name' => 'Administrator',
                'email' => $email,
                'password' => Hash::make($password),
                'email_verified_at' => now(),
                'role_id' => $adminId,
            ])->save();

            return self::CREATED;
        }

        $changed = false;

        if ((int) $user->role_id !== $adminId) {
            $user->role_id = $adminId;
            $changed = true;
        }

        // The administrator signs in to a panel that requires a verified email.
        if ($user->email_verified_at === null) {
            $user->email_verified_at = now();
            $changed = true;
        }

        if ($password && ! Hash::check($password, $user->password)) {
            $user->password = Hash::make($password);
            $changed = true;
        }

        if ($changed) {
            $user->save();
        }

        return $changed ? self::PROMOTED : self::UNCHANGED;
    }
}
