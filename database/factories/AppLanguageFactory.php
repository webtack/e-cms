<?php

namespace Webtack\Ecms\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Webtack\Ecms\Models\AppLanguage;

/**
 * @extends Factory<AppLanguage>
 */
class AppLanguageFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => fake()->unique()->lexify('??'),
            'name' => fake()->word(),
        ];
    }

    /**
     * Mark the language as active.
     */
    public function active(): static
    {
        return $this->state(['is_active' => true]);
    }
}
