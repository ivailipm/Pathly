<?php

namespace Database\Factories;

use App\Models\Source;
use App\Models\Subject;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Source>
 */
class SourceFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'subject_id' => Subject::factory(),
            'title' => fake()->words(3, true),
            'url' => fake()->url(),
            'position' => 1,
        ];
    }

    /**
     * A source without a link, such as a paper book.
     */
    public function withoutLink(): static
    {
        return $this->state(fn (array $attributes) => [
            'url' => null,
        ]);
    }
}
