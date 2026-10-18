<?php

namespace Database\Seeders;

use App\Enums\TaskStatus;
use App\Models\Milestone;
use App\Models\Subject;
use App\Models\Task;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $user = User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);

        // 3 subjects, each with 4 ordered milestones of 5 ordered tasks
        Subject::factory(3)->for($user)->create()->each(function (Subject $subject) {
            foreach (range(1, 4) as $milestonePosition) {
                $milestone = Milestone::factory()->for($subject)->create([
                    'position' => $milestonePosition,
                ]);

                foreach (range(1, 5) as $taskPosition) {
                    Task::factory()->for($milestone)->create([
                        'position' => $taskPosition,
                        'status' => fake()->randomElement(TaskStatus::cases()),
                    ]);
                }
            }
        });
    }
}
