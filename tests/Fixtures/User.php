<?php

namespace Webtack\Ecms\Tests\Fixtures;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Webtack\Ecms\Concerns\HasEcmsAccess;
use Webtack\Ecms\Contracts\EcmsUser;
use Webtack\Ecms\Database\Factories\UserFactory;

/**
 * The user model of the package suite: a host model as the README describes it (`implements EcmsUser`,
 * `use HasEcmsAccess`), bound to the package factory.
 */
#[Fillable(['name', 'email', 'password', 'role_id', 'is_active'])]
#[Hidden(['password', 'remember_token'])]
#[UseFactory(UserFactory::class)]
class User extends Authenticatable implements EcmsUser, MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasEcmsAccess, HasFactory, Notifiable;

    protected $table = 'users';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }
}
