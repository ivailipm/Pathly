<?php

namespace Database\Factories;

use App\Models\Milestone;
use App\Models\Subject;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Milestone>
 */
class MilestoneFactory extends Factory
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
            'title' => fake()->sentence(4),
            'description' => fake()->optional()->paragraph(),
            'position' => 1,
        ];
    }
}
