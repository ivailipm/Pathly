<?php

use App\Livewire\Milestones;
use App\Livewire\Notes;
use App\Livewire\Subjects;
use App\Livewire\Today;
use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    // Today keeps the starter kit's URL and name: Fortify redirects to `dashboard` after login
    Route::livewire('dashboard', Today::class)->name('dashboard');

    Route::livewire('subjects', Subjects\Index::class)->name('subjects.index');

    Route::livewire('notes', Notes\Index::class)->name('notes.index');

    // Other users' subjects answer 404 (SubjectPolicy denies as not found)
    Route::livewire('subjects/{subject}', Subjects\Show::class)
        ->name('subjects.show')
        ->can('view', 'subject');

    // scopeBindings(): the milestone must belong to the subject in the URL, otherwise 404
    Route::livewire('subjects/{subject}/milestones/{milestone}', Milestones\Show::class)
        ->name('milestones.show')
        ->scopeBindings()
        ->can('view', 'subject');
});

require __DIR__.'/settings.php';
