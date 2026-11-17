<?php

namespace Webtack\Ecms\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Webtack\Ecms\Models\Menu;
use Webtack\Ecms\Models\MenuItem;

/**
 * @extends Factory<MenuItem>
 */
class MenuItemFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'menu_key' => fn () => Menu::factory()->create()->key,
            'weight' => 0,
        ];
    }

    /**
     * Put the item into a menu.
     */
    public function in(Menu $menu): static
    {
        return $this->state(['menu_key' => $menu->key]);
    }

    /**
     * Make the item a child of another item (and of its menu).
     */
    public function under(MenuItem $parent): static
    {
        return $this->state(['menu_key' => $parent->menu_key, 'parent_uuid' => $parent->uuid]);
    }
}
