<?php

namespace Database\Factories;

use App\Models\StudySession;
use App\Models\Subject;
use App\Models\Task;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StudySession>
 */
class StudySessionFactory extends Factory
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
            'subject_id' => Subject::factory(),
            'task_id' => null,
            'started_at' => now(),
            'minutes' => 30,
        ];
    }

    /**
     * A timer that is still running.
     */
    public function running(): static
    {
        return $this->state(fn (array $attributes) => [
            'minutes' => null,
        ]);
    }

    /**
     * A session on a task, by the task's owner, on the task's subject.
     */
    public function forTask(Task $task): static
    {
        return $this->state(fn (array $attributes) => [
            'task_id' => $task->id,
            'subject_id' => $task->milestone->subject_id,
            'user_id' => $task->milestone->subject->user_id,
        ]);
    }

    /**
     * A session on a subject (no task), by the subject's owner.
     */
    public function forSubject(Subject $subject): static
    {
        return $this->state(fn (array $attributes) => [
            'task_id' => null,
            'subject_id' => $subject->id,
            'user_id' => $subject->user_id,
        ]);
    }
}
