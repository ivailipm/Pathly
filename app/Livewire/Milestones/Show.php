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

    /**
     * Tick a task off, or untick it. An in-progress task counts as not done.
     */
    public function toggle(int $taskId): void
    {
        $this->authorize('update', $this->subject);

        $task = $this->findTask($taskId);

        $task->update([
            'status' => $task->status === TaskStatus::Done ? TaskStatus::Todo : TaskStatus::Done,
        ]);
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
     * @return Collection<int, Task>
     */
    #[Computed]
    public function tasks(): Collection
    {
        return $this->milestone->tasks()->get();
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
}
