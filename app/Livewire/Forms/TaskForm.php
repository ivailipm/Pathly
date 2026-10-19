<?php

namespace App\Livewire\Forms;

use Livewire\Attributes\Validate;
use Livewire\Form;

class TaskForm extends Form
{
    #[Validate('required|string|max:255')]
    public string $title = '';

    // Only http(s), so a task link can never be a `javascript:` URL
    #[Validate('nullable|url:http,https|max:255')]
    public ?string $url = null;
}
