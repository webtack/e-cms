<?php

namespace Webtack\Ecms\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Webtack\Ecms\Models\File;

/**
 * @extends Factory<File>
 */
class FileFactory extends Factory
{
    /**
     * Define the model's default state: a small PDF row on the public disk (no file is written).
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $uuid = File::newUuid();

        return [
            'uuid' => $uuid,
            'profile' => 'document',
            'disk' => 'public',
            'path' => "{$uuid}.pdf",
            'original_name' => fake()->word().'.pdf',
            'mime' => 'application/pdf',
            'size' => fake()->numberBetween(1_000, 100_000),
            'hash' => hash('sha256', $uuid),
        ];
    }
}
