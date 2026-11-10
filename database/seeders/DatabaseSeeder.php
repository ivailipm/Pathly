<?php

namespace Database\Seeders;

use App\Enums\SubjectColour;
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
        $subjects = [
            ['colour' => SubjectColour::Clay, 'tags' => ['backend', 'portfolio'], 'target_date' => now()->addDays(50)->toDateString()],
            ['colour' => SubjectColour::Slate, 'tags' => ['maths'], 'target_date' => null],
            ['colour' => SubjectColour::Moss, 'tags' => [], 'target_date' => null],
        ];

        foreach ($subjects as $index => $properties) {
            $subject = Subject::factory()->for($user)->create($properties);

            foreach (range(1, 4) as $milestonePosition) {
                $milestone = Milestone::factory()->for($subject)->create([
                    'position' => $milestonePosition,
                ]);

                foreach (range(1, 5) as $taskPosition) {
                    Task::factory()->for($milestone)->create([
                        'position' => $taskPosition,
                        // The first subject's first milestone is finished, so the seeded data shows completion
                        'status' => $index === 0 && $milestonePosition === 1
                            ? TaskStatus::Done
                            : fake()->randomElement(TaskStatus::cases()),
                    ]);
                }

                $milestone->syncCompletion();
            }
        }
    }
}
