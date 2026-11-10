<?php

namespace App\Livewire\Sessions;

use App\Enums\SubjectStatus;
use App\Livewire\Forms\SessionForm;
use App\Models\Milestone;
use App\Models\Subject;
use App\Models\Task;
use App\Support\LocalTime;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * The "Log a session" modal. Embedded on a milestone page (the subject is fixed, the tasks are the milestone's)
 * and on Today (any non-archived subject). Buttons open it with <flux:modal.trigger name="log-session">.
 *
 * @property-read Collection<int, Subject> $subjectOptions
 * @property-read Collection<int, Task> $taskOptions
 */
class LogSession extends Component
{
    #[Locked]
    public ?Milestone $milestone = null;

    public SessionForm $form;

    public function mount(?Milestone $milestone = null): void
    {
        $this->milestone = $milestone;

        if ($milestone !== null) {
            // 404 for another user's milestone
            $this->authorize('view', $milestone->subject);
        }

        $this->resetForm();
    }

    /**
     * A task belongs to one subject, so picking another subject clears it.
     */
    public function updatedFormSubjectId(): void
    {
        $this->form->task_id = null;
    }

    /**
     * The user's subjects to choose from on Today (archived ones left out).
     *
     * @return Collection<int, Subject>
     */
    #[Computed]
    public function subjectOptions(): Collection
    {
        return Auth::user()->subjects()
            ->where('status', '!=', SubjectStatus::Archived)
            ->orderBy('title')
            ->get(['id', 'user_id', 'title']);
    }

    /**
     * The milestone's tasks, or the selected subject's tasks on Today.
     *
     * @return Collection<int, Task>
     */
    #[Computed]
    public function taskOptions(): Collection
    {
        if ($this->milestone !== null) {
            return $this->milestone->tasks()->get(['id', 'milestone_id', 'title']);
        }

        if ($this->form->subject_id === null) {
            return new Collection;
        }

        $subject = $this->subjectOptions->firstWhere('id', $this->form->subject_id);

        return $subject === null
            ? new Collection
            : $subject->tasks()->reorder()->orderBy('tasks.title')->get(['tasks.id', 'tasks.title']);
    }

    public function save(): void
    {
        $this->form->validate();

        $subject = $this->subject();
        $task = $this->task($subject);

        Auth::user()->studySessions()->create([
            'subject_id' => $subject->id,
            'task_id' => $task?->id,
            // Stored at local noon, so it lands on the chosen day whatever the timezone (spec assumption 6)
            'started_at' => LocalTime::noonUtc($this->form->date),
            'minutes' => (int) $this->form->minutes,
        ]);

        $this->resetForm();

        Flux::modal('log-session')->close();

        $this->dispatch('session-logged');
    }

    public function render(): View
    {
        return view('livewire.sessions.log-session');
    }

    /**
     * On a milestone page, its subject (archived or not); otherwise one of the user's non-archived subjects.
     */
    private function subject(): Subject
    {
        if ($this->milestone !== null) {
            $this->authorize('update', $this->milestone->subject);

            return $this->milestone->subject;
        }

        return Auth::user()->subjects()
            ->where('status', '!=', SubjectStatus::Archived)
            ->findOrFail($this->form->subject_id);
    }

    /**
     * The chosen task, loaded through the milestone or the subject, so another task's id answers 404.
     */
    private function task(Subject $subject): ?Task
    {
        if ($this->form->task_id === null) {
            return null;
        }

        return $this->milestone !== null
            ? $this->milestone->tasks()->findOrFail($this->form->task_id)
            : $subject->tasks()->findOrFail($this->form->task_id);
    }

    private function resetForm(): void
    {
        $this->form->reset();
        $this->resetValidation();

        $this->form->subject_id = $this->milestone?->subject_id;
        $this->form->date = LocalTime::today()->toDateString();
    }
}
