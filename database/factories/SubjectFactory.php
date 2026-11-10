<?php

namespace Database\Factories;

use App\Enums\SubjectColour;
use App\Enums\SubjectStatus;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Subject>
 */
class SubjectFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'title' => fake()->sentence(3),
            'description' => fake()->optional()->paragraph(),
            'status' => SubjectStatus::Active,
            'colour' => fake()->randomElement(SubjectColour::cases()),
            'tags' => [],
        ];
    }

    /**
     * Indicate that the subject is paused.
     */
    public function paused(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => SubjectStatus::Paused,
        ]);
    }

    /**
     * Indicate that the subject is archived.
     */
    public function archived(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => SubjectStatus::Archived,
        ]);
    }
}
