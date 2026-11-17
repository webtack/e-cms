<?php

namespace Webtack\Ecms\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Webtack\Ecms\Models\Term;
use Webtack\Ecms\Models\TermTranslation;

/**
 * @extends Factory<TermTranslation>
 */
class TermTranslationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'term_uuid' => fn () => Term::factory()->create()->uuid,
            'lang' => 'default',
            'name' => fake()->words(2, true),
        ];
    }
}
