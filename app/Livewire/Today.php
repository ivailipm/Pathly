<?php

namespace App\Livewire;

use App\Enums\SubjectStatus;
use App\Enums\TaskStatus;
use App\Models\Milestone;
use App\Models\Note;
use App\Models\StudySession;
use App\Models\Subject;
use App\Models\Task;
use App\Support\LocalTime;
use App\Support\Streak;
use App\Support\WeekChart;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * The front page. A top-level page component: an intentional exception to the <Area>/<Name> layout.
 * Everything follows R1 (days in the display timezone) and R9 (paused and archived subjects stay off the desk
 * and out of "Now studying"; archived ones leave the subjects table; their sessions still count).
 *
 * @phpstan-type NowStudying array{subject: Subject, milestone: Milestone, done: int, in_progress: int, total: int}
 * @phpstan-type WeekDay array{date: CarbonImmutable, minutes: int, percent: int, is_today: bool, is_future: bool}
 *
 * @property-read CarbonImmutable $today
 * @property-read array{length: int, includesToday: bool} $streakData
 * @property-read NowStudying|null $nowStudying
 * @property-read list<WeekDay> $week
 * @property-read Collection<int, Subject> $subjects
 */
#[Title('Today')]
class Today extends Component
{
    /**
     * nowStudying(), remembered for the request. Livewire caches computed values with ??=, so a null result
     * would run its queries again on every read; false means "not worked out yet".
     *
     * @var NowStudying|null|false
     */
    private array|null|false $nowStudyingResult = false;

    #[Computed]
    public function greeting(): string
    {
        $hour = LocalTime::now()->hour;

        return match (true) {
            $hour >= 5 && $hour < 12 => __('Good morning'),
            $hour >= 12 && $hour < 18 => __('Good afternoon'),
            default => __('Good evening'),
        };
    }

    #[Computed]
    public function firstName(): string
    {
        return Str::before(trim(Auth::user()->name).' ', ' ');
    }

    #[Computed]
    public function today(): CarbonImmutable
    {
        return LocalTime::today();
    }

    #[Computed]
    public function weekNumber(): int
    {
        return $this->today->isoWeek;
    }

    /**
     * @return array{length: int, includesToday: bool}
     */
    #[Computed]
    public function streakData(): array
    {
        $dates = Auth::user()->studySessions()
            ->completed()
            ->where('started_at', '>=', $this->today->subDays(400)->utc())
            ->pluck('started_at')
            ->map(fn (CarbonImmutable $startedAt) => LocalTime::dateOf($startedAt))
            ->unique()
            ->values()
            ->all();

        return Streak::calculate($dates, $this->today);
    }

    #[Computed]
    public function streak(): int
    {
        return $this->streakData['length'];
    }

    #[Computed]
    public function streakIncludesToday(): bool
    {
        return $this->streakData['includesToday'];
    }

    /**
     * The current milestone of the subject studied last, else of the newest active subject that has one.
     *
     * @return NowStudying|null
     */
    #[Computed]
    public function nowStudying(): ?array
    {
        if ($this->nowStudyingResult === false) {
            $this->nowStudyingResult = $this->findNowStudying();
        }

        return $this->nowStudyingResult;
    }

    /**
     * R5 within the milestone being studied: the first In progress task, else the first To do task.
     */
    #[Computed]
    public function upNext(): ?Task
    {
        $milestone = $this->nowStudying['milestone'] ?? null;

        if ($milestone === null) {
            return null;
        }

        $tasks = $milestone->tasks()->withMinutesLogged()->get();

        return $tasks->first(fn (Task $task) => $task->status === TaskStatus::InProgress)
            ?? $tasks->first(fn (Task $task) => $task->status === TaskStatus::Todo);
    }

    /**
     * Tasks of the milestone being studied that aren't done yet.
     */
    #[Computed]
    public function remainingTasks(): int
    {
        $now = $this->nowStudying;

        return $now === null ? 0 : $now['total'] - $now['done'];
    }

    /**
     * Up to five in-progress tasks across active subjects, most recently touched first.
     *
     * @return Collection<int, Task>
     */
    #[Computed]
    public function desk(): Collection
    {
        return $this->deskQuery()
            ->with('milestone.subject')
            ->latest('tasks.updated_at')
            ->latest('tasks.id')
            ->limit(5)
            ->get();
    }

    #[Computed]
    public function deskCount(): int
    {
        // From the base query: an ORDER BY in a count fails on Postgres
        return $this->deskQuery()->count();
    }

    /**
     * Minutes per local day, Monday to Sunday of this week.
     *
     * @return list<WeekDay>
     */
    #[Computed]
    public function week(): array
    {
        $monday = $this->today->startOfWeek(CarbonImmutable::MONDAY);
        [$from] = LocalTime::utcRangeOfDay($monday);
        [, $to] = LocalTime::utcRangeOfDay($monday->addDays(6));

        $minutesByDate = [];

        Auth::user()->studySessions()
            ->completed()
            ->whereBetween('started_at', [$from, $to])
            ->get(['started_at', 'minutes'])
            ->each(function (StudySession $session) use (&$minutesByDate) {
                $date = LocalTime::dateOf($session->started_at);
                $minutesByDate[$date] = ($minutesByDate[$date] ?? 0) + (int) $session->minutes;
            });

        return WeekChart::build($minutesByDate, $this->today);
    }

    #[Computed]
    public function weekMinutes(): int
    {
        return array_sum(array_column($this->week, 'minutes'));
    }

    #[Computed]
    public function latestNote(): ?Note
    {
        return Note::query()
            ->whereHas('milestone.subject', fn (Builder $subject) => $subject->whereBelongsTo(Auth::user()))
            ->with(['milestone' => fn ($milestones) => $milestones->withNumber(), 'milestone.subject'])
            ->latest()
            ->latest('id')
            ->first();
    }

    /**
     * The Library table data: every non-archived subject, newest first.
     *
     * @return Collection<int, Subject>
     */
    #[Computed]
    public function subjects(): Collection
    {
        return Auth::user()->subjects()
            ->where('subjects.status', '!=', SubjectStatus::Archived)
            ->withLibraryData()
            ->latest('id')
            ->get();
    }

    /**
     * A session was logged from the modal on this page.
     */
    #[On('session-logged')]
    public function refresh(): void
    {
        $this->nowStudyingResult = false;

        unset($this->streakData, $this->week, $this->nowStudying, $this->upNext, $this->subjects);
    }

    public function render(): View
    {
        return view('livewire.today');
    }

    /**
     * @return NowStudying|null
     */
    private function findNowStudying(): ?array
    {
        $active = $this->subjects->filter(fn (Subject $subject) => $subject->status === SubjectStatus::Active);

        $lastSubjectId = Auth::user()->studySessions()
            ->completed()
            ->latest('started_at')
            ->latest('id')
            ->value('subject_id');

        // The subject studied last first, then the rest newest first (the order they're already in)
        $candidates = $active->sortBy(fn (Subject $subject) => $subject->id === $lastSubjectId ? 0 : 1);

        $subject = $candidates->first(fn (Subject $subject) => $subject->current_milestone !== null);
        $milestone = $subject?->current_milestone;

        if ($subject === null || $milestone === null) {
            return null;
        }

        return [
            'subject' => $subject,
            'milestone' => $milestone,
            'done' => $milestone->done_tasks_count,
            'in_progress' => $milestone->in_progress_tasks_count,
            'total' => $milestone->tasks_count,
        ];
    }

    /**
     * In-progress tasks of the user's active subjects.
     *
     * @return Builder<Task>
     */
    private function deskQuery(): Builder
    {
        return Task::query()
            ->where('tasks.status', TaskStatus::InProgress)
            ->whereHas('milestone.subject', fn (Builder $subject) => $subject
                ->whereBelongsTo(Auth::user())
                ->where('subjects.status', SubjectStatus::Active));
    }
}
