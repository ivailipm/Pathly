<?php

use App\Livewire\Milestones;
use App\Livewire\Subjects;
use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::view('dashboard', 'dashboard')->name('dashboard');

    Route::livewire('subjects', Subjects\Index::class)->name('subjects.index');

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
