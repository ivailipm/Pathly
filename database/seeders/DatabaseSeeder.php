<?php

namespace Database\Seeders;

use App\Enums\SubjectColour;
use App\Enums\TaskStatus;
use App\Models\Milestone;
use App\Models\Note;
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

        $subject = $user->subjects()->oldest('id')->firstOrFail();
        $current = $subject->milestones()->whereNull('completed_at')->firstOrFail();

        $this->seedSessions($user, $subject, $current);
        $this->seedNotes($current);
    }

    /**
     * About 12 completed sessions over the last 10 days on the first subject, including today and yesterday,
     * so the week chart, the streak and "Studied today" have something to show. Today's and two days ago's
     * first sessions are on the current milestone's first tasks, which the seeded notes link to.
     */
    private function seedSessions(User $user, Subject $subject, Milestone $current): void
    {
        $tasks = $subject->tasks()->get();
        $currentTasks = $current->tasks()->get();

        foreach ([0, 0, 1, 2, 2, 3, 5, 6, 6, 7, 8, 9] as $index => $daysAgo) {
            $task = match ($index) {
                0 => $currentTasks[0],
                3 => $currentTasks[1],
                default => $tasks->random(),
            };

            $user->studySessions()->create([
                'subject_id' => $subject->id,
                'task_id' => $task->id,
                'started_at' => LocalTime::noonUtc(LocalTime::today()->subDays($daysAgo)->toDateString()),
                'minutes' => fake()->numberBetween(15, 90),
            ]);
        }
    }

    /**
     * Four field notes on the current milestone: two on tasks studied that day, two unlinked.
     */
    private function seedNotes(Milestone $current): void
    {
        $tasks = $current->tasks()->get();
        $evening = fn (int $daysAgo) => LocalTime::today()->subDays($daysAgo)->setTime(19, 30)->utc();

        $notes = [
            [0, $tasks[0]->id, 'The part I kept missing was that the queue worker has to be running, or nothing ever leaves the app.'],
            [1, null, 'Drew the flow on paper: event, queue, broadcaster, browser. Much clearer than reading about it.'],
            [2, $tasks[1]->id, 'Two hosts in the .env: the one the browser sees and the one the server binds to. Wrote both down so I stop guessing.'],
            [5, null, 'Next time: start with the smallest working example before touching the real board.'],
        ];

        foreach ($notes as [$daysAgo, $taskId, $body]) {
            Note::factory()->for($current)->create([
                'task_id' => $taskId,
                'body' => $body,
                'created_at' => $evening($daysAgo),
                'updated_at' => $evening($daysAgo),
            ]);
        }
    }
}
