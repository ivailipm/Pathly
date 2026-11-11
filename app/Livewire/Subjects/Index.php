<?php

namespace App\Livewire\Subjects;

use App\Enums\SubjectColour;
use App\Enums\SubjectStatus;
use App\Livewire\Forms\SubjectForm;
use App\Models\Milestone;
use App\Models\Subject;
use App\Support\LocalTime;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
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
    public const VIEWS = ['gallery', 'table', 'archive'];

    public SubjectForm $form;

    /**
     * Whether the New subject modal is open. `?new=1` opens it, so the sidebar can link to it.
     */
    #[Url(as: 'new')]
    public bool $creating = false;

    /**
     * gallery, table or archive.
     */
    #[Url]
    public string $view = 'gallery';

    public function mount(): void
    {
        $this->normaliseView();
        $this->setDefaultColour();
    }

    public function updatedView(): void
    {
        $this->normaliseView();
    }

    /**
     * Create a subject for the current user.
     */
    public function save(): void
    {
        $subject = Auth::user()->subjects()->create($this->form->data());

        foreach ($this->form->sourceList() as $source) {
            $subject->addSource($source['title'], $source['url']);
        }

        $this->form->reset();
        $this->setDefaultColour();

        $this->creating = false;
    }

    /**
     * The current user's subjects, newest first, with what the Library cards and table show. Archived subjects
     * only appear in the archive (R9).
     *
     * @return Collection<int, Subject>
     */
    #[Computed]
    public function subjects(): Collection
    {
        return Auth::user()->subjects()
            ->when(
                $this->view === 'archive',
                fn (Builder $query) => $query->where('subjects.status', SubjectStatus::Archived),
                fn (Builder $query) => $query->where('subjects.status', '!=', SubjectStatus::Archived),
            )
            ->withLibraryData()
            ->latest('id')
            ->get();
    }

    #[Computed]
    public function milestoneTotal(): int
    {
        return (int) $this->subjects->sum('milestones_count');
    }

    /**
     * Milestones finished this month (in the display timezone), newest first, outside archived subjects.
     *
     * @return Collection<int, Milestone>
     */
    #[Computed]
    public function recentlyFinished(): Collection
    {
        [$monthStart] = LocalTime::utcRangeOfDay(LocalTime::today()->startOfMonth());

        return Milestone::query()
            ->whereHas('subject', fn (Builder $subject) => $subject
                ->whereBelongsTo(Auth::user())
                ->where('status', '!=', SubjectStatus::Archived))
            ->where('completed_at', '>=', $monthStart)
            ->withNumber()
            ->with('subject')
            ->latest('completed_at')
            ->latest('id')
            ->get();
    }

    public function render(): View
    {
        // Explicit, because Livewire 4 would resolve an `Index` class to `livewire.subjects`
        return view('livewire.subjects.index');
    }

    private function normaliseView(): void
    {
        if (! in_array($this->view, self::VIEWS, true)) {
            $this->view = 'gallery';
        }
    }

    /**
     * R8: a new subject gets the next colour in the rotation, counting every subject the user has (archived too).
     */
    private function setDefaultColour(): void
    {
        $this->form->colour = SubjectColour::forIndex(Auth::user()->subjects()->count())->value;
    }
}
