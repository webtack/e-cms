<?php

namespace Webtack\Ecms\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Webtack\Ecms\Models\Menu;

/**
 * @extends Factory<Menu>
 */
class MenuFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'key' => 'menu_'.fake()->unique()->lexify('????????'),
            'name' => fake()->words(2, true),
        ];
    }

    /**
     * Limit the depth of the item tree.
     */
    public function maxDepth(int $depth): static
    {
        return $this->state(['settings' => ['max_depth' => $depth]]);
    }
}
