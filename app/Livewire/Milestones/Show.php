<?php

namespace App\Livewire\Milestones;

use App\Enums\TaskStatus;
use App\Livewire\Forms\MilestoneForm;
use App\Livewire\Forms\TaskForm;
use App\Models\Milestone;
use App\Models\Subject;
use App\Models\Task;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * @property-read array<string, Collection<int, Task>> $columns
 */
class Show extends Component
{
    public Subject $subject;

    public Milestone $milestone;

    public MilestoneForm $form;

    public TaskForm $taskForm;

    public TaskForm $editTaskForm;

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
            ...$this->taskForm->validate(),
            'status' => TaskStatus::tryFrom($this->addingTo ?? '') ?? TaskStatus::Todo,
            // reorder() drops the relation's orderBy, which Postgres rejects in an aggregate
            'position' => (int) $this->milestone->tasks()->reorder()->max('position') + 1,
        ]);

        $this->taskForm->reset();
    }

    public function editTask(int $taskId): void
    {
        $task = $this->findTask($taskId);

        $this->editTaskForm->fill($task->only(['title', 'url']));

        $this->editingTaskId = $task->id;
    }

    public function updateTask(): void
    {
        $this->authorize('update', $this->subject);

        $this->findTask((int) $this->editingTaskId)->update($this->editTaskForm->validate());

        $this->cancelTaskEdit();
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

        $this->findTask($taskId)->delete();

        if ($this->editingTaskId === $taskId) {
            $this->cancelTaskEdit();
        }
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
        });
    }

    /**
     * The milestone's tasks as board columns, keyed by status value.
     *
     * @return array<string, Collection<int, Task>>
     */
    #[Computed]
    public function columns(): array
    {
        return $this->toColumns($this->milestone->tasks()->get());
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
     * Load a task through this milestone, so an id from another milestone gives 404.
     */
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
