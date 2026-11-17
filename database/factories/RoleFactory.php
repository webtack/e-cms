<?php

namespace Webtack\Ecms\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Webtack\Ecms\Models\Role;
use Webtack\Ecms\Models\RolePermission;

/**
 * @extends Factory<Role>
 */
class RoleFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return ['name' => fake()->unique()->words(2, true)];
    }

    /**
     * Give the role permissions, `scope => level` (`'menus' => 'allow'`, `'node:news' => 'own'`).
     *
     * @param  array<string, string>  $permissions
     *
     * @example Role::factory()->withPermissions(['menus' => 'allow', 'node:news' => 'own'])->create()
     */
    public function withPermissions(array $permissions): static
    {
        return $this->afterCreating(function (Role $role) use ($permissions) {
            foreach ($permissions as $scope => $level) {
                RolePermission::create(['role_id' => $role->id, 'scope' => $scope, 'level' => $level]);
            }
        });
    }
}
