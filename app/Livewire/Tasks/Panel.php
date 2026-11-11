<?php

namespace App\Livewire\Tasks;

use App\Enums\TaskStatus;
use App\Events\BoardChanged;
use App\Livewire\Forms\TaskForm;
use App\Models\Note;
use App\Models\Subject;
use App\Models\Task;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * The task side panel: everything about one task (its milestone, link, time, description and linked field notes),
 * with its status, edit, "add link to sources" and delete. Embedded once on a page that shows a subject's tasks;
 * cards open it with $dispatch('open-task', { id }) (add `edit: true` to start in the edit form).
 *
 * After a change it dispatches `task-changed`, so the page's board reloads, and broadcasts BoardChanged to other tabs.
 *
 * @property-read Task|null $task
 * @property-read Collection<int, Note> $notes
 * @property-read bool $linkInSources
 */
class Panel extends Component
{
    #[Locked]
    public Subject $subject;

    /**
     * The open task. Only set by openTask(), which loads it through the subject.
     */
    #[Locked]
    public ?int $taskId = null;

    public bool $open = false;

    public bool $editing = false;

    public TaskForm $form;

    public function mount(): void
    {
        $this->authorize('view', $this->subject);
    }

    #[On('open-task')]
    public function openTask(int $id, bool $edit = false): void
    {
        $task = $this->findTask($id);

        $this->taskId = $task->id;
        $this->open = true;
        $this->cancelEdit();
        unset($this->task, $this->notes, $this->linkInSources);

        if ($edit) {
            $this->edit();
        }
    }

    public function edit(): void
    {
        $task = $this->task ?? abort(404);

        $this->form->fill([
            ...$task->only(['title', 'url', 'description']),
            'type' => $task->type?->value,
        ]);

        $this->editing = true;
    }

    public function save(): void
    {
        $this->authorize('update', $this->subject);

        // No status change, so no syncCompletion() here
        $this->currentTask()->update($this->form->data());

        $this->cancelEdit();
        $this->changed();
    }

    public function cancelEdit(): void
    {
        $this->editing = false;

        $this->form->reset();
        $this->resetValidation();
    }

    /**
     * Move the task to the end of another column, like the board's "Move to" menu.
     */
    public function move(string $status): void
    {
        $this->authorize('update', $this->subject);

        // A string, not a TaskStatus parameter: Livewire would turn an unknown value into null
        $target = TaskStatus::tryFrom($status) ?? abort(422);
        $task = $this->currentTask();

        if ($task->status !== $target) {
            $task->milestone->moveTaskToEnd($task, $target);
            $this->changed();
        }
    }

    public function pinSource(): void
    {
        $this->authorize('update', $this->subject);

        if ($this->subject->pinTaskLink($this->currentTask())) {
            Flux::toast(__('Added to the subject\'s sources.'));
        }

        unset($this->linkInSources);
    }

    public function delete(): void
    {
        $this->authorize('update', $this->subject);

        $task = $this->currentTask();
        $milestoneId = $task->milestone_id;

        if (! $task->milestone->deleteTask($task)) {
            Flux::toast(
                text: __("That timer ran over 10 hours, so it wasn't saved. Log the session by hand."),
                variant: 'warning',
            );
        }

        $this->close();
        $this->changed($milestoneId);
    }

    /**
     * The flyout was closed (the ✕, Escape or a click outside).
     */
    public function close(): void
    {
        $this->open = false;
        $this->taskId = null;

        $this->cancelEdit();
    }

    /**
     * The open task with its numbered milestone and minutes logged; null once it's been deleted (here or elsewhere).
     */
    #[Computed]
    public function task(): ?Task
    {
        if ($this->taskId === null) {
            return null;
        }

        return $this->subject->tasks()
            ->with(['milestone' => fn ($milestones) => $milestones->withNumber()])
            ->withMinutesLogged()
            ->find($this->taskId);
    }

    /**
     * The field notes linked to the task, newest first.
     *
     * @return Collection<int, Note>
     */
    #[Computed]
    public function notes(): Collection
    {
        return $this->task?->notes()->get() ?? new Collection;
    }

    #[Computed]
    public function linkInSources(): bool
    {
        $url = $this->task?->url;

        return $url !== null && $this->subject->sources()->where('url', $url)->exists();
    }

    public function render(): View
    {
        return view('livewire.tasks.panel');
    }

    /**
     * The open task, for an action: 404 when it's gone.
     */
    private function currentTask(): Task
    {
        return $this->findTask((int) $this->taskId);
    }

    /**
     * A task of this subject, never another subject's (404).
     */
    private function findTask(int $id): Task
    {
        /** @var Task */
        return $this->subject->tasks()->with('milestone')->findOrFail($id);
    }

    /**
     * Reload the panel, tell the page's board and broadcast to other tabs on the task's milestone.
     */
    private function changed(?int $milestoneId = null): void
    {
        $milestoneId ??= $this->currentTask()->milestone_id;

        unset($this->task, $this->notes, $this->linkInSources);

        $this->dispatch('task-changed', milestoneId: $milestoneId);

        broadcast(new BoardChanged($milestoneId))->toOthers();
    }
}
