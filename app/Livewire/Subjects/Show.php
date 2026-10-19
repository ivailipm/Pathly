<?php

namespace App\Livewire\Subjects;

use App\Livewire\Forms\MilestoneForm;
use App\Livewire\Forms\SubjectForm;
use App\Models\Milestone;
use App\Models\Subject;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Computed;
use Livewire\Component;

class Show extends Component
{
    public Subject $subject;

    public SubjectForm $form;

    public MilestoneForm $milestoneForm;

    public bool $editing = false;

    public function mount(): void
    {
        // The route's `can` middleware covers real requests; this also covers Livewire::test()
        $this->authorize('view', $this->subject);
    }

    public function edit(): void
    {
        $this->form->fill($this->subject->only(['title', 'description']));

        $this->editing = true;
    }

    public function update(): void
    {
        $this->authorize('update', $this->subject);

        $this->subject->update($this->form->validate());

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
    }

    /**
     * @return Collection<int, Milestone>
     */
    #[Computed]
    public function milestones(): Collection
    {
        return $this->subject->milestones()->get();
    }

    public function render(): View
    {
        return view('livewire.subjects.show')->title($this->subject->title);
    }
}
