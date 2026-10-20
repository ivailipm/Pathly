<?php

namespace App\Livewire\Subjects;

use App\Enums\TaskStatus;
use App\Livewire\Forms\SubjectForm;
use App\Models\Subject;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Subjects')]
class Index extends Component
{
    public SubjectForm $form;

    /**
     * Create a subject for the current user.
     */
    public function save(): void
    {
        Auth::user()->subjects()->create($this->form->validate());

        $this->form->reset();
    }

    /**
     * The current user's subjects, newest first, with task counts for the progress bars.
     *
     * @return Collection<int, Subject>
     */
    #[Computed]
    public function subjects(): Collection
    {
        return Auth::user()->subjects()
            ->withCount(['tasks', 'tasks as done_tasks_count' => fn ($query) => $query->where('status', TaskStatus::Done)])
            ->latest('id')
            ->get();
    }

    public function render(): View
    {
        // Explicit, because Livewire 4 would resolve an `Index` class to `livewire.subjects`
        return view('livewire.subjects.index');
    }
}
