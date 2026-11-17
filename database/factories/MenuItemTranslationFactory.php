<?php

namespace Webtack\Ecms\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Webtack\Ecms\Models\MenuItem;
use Webtack\Ecms\Models\MenuItemTranslation;

/**
 * @extends Factory<MenuItemTranslation>
 */
class MenuItemTranslationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'item_uuid' => fn () => MenuItem::factory()->create()->uuid,
            'lang' => 'default',
            'title' => fake()->words(2, true),
        ];
    }
}
