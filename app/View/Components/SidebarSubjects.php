<?php

namespace App\View\Components;

use App\Enums\SubjectStatus;
use App\Models\Subject;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\Component;

class SidebarSubjects extends Component
{
    /** @var Collection<int, Subject> */
    public Collection $subjects;

    public function __construct()
    {
        $user = Auth::user();

        if ($user === null) {
            $this->subjects = new Collection;

            return;
        }

        // Archived subjects leave the sidebar (R9)
        $this->subjects = $user->subjects()
            ->where('status', '!=', SubjectStatus::Archived)
            ->latest('id')
            ->get(['id', 'user_id', 'title', 'colour']);
    }

    public function render(): View
    {
        return view('components.sidebar-subjects');
    }
}
