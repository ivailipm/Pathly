<?php

namespace App\Livewire\Forms;

use App\Enums\SubjectColour;
use App\Enums\SubjectStatus;
use App\Models\Subject;
use App\Rules\SourceList;
use App\Rules\TagList;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Validate;
use Livewire\Form;

class SubjectForm extends Form
{
    #[Validate('required|string|max:255')]
    public string $title = '';

    #[Validate('nullable|string|max:2000')]
    public ?string $description = null;

    public string $status = 'active';

    /** "Y-m-d" from <input type="date">, or empty */
    public ?string $target_date = null;

    /** Comma-separated in the form, saved as a list */
    public string $tags = '';

    public string $colour = 'clay';

    /** Only in the New subject form: one source per line, saved by sourceList() */
    public string $sources = '';

    /**
     * @return array<string, mixed>
     */
    protected function rules(): array
    {
        return [
            'status' => ['required', Rule::enum(SubjectStatus::class)],
            'target_date' => ['nullable', 'date_format:Y-m-d'],
            'tags' => ['nullable', 'string', 'max:300', new TagList],
            'colour' => ['required', Rule::enum(SubjectColour::class)],
            'sources' => ['nullable', 'string', 'max:20000', new SourceList],
        ];
    }

    /**
     * The validated values, ready for create() or update(): tags as a list, an empty date as null.
     *
     * @return array<string, mixed>
     */
    public function data(): array
    {
        $validated = $this->validate();

        $validated['tags'] = TagList::parse($validated['tags'] ?? null);
        $validated['target_date'] = filled($validated['target_date'] ?? null) ? $validated['target_date'] : null;

        // Not a column: saved as Source rows
        unset($validated['sources']);

        return $validated;
    }

    /**
     * The sources typed in the form, parsed (call after data(), which validates them).
     *
     * @return list<array{title: string, url: string|null}>
     */
    public function sourceList(): array
    {
        return SourceList::parse($this->sources);
    }

    public function fillFrom(Subject $subject): void
    {
        $this->fill([
            'title' => $subject->title,
            'description' => $subject->description,
            'status' => $subject->status->value,
            'target_date' => $subject->target_date?->toDateString(),
            'tags' => implode(', ', $subject->tags),
            'colour' => $subject->colour->value,
        ]);
    }
}
