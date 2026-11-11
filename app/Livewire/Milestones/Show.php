<?php

namespace App\Livewire\Milestones;

use App\Enums\TaskStatus;
use App\Events\BoardChanged;
use App\Livewire\Forms\MilestoneForm;
use App\Livewire\Forms\NoteForm;
use App\Livewire\Forms\TaskForm;
use App\Models\Milestone;
use App\Models\Note;
use App\Models\StudySession;
use App\Models\Subject;
use App\Models\Task;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * @property-read array<string, Collection<int, Task>> $columns
 * @property-read StudySession|null $runningSession
 * @property-read int|null $upNextTaskId
 * @property-read list<string> $sourceUrls
 */
class Show extends Component
{
    /**
     * A timer running longer than this is treated as forgotten: discarded, not saved (spec assumption 11).
     */
    public const MAX_TIMER_MINUTES = 600;

    public Subject $subject;

    public Milestone $milestone;

    public MilestoneForm $form;

    public TaskForm $taskForm;

    public TaskForm $editTaskForm;

    public NoteForm $noteForm;

    public bool $editing = false;

    public ?int $editingTaskId = null;

    /**
     * The status value of the column whose add form is open. Only set by openAddTask(), so a tampered
     * request can't add a task to a column that wasn't opened.
     */
    #[Locked]
    public ?string $addingTo = null;

    public function mount(): void
    {
        // The route's `can` middleware covers real requests; this also covers Livewire::test()
        $this->authorize('view', $this->subject);

        $this->noteForm->task_id = $this->defaultNoteTaskId();
    }

    public function edit(): void
    {
        $this->form->fill($this->milestone->only(['title', 'description']));

        $this->editing = true;
    }

    public function update(): void
    {
        $this->authorize('update', $this->subject);

        $this->milestone->update($this->form->validate());

        $this->editing = false;

        $this->broadcastBoard();
    }

    public function cancelEdit(): void
    {
        $this->editing = false;

        $this->form->reset();
        $this->resetValidation();
    }

    public function delete(): void
    {
        $this->authorize('update', $this->subject);

        $this->milestone->delete();

        $this->redirectRoute('subjects.show', ['subject' => $this->subject], navigate: true);
    }

    /**
     * Open the add form at the bottom of a column; the same column again closes it.
     */
    public function openAddTask(string $status): void
    {
        $target = TaskStatus::tryFrom($status) ?? abort(422);

        if ($this->addingTo === $target->value) {
            $this->closeAddTask();

            return;
        }

        $this->addingTo = $target->value;

        $this->taskForm->reset();
        $this->resetValidation();
    }

    public function closeAddTask(): void
    {
        $this->addingTo = null;

        $this->taskForm->reset();
        $this->resetValidation();
    }

    /**
     * Add a task last in the open column (To do when none is open). The form stays open for the next one.
     */
    public function addTask(): void
    {
        $this->authorize('update', $this->subject);

        $this->milestone->tasks()->create([
            ...$this->taskForm->data(),
            'status' => TaskStatus::tryFrom($this->addingTo ?? '') ?? TaskStatus::Todo,
            // reorder() drops the relation's orderBy, which Postgres rejects in an aggregate
            'position' => (int) $this->milestone->tasks()->reorder()->max('position') + 1,
        ]);

        // A new task reopens a finished milestone (unless it was added straight to Done)
        $this->milestone->syncCompletion();

        $this->taskForm->reset();

        $this->broadcastBoard();
    }

    public function editTask(int $taskId): void
    {
        $task = $this->findTask($taskId);

        $this->editTaskForm->fill([...$task->only(['title', 'url']), 'type' => $task->type?->value]);

        $this->editingTaskId = $task->id;
    }

    public function updateTask(): void
    {
        $this->authorize('update', $this->subject);

        // No status change, so no syncCompletion() here
        $this->findTask((int) $this->editingTaskId)->update($this->editTaskForm->data());

        $this->cancelTaskEdit();

        $this->broadcastBoard();
    }

    public function cancelTaskEdit(): void
    {
        $this->editingTaskId = null;

        $this->editTaskForm->reset();
        $this->resetValidation();
    }

    public function deleteTask(int $taskId): void
    {
        $this->authorize('update', $this->subject);

        $task = $this->findTask($taskId);
        $running = $this->runningSession;

        // Keep the time so far: the session stays on the subject after the task is gone
        if ($running?->task_id === $task->id) {
            $this->stopSession($running);
        }

        $task->delete();

        // Deleting the last task that wasn't done finishes the milestone; deleting the last task reopens it
        $this->milestone->syncCompletion();

        if ($this->editingTaskId === $taskId) {
            $this->cancelTaskEdit();
        }

        $this->broadcastBoard();
    }

    /**
     * Move a task to a column and a spot in it. Called by the "Move to" menu and by wire:sort,
     * which passes the dropped task's id, its index in the column and the column's status.
     */
    public function moveTask(int $taskId, int $position, string $status): void
    {
        $this->authorize('update', $this->subject);

        // A string, not a TaskStatus parameter: Livewire would turn an unknown value into null
        $target = TaskStatus::tryFrom($status) ?? abort(422);

        DB::transaction(function () use ($taskId, $position, $target) {
            $task = $this->findTask($taskId);

            $columns = $this->toColumns($this->milestone->tasks()->whereKeyNot($task->id)->get());

            $task->status = $target;
            $column = $columns[$target->value];
            $column->splice(max(0, min($position, $column->count())), 0, [$task]);

            // Renumber the whole milestone in board order; save() skips tasks that didn't change
            $next = 1;

            foreach ($columns as $columnTasks) {
                foreach ($columnTasks as $columnTask) {
                    $columnTask->position = $next++;
                    $columnTask->save();
                }
            }

            $this->milestone->syncCompletion();
        });

        // After the transaction, so other tabs never reload a half-saved board
        $this->broadcastBoard();
    }

    /**
     * Start a timer on a task. Any other running timer of the user (on any milestone) is stopped first.
     */
    public function startTimer(int $taskId): void
    {
        $this->authorize('update', $this->subject);

        $task = $this->findTask($taskId);

        Auth::user()->studySessions()->running()->get()
            ->each(fn (StudySession $session) => $this->stopSession($session));

        Auth::user()->studySessions()->create([
            'subject_id' => $this->subject->id,
            'task_id' => $task->id,
            'started_at' => now(),
        ]);

        unset($this->runningSession, $this->minutesLogged, $this->columns);

        $this->broadcastBoard();
    }

    /**
     * Stop the running timer on this milestone and save its minutes.
     */
    public function stopTimer(): void
    {
        $this->authorize('update', $this->subject);

        $this->stopSession($this->runningSession ?? abort(404));

        unset($this->runningSession, $this->minutesLogged, $this->columns);

        $this->broadcastBoard();
    }

    /**
     * Add a field note, linked to a task of this milestone or to none.
     */
    public function addNote(): void
    {
        $this->authorize('update', $this->subject);

        $data = $this->noteForm->validate();

        if ($data['task_id'] !== null) {
            // 404 for a task outside this milestone
            $this->findTask($data['task_id']);
        }

        $this->milestone->notes()->create($data);

        $this->noteForm->reset();
        $this->noteForm->task_id = $this->defaultNoteTaskId();

        unset($this->notes);

        $this->broadcastBoard();
    }

    /**
     * A session was logged by hand (LogSession): the totals changed.
     */
    #[On('session-logged')]
    public function refreshTotals(): void
    {
        unset($this->minutesLogged, $this->columns, $this->notes);
    }

    /**
     * Another tab changed this board (BoardChanged over Reverb): re-read everything it can have touched.
     * The leading dot in ".board.changed" makes Echo use the broadcastAs() name as it is.
     */
    #[On('echo-private:milestones.{milestone.id},.board.changed')]
    public function refreshBoard(): void
    {
        $this->milestone->refresh();

        unset($this->columns, $this->notes, $this->upNextTaskId, $this->runningSession, $this->minutesLogged);
    }

    /**
     * The milestone's field notes, newest first, each with its task and that day's minutes on it.
     *
     * @return Collection<int, Note>
     */
    #[Computed]
    public function notes(): Collection
    {
        $notes = $this->milestone->notes()->with('task')->get();

        Note::attachMinutes($notes);

        return $notes;
    }

    /**
     * The milestone's tasks as board columns, keyed by status value, each with minutes_logged.
     *
     * @return array<string, Collection<int, Task>>
     */
    #[Computed]
    public function columns(): array
    {
        return $this->toColumns($this->milestone->tasks()->withMinutesLogged()->get());
    }

    /**
     * The user's running timer, when it is on one of this milestone's tasks.
     */
    #[Computed]
    public function runningSession(): ?StudySession
    {
        return Auth::user()->studySessions()
            ->running()
            ->whereIn('task_id', $this->milestoneTaskIds())
            ->latest('started_at')
            ->first();
    }

    /**
     * Completed session minutes on this milestone's tasks.
     */
    #[Computed]
    public function minutesLogged(): int
    {
        return (int) StudySession::query()
            ->completed()
            ->whereIn('task_id', $this->milestoneTaskIds())
            ->sum('minutes');
    }

    /**
     * R2: this milestone's place in its subject's path.
     */
    #[Computed]
    public function number(): int
    {
        // Not value('number'): value() replaces the selected columns and drops the subquery
        return (int) Milestone::query()->whereKey($this->milestone->id)->withNumber()->first()?->number;
    }

    #[Computed]
    public function milestoneCount(): int
    {
        return $this->subject->milestones()->reorder()->count();
    }

    /**
     * R5: the first In progress task in board order, else the first To do task.
     */
    #[Computed]
    public function upNextTaskId(): ?int
    {
        return $this->columns[TaskStatus::InProgress->value]->first()->id
            ?? $this->columns[TaskStatus::Todo->value]->first()?->id;
    }

    public function render(): View
    {
        return view('livewire.milestones.show')->title($this->milestone->title);
    }

    /**
     * Tell the milestone's other open tabs to reload the board. Once per action, at the end.
     */
    private function broadcastBoard(): void
    {
        broadcast(new BoardChanged($this->milestone->id))->toOthers();
    }

    /**
     * A new note links to the task being timed, else to the up next task.
     */
    private function defaultNoteTaskId(): ?int
    {
        return $this->runningSession->task_id ?? $this->upNextTaskId;
    }

    /**
     * Save a running session's minutes (at least 1), or discard it with a toast when it ran past the limit.
     */
    private function stopSession(StudySession $session): void
    {
        // Carbon 3 returns a signed float
        $minutes = (int) ceil(abs($session->started_at->diffInSeconds(now())) / 60);

        if ($minutes > self::MAX_TIMER_MINUTES) {
            $session->delete();

            Flux::toast(
                text: __("That timer ran over 10 hours, so it wasn't saved. Log the session by hand."),
                variant: 'warning',
            );

            return;
        }

        $session->update(['minutes' => max(1, $minutes)]);
    }

    /**
     * A subquery of this milestone's task ids.
     *
     * @return Builder<Task>
     */
    private function milestoneTaskIds(): Builder
    {
        return Task::query()->select('tasks.id')->where('tasks.milestone_id', $this->milestone->id);
    }

    /**
     * Load a task through this milestone, so an id from another milestone gives 404.
     */
    /**
     * Add a task's link to the subject's sources, named after the task. Does nothing when it's already there.
     */
    public function pinSource(int $taskId): void
    {
        $this->authorize('update', $this->subject);

        $task = $this->findTask($taskId);

        if ($task->url === null) {
            return;
        }

        if (! $this->subject->sources()->where('url', $task->url)->exists()) {
            $this->subject->addSource($task->title, $task->url);
        }

        unset($this->sourceUrls);

        Flux::toast(__('Added to the subject\'s sources.'));
    }

    /**
     * The links already in the subject's sources, so a task's "Add link to sources" hides once it's there.
     *
     * @return list<string>
     */
    #[Computed]
    public function sourceUrls(): array
    {
        return array_values($this->subject->sources()->whereNotNull('url')->pluck('url')->all());
    }

    private function findTask(int $taskId): Task
    {
        return $this->milestone->tasks()->findOrFail($taskId);
    }

    /**
     * Split tasks into board columns: one per status, in board order, even when empty.
     *
     * @param  Collection<int, Task>  $tasks
     * @return array<string, Collection<int, Task>>
     */
    private function toColumns(Collection $tasks): array
    {
        $columns = [];

        foreach (TaskStatus::cases() as $status) {
            $columns[$status->value] = $tasks->filter(fn (Task $task) => $task->status === $status)->values();
        }

        return $columns;
    }
}
