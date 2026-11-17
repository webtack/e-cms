<?php

namespace Webtack\Ecms\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Webtack\Ecms\Models\ContentField;
use Webtack\Ecms\Models\ContentType;

/**
 * @extends Factory<ContentField>
 */
class ContentFieldFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'content_type' => fn () => ContentType::factory()->create()->key,
            'key' => 'field_'.fake()->unique()->lexify('????????'),
            'name' => fake()->words(2, true),
            'type' => 'string',
            'hide_label' => false,
        ];
    }
}
