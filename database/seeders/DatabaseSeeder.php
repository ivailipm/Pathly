<?php

namespace Database\Seeders;

use App\Enums\SubjectColour;
use App\Enums\TaskStatus;
use App\Models\Milestone;
use App\Models\Subject;
use App\Models\Task;
use App\Models\User;
use App\Support\LocalTime;
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

        $this->seedSessions($user);
    }

    /**
     * About 12 completed sessions over the last 10 days on the first subject, including today and yesterday,
     * so the week chart, the streak and "Studied today" have something to show.
     */
    private function seedSessions(User $user): void
    {
        $subject = $user->subjects()->oldest('id')->firstOrFail();
        $tasks = $subject->tasks()->get();

        foreach ([0, 0, 1, 2, 2, 3, 5, 6, 6, 7, 8, 9] as $daysAgo) {
            $user->studySessions()->create([
                'subject_id' => $subject->id,
                'task_id' => $tasks->random()->id,
                'started_at' => LocalTime::noonUtc(LocalTime::today()->subDays($daysAgo)->toDateString()),
                'minutes' => fake()->numberBetween(15, 90),
            ]);
        }
    }
}
