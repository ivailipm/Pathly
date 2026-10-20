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
use Livewire\Component;

class Show extends Component
{
    public Subject $subject;

    public Milestone $milestone;

    public MilestoneForm $form;

    public TaskForm $taskForm;

    public TaskForm $editTaskForm;

    public bool $editing = false;

    public ?int $editingTaskId = null;

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

    public function addTask(): void
    {
        $this->authorize('update', $this->subject);

        $this->milestone->tasks()->create([
            ...$this->taskForm->validate(),
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
