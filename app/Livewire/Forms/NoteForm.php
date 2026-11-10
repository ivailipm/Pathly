<?php

namespace App\Livewire\Forms;

use Livewire\Attributes\Validate;
use Livewire\Form;

/**
 * A field note on a milestone. The linked task is loaded through the milestone when saving, so a task from
 * elsewhere answers 404.
 */
class NoteForm extends Form
{
    #[Validate('required|string|max:2000')]
    public string $body = '';

    #[Validate('nullable|integer')]
    public ?int $task_id = null;
}
