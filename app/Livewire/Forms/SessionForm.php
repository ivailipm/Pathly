<?php

namespace App\Livewire\Forms;

use App\Support\LocalTime;
use Livewire\Form;

/**
 * A study session logged by hand. Ownership of the subject and task isn't checked here: LogSession::save()
 * loads both through the user's relations, so another user's ids answer 404 instead of a validation error.
 */
class SessionForm extends Form
{
    public ?int $subject_id = null;

    public ?int $task_id = null;

    public int|string|null $minutes = null;

    /** "Y-m-d" in the display timezone */
    public string $date = '';

    /**
     * @return array<string, mixed>
     */
    protected function rules(): array
    {
        return [
            'subject_id' => ['required', 'integer'],
            'task_id' => ['nullable', 'integer'],
            'minutes' => ['required', 'integer', 'min:1', 'max:600'],
            'date' => ['required', 'date_format:Y-m-d', 'before_or_equal:'.LocalTime::today()->toDateString()],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function messages(): array
    {
        return [
            'date.before_or_equal' => __('The date can\'t be in the future.'),
        ];
    }
}
