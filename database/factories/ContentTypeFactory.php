<?php

namespace Webtack\Ecms\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Webtack\Ecms\Models\ContentType;

/**
 * @extends Factory<ContentType>
 */
class ContentTypeFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'key' => 'type_'.fake()->unique()->lexify('????????'),
            'name' => fake()->words(2, true),
            'slug' => null,
            'kind' => 'content',
        ];
    }

    /**
     * Set the kind of the type.
     */
    public function kind(string $kind): static
    {
        return $this->state(['kind' => $kind]);
    }
}
