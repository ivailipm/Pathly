<?php

namespace App\Livewire\Subjects;

use App\Livewire\Forms\SourceForm;
use App\Models\Source;
use App\Models\Subject;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * The "Sources" list on a subject page: links and books the subject is learned from, added, edited and removed
 * in place.
 *
 * @property-read Collection<int, Source> $sources
 */
class Sources extends Component
{
    #[Locked]
    public Subject $subject;

    public SourceForm $form;

    public bool $adding = false;

    /**
     * The source being edited in place. Only set by edit(), which loads it through the subject.
     */
    #[Locked]
    public ?int $editingId = null;

    public function mount(): void
    {
        $this->authorize('view', $this->subject);
    }

    /**
     * @return Collection<int, Source>
     */
    #[Computed]
    public function sources(): Collection
    {
        return $this->subject->sources()->get();
    }

    public function add(): void
    {
        $this->cancel();

        $this->adding = true;
    }

    public function edit(int $id): void
    {
        $source = $this->subject->sources()->findOrFail($id);

        $this->cancel();
        $this->form->fillFrom($source);
        $this->editingId = $source->id;
    }

    public function save(): void
    {
        $this->authorize('update', $this->subject);

        $data = $this->form->data();

        if ($this->editingId !== null) {
            $this->subject->sources()->findOrFail($this->editingId)->update($data);
        } else {
            $this->subject->addSource($data['title'], $data['url']);
        }

        $this->cancel();
        unset($this->sources);
    }

    public function cancel(): void
    {
        $this->adding = false;
        $this->editingId = null;

        $this->form->reset();
        $this->resetValidation();
    }

    public function remove(int $id): void
    {
        $this->authorize('update', $this->subject);

        $this->subject->sources()->findOrFail($id)->delete();

        if ($this->editingId === $id) {
            $this->cancel();
        }

        unset($this->sources);
    }

    public function render(): View
    {
        return view('livewire.subjects.sources');
    }
}
