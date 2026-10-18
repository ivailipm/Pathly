<?php

namespace Database\Factories;

use App\Enums\TaskStatus;
use App\Models\Milestone;
use App\Models\Task;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Task>
 */
class TaskFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'milestone_id' => Milestone::factory(),
            'title' => fake()->sentence(5),
            'url' => fake()->optional()->url(),
            'status' => TaskStatus::Todo,
            'position' => 1,
        ];
    }

    /**
     * Indicate that the task is in progress.
     */
    public function inProgress(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => TaskStatus::InProgress,
        ]);
    }

    /**
     * Indicate that the task is done.
     */
    public function done(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => TaskStatus::Done,
        ]);
    }
}
