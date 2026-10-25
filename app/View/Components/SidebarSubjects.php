<?php

namespace App\View\Components;

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

        $this->subjects = $user->subjects()
            ->latest('id')
            ->get(['id', 'user_id', 'title']);
    }

    public function render(): View
    {
        return view('components.sidebar-subjects');
    }
}
