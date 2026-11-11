<?php

namespace App\Livewire\Subjects;

use App\Livewire\Forms\MilestoneForm;
use App\Livewire\Forms\SubjectForm;
use App\Models\Milestone;
use App\Models\Note;
use App\Models\Subject;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * @property-read Collection<int, Milestone> $milestones
 */
class Show extends Component
{
    public const VIEWS = ['path', 'board'];

    public Subject $subject;

    /**
     * path (the milestones, notes and sources) or board (every task in three columns).
     */
    #[Url]
    public string $view = 'path';

    public SubjectForm $form;

    public MilestoneForm $milestoneForm;

    public bool $editing = false;

    public bool $addingMilestone = false;

    public function mount(): void
    {
        // The route's `can` middleware covers real requests; this also covers Livewire::test()
        $this->authorize('view', $this->subject);

        $this->normaliseView();
    }

    public function updatedView(): void
    {
        $this->normaliseView();
    }

    public function edit(): void
    {
        $this->form->fillFrom($this->subject);

        $this->editing = true;
    }

    public function update(): void
    {
        $this->authorize('update', $this->subject);

        $this->subject->update($this->form->data());

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
        $this->authorize('delete', $this->subject);

        $this->subject->delete();

        $this->redirectRoute('subjects.index', navigate: true);
    }

    public function addMilestone(): void
    {
        $this->authorize('update', $this->subject);

        $this->subject->milestones()->create([
            ...$this->milestoneForm->validate(),
            // reorder() drops the relation's orderBy, which Postgres rejects in an aggregate
            'position' => (int) $this->subject->milestones()->reorder()->max('position') + 1,
        ]);

        $this->milestoneForm->reset();

        $this->addingMilestone = false;
    }

    /**
     * The path: every milestone in order, numbered, with its task counts.
     *
     * @return Collection<int, Milestone>
     */
    #[Computed]
    public function milestones(): Collection
    {
        return $this->subject->milestones()
            ->withNumber()
            ->withTaskCounts()
            ->get();
    }

    /**
     * R4: the first milestone that is not done.
     */
    #[Computed]
    public function currentMilestoneId(): ?int
    {
        return $this->milestones->first(fn (Milestone $milestone) => ! $milestone->is_done)?->id;
    }

    /**
     * Completed session minutes on the subject, including sessions whose task was deleted.
     */
    #[Computed]
    public function minutesLogged(): int
    {
        return (int) $this->subject->studySessions()->completed()->sum('minutes');
    }

    /**
     * "In the margins": the subject's three newest field notes, each with its numbered milestone.
     *
     * @return Collection<int, Note>
     */
    #[Computed]
    public function recentNotes(): Collection
    {
        return $this->subject->notes()
            ->with(['milestone' => fn ($milestones) => $milestones->withNumber()])
            ->latest('notes.created_at')
            ->latest('notes.id')
            ->limit(3)
            ->get();
    }

    #[On('session-logged')]
    public function refreshTotals(): void
    {
        unset($this->minutesLogged);
    }

    /**
     * A task moved, changed or was deleted on the board or in the task panel: the progress changed.
     */
    #[On('task-changed')]
    public function refreshProgress(): void
    {
        unset($this->milestones, $this->currentMilestoneId, $this->doneMilestonesCount, $this->minutesLogged);
    }

    #[Computed]
    public function doneMilestonesCount(): int
    {
        return $this->milestones->filter(fn (Milestone $milestone) => $milestone->is_done)->count();
    }

    public function render(): View
    {
        return view('livewire.subjects.show')->title($this->subject->title);
    }

    private function normaliseView(): void
    {
        if (! in_array($this->view, self::VIEWS, true)) {
            $this->view = 'path';
        }
    }
}
