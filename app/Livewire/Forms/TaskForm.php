<?php

namespace App\Livewire\Forms;

use App\Enums\TaskType;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Validate;
use Livewire\Form;

class TaskForm extends Form
{
    #[Validate('required|string|max:255')]
    public string $title = '';

    // Only http(s), so a task link can never be a `javascript:` URL
    #[Validate('nullable|url:http,https|max:255')]
    public ?string $url = null;

    /** What to do, page numbers, where you stopped. Only in the task panel's edit form. */
    #[Validate('nullable|string|max:5000')]
    public ?string $description = null;

    /** A TaskType value, or null/empty for "No type" */
    public ?string $type = null;

    /**
     * @return array<string, mixed>
     */
    protected function rules(): array
    {
        return [
            'type' => ['nullable', Rule::enum(TaskType::class)],
        ];
    }

    /**
     * The validated values, ready for create() or update(): an empty type as null.
     *
     * @return array<string, mixed>
     */
    public function data(): array
    {
        $validated = $this->validate();

        $validated['type'] = filled($validated['type'] ?? null) ? $validated['type'] : null;
        $validated['description'] = filled($validated['description'] ?? null) ? trim((string) $validated['description']) : null;

        return $validated;
    }
}
