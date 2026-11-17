<?php

namespace Webtack\Ecms\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Webtack\Ecms\Contracts\EcmsUser;
use Webtack\Ecms\Models\Role;
use Webtack\Ecms\Support\UserModel;

/**
 * Factory of the host's user model (`auth.providers.users.model`); the host model binds it with
 * `#[UseFactory(UserFactory::class)]`.
 *
 * @extends Factory<Model&EcmsUser>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * The host's user model, so the factory does not guess a class from its own namespace.
     */
    public function modelName(): string
    {
        return UserModel::class();
    }

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            // Administrator by default, so tests that are not about access keep full rights.
            'role_id' => fn () => Role::admin()->id,
            'is_active' => true,
            'remember_token' => Str::random(10),
        ];
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    /**
     * A user of the given role.
     *
     * @example UserModel::class()::factory()->withRole($editorRole)->create()
     */
    public function withRole(Role $role): static
    {
        return $this->state(fn () => ['role_id' => $role->id]);
    }

    /**
     * A deactivated user.
     */
    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }
}
