<?php

namespace Database\Seeders;

use App\Enums\TaskStatus;
use App\Enums\TaskType;
use App\Models\Milestone;
use App\Models\Subject;
use App\Models\Task;
use App\Models\User;
use App\Notifications\FinishWhatYouStart;
use App\Support\LocalTime;
use App\Support\Tint;
use Carbon\CarbonImmutable;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Seeder;

/**
 * A demo library that looks like the design mockups: four subjects at different stages, three weeks of study
 * sessions, field notes and one "finish what you start" reminder. Every date is relative to today, so the streak,
 * the week chart and "Recently finished" look lived in whenever it runs.
 *
 * Log in as test@example.com with the password "password".
 *
 * @phpstan-type TaskRow array{0: string, 1: string|null, 2: TaskType|null, 3?: TaskStatus}
 * @phpstan-type MilestoneRow array{0: string, 1: string, 2: int|null, 3: list<TaskRow>}
 * @phpstan-type SubjectRow array{title: string, description: string, colour: string, tags: list<string>, target: int|null, started: int, sources: list<array{0: string, 1: string|null}>, milestones: list<MilestoneRow>}
 */
class DemoSeeder extends Seeder
{
    use WithoutModelEvents;

    /** @var array<string, Milestone> seeded milestones by title (titles are unique in the demo) */
    private array $milestones = [];

    /** @var array<string, Task> seeded tasks by title */
    private array $tasks = [];

    /** @var array<string, int> each task's subject id, by task title */
    private array $subjectIds = [];

    /** @var array<string, CarbonImmutable> when each task was last changed, by title */
    private array $touched = [];

    public function run(): void
    {
        // Unguarded so the seeder can backdate timestamps and completion dates, which aren't fillable
        Model::unguarded(function () {
            $user = User::factory()->create([
                'name' => 'Alex Morgan',
                'email' => 'test@example.com',
            ]);

            // Created oldest first: the app lists subjects newest first, so Laravel & Livewire leads like in the mockups
            foreach (array_reverse($this->subjects()) as $subject) {
                $this->seedSubject($user, $subject);
            }

            $this->seedSessions($user);
            $this->seedNotes();
            $this->touchTasks();
            $this->remind($user);
        });
    }

    /**
     * @param  SubjectRow  $row
     */
    private function seedSubject(User $user, array $row): void
    {
        $created = $this->at($row['started'], '09:00');

        $subject = $user->subjects()->create([
            'title' => $row['title'],
            'description' => $row['description'],
            'colour' => $row['colour'],
            'tags' => $row['tags'],
            'target_date' => $row['target'] === null ? null : LocalTime::today()->addDays($row['target'])->toDateString(),
            'created_at' => $created,
            'updated_at' => $created,
        ]);

        foreach ($row['sources'] as [$title, $url]) {
            $subject->addSource($title, $url);
        }

        foreach ($row['milestones'] as $index => [$title, $description, $doneDaysAgo, $tasks]) {
            $completedAt = $doneDaysAgo === null ? null : $this->at($doneDaysAgo, '21:00');

            $milestone = $subject->milestones()->create([
                'title' => $title,
                'description' => $description,
                'position' => $index + 1,
                'completed_at' => $completedAt,
                'created_at' => $created,
                'updated_at' => $completedAt ?? $created,
            ]);

            $this->milestones[$title] = $milestone;

            foreach ($tasks as $position => $task) {
                $this->tasks[$task[0]] = $milestone->tasks()->create([
                    'title' => $task[0],
                    'url' => $task[1],
                    'type' => $task[2],
                    'status' => $task[3] ?? ($completedAt === null ? TaskStatus::Todo : TaskStatus::Done),
                    'position' => $position + 1,
                    'created_at' => $created,
                ]);

                $this->subjectIds[$task[0]] = $subject->id;
                $this->touched[$task[0]] = $completedAt ?? $created;
            }
        }
    }

    /**
     * Sessions on 17 of the last 18 days: today back to four days ago in a row (a five-day streak), the rest spread
     * over the earlier milestones. Machine learning was last studied four days ago, so it has gone quiet.
     */
    private function seedSessions(User $user): void
    {
        $sessions = [
            [0, '07:40', 'Build a presence channel for live cursors', 50],
            [0, '08:40', 'Read: Echo client API', 20],
            [1, '18:30', 'Subjunctive after “ojalá”', 35],
            [1, '20:00', 'Private channels and auth', 45],
            [2, '19:00', 'Install Reverb and configure .env', 30],
            [3, '07:30', 'Read: When to use the subjunctive', 25],
            [3, '19:30', 'Watch: Reverb from zero', 40],
            [4, '18:00', 'Gradient descent, worked by hand', 60],
            [6, '08:00', 'Watch: The present subjunctive, explained', 20],
            [6, '19:00', 'Read: Broadcasting overview', 40],
            [6, '20:30', 'Watch: Linear regression with one variable', 45],
            [7, '18:30', 'Build the kanban board', 90],
            [8, '19:00', 'Retell a film plot in the past', 45],
            [9, '19:00', 'Build the kanban board', 75],
            [10, '18:00', 'Matrix multiplication by hand', 50],
            [10, '20:00', 'Form objects for create and edit', 60],
            [11, '19:00', 'Read: Livewire components', 45],
            [11, '21:00', 'Watch: Livewire 4 crash course', 35],
            [12, '07:30', 'Read: Mathematics for Machine Learning, chapter 2', 45],
            [12, '19:00', 'Order children by a position column', 40],
            [13, '08:00', 'Watch: Preterite and imperfect in stories', 25],
            [13, '19:00', 'Model subjects, milestones and tasks', 70],
            [14, '19:30', 'Read: Eloquent relationships', 50],
            [14, '21:00', 'Watch: Eager loading and the N+1 problem', 30],
            [15, '19:00', 'Build a layout with Blade components', 60],
            [16, '18:30', 'Read: Routing', 35],
            [16, '19:30', 'Read: Blade templates', 40],
            [17, '19:00', 'Watch: Laravel from scratch, episodes 1 to 8', 80],
        ];

        foreach ($sessions as [$daysAgo, $time, $title, $minutes]) {
            $startedAt = $this->at($daysAgo, $time, $minutes);
            $endedAt = $startedAt->addMinutes($minutes);

            $user->studySessions()->create([
                'subject_id' => $this->subjectIds[$title],
                'task_id' => $this->tasks[$title]->id,
                'started_at' => $startedAt,
                'minutes' => $minutes,
                'created_at' => $endedAt,
                'updated_at' => $endedAt,
            ]);

            $this->touched[$title] = $this->touched[$title]->max($endedAt);
        }
    }

    /**
     * Field notes written after a session, most of them linked to the task studied that day.
     */
    private function seedNotes(): void
    {
        $notes = [
            [1, '21:00', 'Real-time with Reverb', 'Private channels and auth', 'Broadcasting clicked once I drew the flow on paper: event, queue, Reverb, Echo. The part I kept missing was that the queue worker has to be running, or nothing ever leaves the app.'],
            [1, '19:15', 'The subjunctive mood', 'Subjunctive after “ojalá”', '“Ojalá” always takes the subjunctive. Said ten wishes out loud until “ojalá que llueva” stopped sounding strange.'],
            [2, '19:40', 'Real-time with Reverb', 'Install Reverb and configure .env', 'Reverb runs on its own port, so the .env needs both the host the browser sees and the one the server binds to. Wrote both down here so I stop guessing.'],
            [4, '19:10', 'Linear models', 'Gradient descent, worked by hand', 'One step of gradient descent with pencil and paper. The learning rate is how big a step you dare to take: too big and you overshoot the bottom.'],
            [6, '21:30', 'Real-time with Reverb', null, 'Next time: start with the smallest working example before touching the real board.'],
            [7, '20:10', 'Livewire components', 'Build the kanban board', 'Sorting by position and swapping on drop is enough. No need for a library.'],
            [8, '19:50', 'Past tenses, side by side', 'Retell a film plot in the past', 'Preterite moves the story forward, imperfect paints the background. Retelling a film made that obvious.'],
        ];

        foreach ($notes as [$daysAgo, $time, $milestone, $task, $body]) {
            $at = $this->at($daysAgo, $time);

            $this->milestones[$milestone]->notes()->create([
                'task_id' => $task === null ? null : $this->tasks[$task]->id,
                'body' => $body,
                'created_at' => $at,
                'updated_at' => $at,
            ]);
        }
    }

    /**
     * Give every task the time it was last worked on, which "Finish what you start" counts as activity.
     */
    private function touchTasks(): void
    {
        foreach ($this->touched as $title => $at) {
            Task::query()->whereKey($this->tasks[$title]->id)->update(['updated_at' => $at]);
        }
    }

    /**
     * Store the reminder the daily job would have sent this morning, on Today only (no email from a seeder).
     */
    private function remind(User $user): void
    {
        $stalled = $user->subjects()
            ->withActivity()
            ->get()
            ->filter(fn (Subject $subject) => $subject->isStalled(config()->integer('app.nudge_after_days')))
            ->values();

        if ($stalled->isEmpty()) {
            return;
        }

        $user->notifyNow(new FinishWhatYouStart($stalled), ['database']);

        Subject::query()->whereKey($stalled->modelKeys())->update(['nudged_at' => now()]);
    }

    /**
     * A local time $daysAgo days back, in UTC. Today's times that haven't come yet move to just before now, so a
     * session of $minutes has already ended (but never before midnight).
     */
    private function at(int $daysAgo, string $time, int $minutes = 0): CarbonImmutable
    {
        $local = LocalTime::today()->subDays($daysAgo)->setTimeFromTimeString($time);
        $latest = LocalTime::now()->subMinutes($minutes + 5);

        if ($local->greaterThan($latest)) {
            $local = $latest->max(LocalTime::today());
        }

        return $local->utc();
    }

    /**
     * The demo curriculum: [title, description, finished how many days ago (null = not finished), tasks], and each
     * task [title, url, type, status]. Tasks in finished milestones are done; the others are to do unless stated.
     *
     * @return list<SubjectRow>
     */
    private function subjects(): array
    {
        $done = TaskStatus::Done;
        $doing = TaskStatus::InProgress;

        return [
            [
                'title' => 'Laravel & Livewire',
                'description' => 'Go from routes and Blade to a real-time, tested app on a real server. This is the stack behind Pathly itself, so every milestone ends with something shipped here.',
                'colour' => Tint::PRESETS['Clay'],
                'tags' => ['backend', 'portfolio'],
                'target' => 35,
                'started' => 17,
                'sources' => [
                    ['Laravel docs', 'https://laravel.com/docs'],
                    ['Livewire docs', 'https://livewire.laravel.com/docs'],
                    ['Laracasts', 'https://laracasts.com'],
                    ['Laravel: Up & Running', null],
                ],
                'milestones' => [
                    ['Routing & Blade', 'Routes, controllers, layouts and components.', 15, [
                        ['Read: Routing', 'https://laravel.com/docs/routing', TaskType::Docs],
                        ['Read: Blade templates', 'https://laravel.com/docs/blade', TaskType::Docs],
                        ['Watch: Laravel from scratch, episodes 1 to 8', 'https://laracasts.com', TaskType::Video],
                        ['Build a layout with Blade components', null, TaskType::Exercise],
                    ]],
                    ['Eloquent relationships', 'Subjects own milestones own tasks, ordered by position.', 12, [
                        ['Read: Eloquent relationships', 'https://laravel.com/docs/eloquent-relationships', TaskType::Docs],
                        ['Model subjects, milestones and tasks', null, TaskType::Exercise],
                        ['Watch: Eager loading and the N+1 problem', 'https://laracasts.com', TaskType::Video],
                        ['Order children by a position column', null, TaskType::Exercise],
                    ]],
                    ['Livewire components', 'Forms, actions and the drag-and-drop board.', 7, [
                        ['Read: Livewire components', 'https://livewire.laravel.com/docs/components', TaskType::Docs],
                        ['Form objects for create and edit', 'https://livewire.laravel.com/docs/forms', TaskType::Exercise],
                        ['Build the kanban board', null, TaskType::Exercise],
                        ['Watch: Livewire 4 crash course', 'https://laracasts.com', TaskType::Video],
                    ]],
                    ['Real-time with Reverb', 'Broadcast events over WebSockets so the board updates live in every open tab. Done when moving a card here moves it everywhere.', null, [
                        ['Read: Broadcasting overview', 'https://laravel.com/docs/broadcasting', TaskType::Docs, $done],
                        ['Install Reverb and configure .env', 'https://laravel.com/docs/reverb', TaskType::Exercise, $done],
                        ['Watch: Reverb from zero', 'https://laracasts.com', TaskType::Video, $done],
                        ['Private channels and auth', 'https://laravel.com/docs/broadcasting#authorizing-channels', TaskType::Docs, $done],
                        ['Build a presence channel for live cursors', null, TaskType::Exercise, $doing],
                        ['Read: Echo client API', 'https://github.com/laravel/echo', TaskType::Docs, $doing],
                        ['Scale Reverb horizontally with Redis', 'https://laravel.com/docs/reverb#scaling', TaskType::Docs],
                    ]],
                    ['Testing with Pest', 'Feature tests for policies, ordering and broadcasts.', null, [
                        ['Read: Writing tests', 'https://pestphp.com/docs/writing-tests', TaskType::Docs],
                        ['Prove other users get a 404', null, TaskType::Exercise],
                        ['Test the order after a drag and drop', null, TaskType::Exercise],
                        ['Fake events and assert broadcasts', 'https://laravel.com/docs/events#testing', TaskType::Docs],
                        ['Watch: Pest from scratch', 'https://laracasts.com', TaskType::Video],
                    ]],
                    ['Deploy with Coolify', 'Queues, Reverb and SSL on a real server.', null, [
                        ['Set up a Hetzner server with Coolify', 'https://coolify.io/docs', TaskType::Docs],
                        ['Keep the queue worker and Reverb running', 'https://laravel.com/docs/reverb#production', TaskType::Docs],
                        ['Point a domain at it and turn on SSL', null, TaskType::Exercise],
                    ]],
                ],
            ],
            [
                'title' => 'Machine learning foundations',
                'description' => 'The maths and the models behind machine learning, worked through by hand before reaching for a library.',
                'colour' => Tint::PRESETS['Slate'],
                'tags' => ['maths'],
                'target' => 81,
                'started' => 24,
                'sources' => [
                    ['Mathematics for Machine Learning', 'https://mml-book.github.io'],
                    ['Machine Learning Specialization', 'https://www.coursera.org/learn/machine-learning'],
                    ['scikit-learn user guide', 'https://scikit-learn.org/stable/user_guide.html'],
                    ['Hands-On Machine Learning (book)', null],
                ],
                'milestones' => [
                    ['Vectors and matrices refresher', 'Dot products, matrix multiplication and why shapes matter.', 10, [
                        ['Watch: Essence of linear algebra, chapters 1 to 4', 'https://www.3blue1brown.com/topics/linear-algebra', TaskType::Video],
                        ['Read: Mathematics for Machine Learning, chapter 2', 'https://mml-book.github.io', TaskType::Docs],
                        ['Matrix multiplication by hand', null, TaskType::Exercise],
                    ]],
                    ['Linear models', 'Fit a line, then a plane, then understand what the fitting actually does.', null, [
                        ['Watch: Linear regression with one variable', 'https://www.coursera.org/learn/machine-learning', TaskType::Video, $done],
                        ['Gradient descent, worked by hand', 'https://colab.research.google.com', TaskType::Notebook, $doing],
                        ['Read: Least squares and the normal equation', 'https://mml-book.github.io', TaskType::Docs],
                        ['Fit a model to the California housing data', 'https://scikit-learn.org/stable/datasets/real_world.html', TaskType::Notebook],
                        ['Read: Ridge and lasso', 'https://scikit-learn.org/stable/modules/linear_model.html', TaskType::Docs],
                    ]],
                    ['Classification', 'Logistic regression, decision boundaries and how to tell whether a model is any good.', null, [
                        ['Watch: Logistic regression', 'https://www.coursera.org/learn/machine-learning', TaskType::Video],
                        ['Read: Precision, recall and the confusion matrix', 'https://scikit-learn.org/stable/modules/model_evaluation.html', TaskType::Docs],
                        ['Classify handwritten digits', 'https://colab.research.google.com', TaskType::Notebook],
                    ]],
                    ['Trees and ensembles', 'Decision trees, random forests and gradient boosting.', null, [
                        ['Read: Decision trees', 'https://scikit-learn.org/stable/modules/tree.html', TaskType::Docs],
                        ['Grow a tree by hand on ten rows', null, TaskType::Exercise],
                        ['Random forest on the housing data', 'https://colab.research.google.com', TaskType::Notebook],
                    ]],
                    ['A neural network from scratch', 'Forward pass, backpropagation and a training loop in plain NumPy.', null, [
                        ['Watch: Neural networks, chapters 1 to 4', 'https://www.3blue1brown.com/topics/neural-networks', TaskType::Video],
                        ['Backpropagation on paper', null, TaskType::Exercise],
                        ['A two-layer network in NumPy', 'https://colab.research.google.com', TaskType::Notebook],
                        ['Train it on handwritten digits', 'https://colab.research.google.com', TaskType::Notebook],
                    ]],
                ],
            ],
            [
                'title' => 'Spanish to B2',
                'description' => 'From a comfortable A2 to B2: enough grammar to stop translating in my head, and enough listening to follow a podcast at full speed.',
                'colour' => Tint::PRESETS['Moss'],
                'tags' => ['language'],
                'target' => null,
                'started' => 45,
                'sources' => [
                    ['SpanishDict grammar guides', 'https://www.spanishdict.com/guide'],
                    ['Coffee Break Spanish', 'https://coffeebreaklanguages.com'],
                    ['Gramática de uso del español B1-B2 (book)', null],
                ],
                'milestones' => [
                    ['Ser, estar and the present tense', 'The two verbs for "to be", and the regular and irregular present.', 40, [
                        ['Read: Ser vs estar', 'https://www.spanishdict.com/guide/ser-vs-estar', TaskType::Docs],
                        ['Twenty sentences, ser or estar', null, TaskType::Exercise],
                        ['Watch: Irregular verbs in the present', 'https://www.youtube.com', TaskType::Video],
                    ]],
                    ['Reflexive verbs and daily routines', 'Describe a whole day out loud without stopping.', 31, [
                        ['Read: Reflexive verbs', 'https://www.spanishdict.com/guide/reflexive-verbs-in-spanish', TaskType::Docs],
                        ['Record a two-minute morning routine', null, TaskType::Exercise],
                        ['Watch: Easy Spanish street interviews', 'https://www.youtube.com', TaskType::Video],
                    ]],
                    ['Por and para', 'Purpose, cause, deadlines and exchanges.', 22, [
                        ['Read: Por vs para', 'https://www.spanishdict.com/guide/por-vs-para', TaskType::Docs],
                        ['Fill-in-the-gap drill, forty sentences', null, TaskType::Exercise],
                        ['Write a short trip story using both', null, TaskType::Exercise],
                    ]],
                    ['Past tenses, side by side', 'Preterite for what happened, imperfect for how it was.', 8, [
                        ['Read: Preterite vs imperfect', 'https://www.spanishdict.com/guide/preterite-vs-imperfect', TaskType::Docs],
                        ['Retell a film plot in the past', null, TaskType::Exercise],
                        ['Watch: Preterite and imperfect in stories', 'https://www.youtube.com', TaskType::Video],
                    ]],
                    ['The subjunctive mood', 'Wishes, doubts and feelings: when the verb changes, and why.', null, [
                        ['Watch: The present subjunctive, explained', 'https://www.youtube.com', TaskType::Video, $done],
                        ['Read: When to use the subjunctive', 'https://www.spanishdict.com/guide/when-to-use-the-subjunctive', TaskType::Docs, $done],
                        ['Subjunctive after “ojalá”', null, TaskType::Exercise, $doing],
                        ['Write ten wishes for next year', null, TaskType::Exercise],
                        ['Doubt and denial: “no creo que…”', null, TaskType::Exercise],
                    ]],
                    ['Conditionals and hypotheticals', '“If I had time, I would…” in both directions.', null, [
                        ['Read: The conditional tense', 'https://www.spanishdict.com/guide/spanish-conditional-tense', TaskType::Docs],
                        ['Ten “si” sentences about my week', null, TaskType::Exercise],
                        ['Watch: The imperfect subjunctive with “si”', 'https://www.youtube.com', TaskType::Video],
                    ]],
                    ['Reported speech', 'Pass on what someone said, with the tense shifts.', null, [
                        ['Read: Reported speech', 'https://www.spanishdict.com/guide/reported-speech-in-spanish', TaskType::Docs],
                        ['Summarise a podcast episode in writing', null, TaskType::Exercise],
                        ['Swap voice notes with a tandem partner', null, TaskType::Exercise],
                    ]],
                    ['B2 mock exam', 'A full DELE B2 practice paper under exam conditions.', null, [
                        ['Read: The DELE B2 exam format', 'https://examenes.cervantes.es/en/dele/b2', TaskType::Docs],
                        ['Sit a full practice paper', null, TaskType::Exercise],
                    ]],
                ],
            ],
            [
                'title' => 'Data visualisation with D3',
                'description' => 'Charts that explain something: scales, shapes and transitions, ending with a small dashboard of my own.',
                'colour' => Tint::PRESETS['Plum'],
                'tags' => ['javascript'],
                'target' => null,
                'started' => 2,
                'sources' => [
                    ['D3 documentation', 'https://d3js.org'],
                    ['Observable', 'https://observablehq.com'],
                ],
                'milestones' => [
                    ['Scales and axes', 'Map data to pixels and label it honestly.', null, [
                        ['Read: d3-scale', 'https://d3js.org/d3-scale', TaskType::Docs],
                        ['Read: d3-axis', 'https://d3js.org/d3-axis', TaskType::Docs],
                        ['Draw a bar chart with both axes', 'https://observablehq.com', TaskType::Notebook],
                    ]],
                    ['Shapes and paths', 'Lines, areas and arcs from generators.', null, [
                        ['Read: d3-shape', 'https://d3js.org/d3-shape', TaskType::Docs],
                        ['A line chart of my study minutes', 'https://observablehq.com', TaskType::Notebook],
                        ['A donut chart without a legend', null, TaskType::Exercise],
                    ]],
                    ['Transitions and interaction', 'Enter, update and exit, and tooltips that actually help.', null, [
                        ['Read: Joining data', 'https://d3js.org/d3-selection/joining', TaskType::Docs],
                        ['Animate the bar chart between two datasets', 'https://observablehq.com', TaskType::Notebook],
                        ['Add a tooltip that follows the cursor', null, TaskType::Exercise],
                    ]],
                    ['A dashboard of my own', 'Three linked charts on one page.', null, [
                        ['Sketch the dashboard on paper', null, TaskType::Exercise],
                        ['Build and publish it', 'https://observablehq.com', TaskType::Notebook],
                    ]],
                ],
            ],
        ];
    }
}
