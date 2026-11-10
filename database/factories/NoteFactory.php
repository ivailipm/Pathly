<?php

namespace Database\Factories;

use App\Models\Milestone;
use App\Models\Note;
use App\Models\Task;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Note>
 */
class NoteFactory extends Factory
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
            'task_id' => null,
            'body' => fake()->sentence(14),
        ];
    }

    /**
     * A note on a task, in the task's milestone.
     */
    public function forTask(Task $task): static
    {
        return $this->state(fn (array $attributes) => [
            'milestone_id' => $task->milestone_id,
            'task_id' => $task->id,
        ]);
    }
}
