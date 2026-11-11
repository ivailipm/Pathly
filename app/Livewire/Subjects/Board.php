<?php

namespace App\Livewire\Subjects;

use App\Enums\TaskStatus;
use App\Events\BoardChanged;
use App\Models\Milestone;
use App\Models\Subject;
use App\Models\Task;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * The subject's Board tab: every task of every milestone in To do / In progress / Done, grouped by milestone in path
 * order. Cards move between columns by drag and drop (wire:sort) or the "Move to" menu, and never leave their
 * milestone. Titles open the task panel. Finished milestones are folded away in Done until opened.
 *
 * @property-read Collection<int, Milestone> $milestones
 * @property-read array<string, Collection<int, Task>> $columns
 */
class Board extends Component
{
    #[Locked]
    public Subject $subject;

    /**
     * Finished milestones whose done tasks are shown. The rest are folded into their heading.
     *
     * @var list<int>
     */
    public array $expanded = [];

    public function mount(): void
    {
        $this->authorize('view', $this->subject);
    }

    /**
     * Move a task to a column and a spot in it. wire:sort passes the task's index among the column's visible cards,
     * across milestones; the task stays in its milestone, so only that milestone's cards above the drop count.
     * The "Move to" menu passes the column's size: the end of its milestone in that column.
     */
    public function moveTask(int $taskId, int $position, string $status): void
    {
        $this->authorize('update', $this->subject);

        // A string, not a TaskStatus parameter: Livewire would turn an unknown value into null
        $target = TaskStatus::tryFrom($status) ?? abort(422);
        $task = $this->findTask($taskId);

        $index = $this->visible($this->columns[$target->value])
            ->reject(fn (Task $other) => $other->id === $task->id)
            ->take(max(0, $position))
            ->where('milestone_id', $task->milestone_id)
            ->count();

        $task->milestone->moveTask($task, $target, $index);

        $this->refreshBoard();

        // The subject page's progress (not this board again: it's already fresh)
        $this->dispatch('task-changed', milestoneId: $task->milestone_id)->to(Show::class);

        broadcast(new BoardChanged($task->milestone_id))->toOthers();
    }

    /**
     * Show or fold a finished milestone's done tasks.
     */
    public function toggle(int $milestoneId): void
    {
        $this->expanded = in_array($milestoneId, $this->expanded, true)
            ? array_values(array_diff($this->expanded, [$milestoneId]))
            : [...$this->expanded, $milestoneId];
    }

    /**
     * A task changed here, in the task panel or in another tab (BoardChanged on one of the subject's milestones).
     */
    public function refreshBoard(): void
    {
        unset($this->milestones, $this->columns);
    }

    /**
     * The subject's milestones in path order, numbered, with their task counts.
     *
     * @return Collection<int, Milestone>
     */
    #[Computed]
    public function milestones(): Collection
    {
        return $this->subject->milestones()->withNumber()->withTaskCounts()->get();
    }

    /**
     * Every task as board columns keyed by status value, each column in path order: by milestone, then position.
     *
     * @return array<string, Collection<int, Task>>
     */
    #[Computed]
    public function columns(): array
    {
        $milestones = $this->milestones->keyBy('id');
        $order = $this->milestones->pluck('id')->flip();

        $tasks = Task::query()
            ->whereIn('milestone_id', $milestones->keys())
            ->withMinutesLogged()
            ->get()
            ->each(fn (Task $task) => $task->setRelation('milestone', $milestones[$task->milestone_id]))
            ->sortBy([
                fn (Task $a, Task $b) => $order[$a->milestone_id] <=> $order[$b->milestone_id],
                fn (Task $a, Task $b) => [$a->position, $a->id] <=> [$b->position, $b->id],
            ]);

        $columns = [];

        foreach (TaskStatus::cases() as $status) {
            $columns[$status->value] = $tasks->filter(fn (Task $task) => $task->status === $status)->values();
        }

        return $columns;
    }

    /**
     * Whether a milestone's cards are folded away: finished and not opened.
     */
    public function isFolded(Milestone $milestone): bool
    {
        return $milestone->is_done && ! in_array($milestone->id, $this->expanded, true);
    }

    public function render(): View
    {
        return view('livewire.subjects.board');
    }

    /**
     * Live updates from every milestone's board channel, and from the task panel.
     *
     * @return array<string, string>
     */
    protected function getListeners(): array
    {
        $listeners = ['task-changed' => 'refreshBoard'];

        foreach ($this->milestones as $milestone) {
            $listeners["echo-private:milestones.{$milestone->id},.board.changed"] = 'refreshBoard';
        }

        return $listeners;
    }

    /**
     * The cards a column shows: without the folded milestones' tasks.
     *
     * @param  Collection<int, Task>  $column
     * @return Collection<int, Task>
     */
    private function visible(Collection $column): Collection
    {
        return $column->reject(fn (Task $task) => $this->isFolded($task->milestone))->values();
    }

    /**
     * A task of this subject, never another subject's (404).
     */
    private function findTask(int $id): Task
    {
        /** @var Task */
        return $this->subject->tasks()->with('milestone')->findOrFail($id);
    }
}
