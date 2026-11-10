<?php

namespace App\Livewire\Subjects;

use App\Livewire\Forms\SubjectForm;
use App\Models\Subject;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * @property-read Collection<int, Subject> $subjects
 */
#[Title('Library')]
class Index extends Component
{
    public SubjectForm $form;

    /**
     * Whether the New subject modal is open. `?new=1` opens it, so the sidebar can link to it.
     */
    #[Url(as: 'new')]
    public bool $creating = false;

    /**
     * Create a subject for the current user.
     */
    public function save(): void
    {
        Auth::user()->subjects()->create($this->form->validate());

        $this->form->reset();

        $this->creating = false;
    }

    /**
     * The current user's subjects, newest first, with what the Library cards show.
     *
     * @return Collection<int, Subject>
     */
    #[Computed]
    public function subjects(): Collection
    {
        return Auth::user()->subjects()
            ->withLibraryData()
            ->latest('id')
            ->get();
    }

    #[Computed]
    public function milestoneTotal(): int
    {
        return (int) $this->subjects->sum('milestones_count');
    }

    public function render(): View
    {
        // Explicit, because Livewire 4 would resolve an `Index` class to `livewire.subjects`
        return view('livewire.subjects.index');
    }
}
