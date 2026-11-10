<?php

namespace App\Livewire\Notes;

use App\Models\Note;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Every field note of the user, newest first.
 *
 * @property-read LengthAwarePaginator<int, Note> $notes
 */
#[Title('Field notes')]
class Index extends Component
{
    use WithPagination;

    public const PER_PAGE = 20;

    /**
     * @return LengthAwarePaginator<int, Note>
     */
    #[Computed]
    public function notes(): LengthAwarePaginator
    {
        $notes = Note::query()
            ->whereHas('milestone.subject', fn (Builder $subject) => $subject->whereBelongsTo(Auth::user()))
            ->with(['milestone' => fn ($milestones) => $milestones->withNumber(), 'milestone.subject', 'task'])
            ->latest()
            ->latest('id')
            ->paginate(self::PER_PAGE);

        Note::attachMinutes($notes->getCollection());

        return $notes;
    }

    public function render(): View
    {
        // Explicit, because Livewire 4 would resolve an `Index` class to `livewire.notes`
        return view('livewire.notes.index');
    }
}
