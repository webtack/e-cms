<?php

namespace Webtack\Ecms\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Webtack\Ecms\Models\Term;
use Webtack\Ecms\Models\Vocabulary;

/**
 * @extends Factory<Term>
 */
class TermFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'vocabulary_key' => fn () => Vocabulary::factory()->create()->key,
            'slug' => 'term-'.fake()->unique()->lexify('????????'),
            'weight' => 0,
        ];
    }

    /**
     * Put the term into a vocabulary.
     */
    public function in(Vocabulary $vocabulary): static
    {
        return $this->state(['vocabulary_key' => $vocabulary->key]);
    }

    /**
     * Make the term a child of another term (and of its vocabulary).
     */
    public function under(Term $parent): static
    {
        return $this->state(['vocabulary_key' => $parent->vocabulary_key, 'parent_uuid' => $parent->uuid]);
    }
}
