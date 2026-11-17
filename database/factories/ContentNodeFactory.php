<?php

namespace Webtack\Ecms\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Webtack\Ecms\Models\ContentNode;
use Webtack\Ecms\Models\ContentType;
use Webtack\Ecms\Support\UserModel;

/**
 * @extends Factory<ContentNode>
 */
class ContentNodeFactory extends Factory
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
            'slug' => 'node-'.fake()->unique()->lexify('????????'),
            'published_at' => now(),
            'unpublished_at' => null,
            'is_active' => true,
            'user_id' => UserModel::class()::factory(),
        ];
    }
}
