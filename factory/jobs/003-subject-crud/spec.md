# Subject, milestone and task CRUD pages (Phase 3 MVP loop)

> Feature: Core CRUD UI in Livewire: a subject list/create page, a subject detail page showing its milestones in order, a milestone detail page showing its tasks as a checklist, and a "mark task done" toggle, scoped to the logged-in user, so a user can create a subject → add milestones → add tasks → check them off. Revision 2: subjects, milestones and tasks can also be edited and deleted.

The code will be hand-written by the developer in steps A–E below, one commit each, on branch
`feature/subject-crud`. Step F is a one-line CI change. Revision 3 addresses spec-review.md (round 2).
Revision 4: other users' records return 404 instead of 403, so their ids are not revealed.

## Context

- Data model (done, Phase 1):
  - `app/Models/Subject.php`: fillable `title`, `description`. Has `user()`, `milestones()`
    (ordered by `position`) and `tasks()` (hasManyThrough).
  - `app/Models/Milestone.php`: fillable `subject_id`, `title`, `description`, `position`.
    Has `subject()` and `tasks()` (ordered by `position`).
  - `app/Models/Task.php`: fillable `milestone_id`, `title`, `url`, `status`, `position`.
    `status` is cast to `App\Enums\TaskStatus` (`Todo`, `InProgress`, `Done`) and defaults to `todo`.
  - `app/Models/User.php`: has `subjects()`.
  - Migrations in `database/migrations/2026_10_06_*`: `title` is `string` (255), `description` is
    nullable `text`, `tasks.url` is a nullable `string` (255), and `position` is an unsigned integer
    with a `[parent_id, position]` index. Foreign keys cascade on delete, so deleting a subject
    removes its milestones and tasks.
  - Factories exist for all three models (`TaskFactory` has `inProgress()` and `done()` states).
    `UserFactory` creates verified users, so the seeded `test@example.com` passes `verified`.
- Auth: the Livewire starter kit (Fortify). `routes/web.php` has a
  `Route::middleware(['auth', 'verified'])` group containing `dashboard`. There is no `app/Policies/`
  folder yet. Policies are auto-discovered by name (`App\Policies\SubjectPolicy`).
- Livewire 4 (`livewire/livewire` v4.4). Pattern to copy: `app/Livewire/Settings/Profile.php` with
  its view `resources/views/livewire/settings/profile.blade.php`, registered with `Route::livewire()`
  in `routes/settings.php`. Full-page components use the `layouts::app` layout by default.
  Form objects extend `Livewire\Form` (`vendor/livewire/livewire/src/Features/SupportFormObjects/Form.php`);
  their validation errors are prefixed with the property name (`form.title`).
- Livewire re-applies the route's `auth` and `can` middleware on every update request
  (`vendor/livewire/livewire/src/Mechanisms/PersistentMiddleware/PersistentMiddleware.php`).
  Eloquent model properties cannot be replaced from the browser: `HandleSynths::hydrateForUpdate()`
  (`src/Mechanisms/HandleSynths/HandleSynths.php:128-137`) takes the synth meta only from the
  checksummed snapshot, and `ModelSynth::hydrate()` (`src/Features/SupportModels/ModelSynth.php:55-74`)
  rebuilds the model from that meta's class and key. So `#[Locked]` is not needed on model properties.
  `Livewire::test()` skips the persistent middleware, which is why components also authorize in `mount()`.
- A policy can deny with `Illuminate\Auth\Access\Response::denyAsNotFound()`. The exception handler
  turns an `AuthorizationException` that carries a status into an `HttpException` with that status
  (`vendor/laravel/framework/src/Illuminate/Foundation/Exceptions/Handler.php:774`), so `->can()`
  route middleware and `$this->authorize()` both answer 404. `$user->can()` still returns false.
- In `Livewire::test()`, `AuthorizationException`, `ModelNotFoundException` and `HttpException` are
  rendered as responses, not thrown (`vendor/livewire/livewire/src/Features/SupportTesting/RequestBroker.php:30`),
  and the testable forwards `TestResponse` assertions. Tests assert them with `->assertNotFound()`.
- Livewire update requests skip `ConvertEmptyStringsToNull`, so a cleared textarea submits `''`.
- UI: free Flux components (`vendor/livewire/flux/stubs/resources/views/flux/`, includes `card`,
  `checkbox`, `input`, `textarea`, `button`, `heading`, `text`, `link`).
- Navigation: `resources/views/layouts/app/sidebar.blade.php` has one `flux:sidebar.item` (Dashboard).
- Tests: Pest, `tests/Pest.php` applies `RefreshDatabase` to `tests/Feature`, in-memory SQLite.
  Existing Livewire test example: `tests/Feature/Settings/ProfileUpdateTest.php`.
- Postgres in dev and production rejects `SELECT max(position) ... ORDER BY position`. The
  `milestones()` and `tasks()` relations carry `orderBy('position')`, so aggregates on them must call
  `reorder()` first. SQLite in tests accepts the broken query, so tests will not catch this.

## Requirements

### A. Authorization and form objects
1. `app/Policies/SubjectPolicy.php` with `view`, `update` and `delete`. Each returns an
   `Illuminate\Auth\Access\Response`: `Response::allow()` when `$user->id === $subject->user_id`,
   otherwise `Response::denyAsNotFound()`. No other methods. Another user's subject therefore
   behaves exactly like one that doesn't exist.
2. Three form objects in `app/Livewire/Forms/`, each with `#[Validate]` attributes:
   - `SubjectForm`: `public string $title = ''` (required, string, max 255) and
     `public ?string $description = null` (nullable, string, max 2000).
   - `MilestoneForm`: same two fields and rules as `SubjectForm`.
   - `TaskForm`: `public string $title = ''` (required, string, max 255) and
     `public ?string $url = null` (nullable, `url:http,https`, max 255).
   `description` and `url` are nullable so a form can be filled from a record where they are null.

### B. Subjects index: `/subjects`, route `subjects.index`, component `App\Livewire\Subjects\Index`
3. `#[Title('Subjects')]`, view `resources/views/livewire/subjects/index.blade.php`, route registered
   with `Route::livewire()` inside the `auth` + `verified` group.
4. Lists only the logged-in user's subjects, newest first (`latest('id')`). Each shows its title,
   its description when present, and links to `subjects.show` with `wire:navigate`.
5. `public SubjectForm $form` with a `save()` action:
   `Auth::user()->subjects()->create($this->form->validate())`, then `$this->form->reset()`. The new
   subject appears in the list without a page reload.
6. Empty state text "No subjects yet. Add your first one above." when the user has no subjects.
7. Sidebar item "Subjects" under Dashboard, `:current="request()->routeIs('subjects.*', 'milestones.*')"`.

### C. Subject detail: `/subjects/{subject}`, route `subjects.show`, component `App\Livewire\Subjects\Show`
8. Route has `->can('view', 'subject')`, so other users get 404. Properties: `public Subject $subject`,
   `public SubjectForm $form`, `public MilestoneForm $milestoneForm`, `public bool $editing = false`.
   `mount()` calls `$this->authorize('view', $this->subject)`.
9. Shows the title, description, a link back to `subjects.index`, and the milestones in `position`
   order, each linking to `milestones.show`. Empty state when there are no milestones.
10. Edit: `edit()` calls `$this->form->fill($this->subject->only(['title', 'description']))` and sets
    `editing = true`, which replaces the heading with the form. `edit()` does not authorize: it only
    reads data `mount()` already authorized. `update()` authorizes `update`, saves
    `$this->form->validate()` to the subject and sets `editing = false`. `cancelEdit()` sets
    `editing = false`, resets the form and calls `$this->resetValidation()`.
11. Delete: `delete()` authorizes `delete`, deletes the subject and calls
    `$this->redirectRoute('subjects.index', navigate: true)`. The button uses
    `wire:confirm="Delete this subject and all its milestones and tasks?"`.
12. Add milestone: `addMilestone()` authorizes `update`, validates `$this->milestoneForm` and creates
    the milestone through `$this->subject->milestones()` with
    `position = (int) $this->subject->milestones()->reorder()->max('position') + 1`, then resets
    `$this->milestoneForm`.

### D. Milestone detail: `/subjects/{subject}/milestones/{milestone}`, route `milestones.show`, component `App\Livewire\Milestones\Show`
13. Route has `->scopeBindings()` (milestone from another subject → 404) and `->can('view', 'subject')`
    (other users → 404). Properties: `public Subject $subject`, `public Milestone $milestone`,
    `public MilestoneForm $form`, `public TaskForm $taskForm`, `public bool $editing = false`.
    `mount()` calls `$this->authorize('view', $this->subject)`.
14. Shows the milestone title, description, a link back to `subjects.show`, and its tasks in
    `position` order as a checklist. Each row has `wire:key="task-{{ $task->id }}"` and a
    `flux:checkbox` with `:checked="$task->status === TaskStatus::Done"` and
    `wire:click="toggle({{ $task->id }})"`. `InProgress` tasks show unchecked. A task with a `url`
    shows it as a `flux:link` with `target="_blank"` and `rel="noopener noreferrer"`.
    Empty state when there are no tasks.
15. Edit and delete the milestone exactly like the subject in C10–C11, using `$this->form`. Deleting
    authorizes `update` on the subject, uses
    `wire:confirm="Delete this milestone and all its tasks?"` and redirects to `subjects.show`.
16. Add task: `addTask()` authorizes `update`, validates `$this->taskForm` and creates the task through
    `$this->milestone->tasks()` with `position = (int) $this->milestone->tasks()->reorder()->max('position') + 1`
    (status defaults to `todo`), then resets `$this->taskForm`.
17. `toggle(int $taskId)` authorizes `update`, loads the task with
    `$this->milestone->tasks()->findOrFail($taskId)`, and sets the status to `Todo` when it is `Done`,
    otherwise to `Done`.

### E. Task edit and delete (on the milestone page)
18. Properties: `public TaskForm $editTaskForm` and `public ?int $editingTaskId = null`.
19. `editTask(int $taskId)` loads the task through `$this->milestone->tasks()->findOrFail($taskId)`,
    calls `$this->editTaskForm->fill($task->only(['title', 'url']))`, and sets `editingTaskId`. That
    row then shows inline inputs with Save and Cancel instead of the checkbox. Like `edit()`, it
    does not authorize.
20. `updateTask()` authorizes `update`, loads the task through
    `$this->milestone->tasks()->findOrFail($this->editingTaskId)`, saves
    `$this->editTaskForm->validate()`, then clears `editingTaskId` and resets the form.
    `cancelTaskEdit()` clears both without saving and calls `$this->resetValidation()`.
21. `deleteTask(int $taskId)` authorizes `update`, loads the task through the relationship and deletes
    it. If it was the task being edited, it also clears `editingTaskId` and resets `editTaskForm`.
    The button uses `wire:confirm="Delete this task?"`.

### F. Run the test suite on Postgres in CI
22. In `.github/workflows/tests.yml`, the "Run CI Checks" step sets `env: DB_CONNECTION: pgsql` and
    `DB_DATABASE: pathly`. Real environment variables win over the non-forced `<env>` values in
    `phpunit.xml`, so CI runs the tests on the Postgres service while local runs stay on SQLite.
    This is what catches a missing `reorder()` (see Context), which SQLite hides.

### Tests
23. Pest tests: `tests/Feature/SubjectPolicyTest.php`, `tests/Feature/Subjects/IndexTest.php`,
    `tests/Feature/Subjects/ShowTest.php`, `tests/Feature/Milestones/ShowTest.php`. Step E adds its
    tests to `tests/Feature/Milestones/ShowTest.php`.

## Acceptance criteria

Each step is done when its tests pass (`php artisan test <file>`) and `composer test` passes
(Pint, Larastan level 7, full suite).

1. **A** `SubjectPolicyTest`: via `$user->can(...)`, the owner can `view`, `update` and `delete` their
   subject; another user cannot, and `Gate::forUser($other)->inspect('view', $subject)->status()` is 404.
2. **B** `Subjects/IndexTest`:
   - a guest visiting `/subjects` is redirected to `/login`
   - a user can create a subject; it is stored with their `user_id`, and `form.title` is empty afterwards
   - a missing title fails with `form.title` → `required`; a 256-character title and a
     2001-character description fail validation
   - the page shows the user's own subjects and not another user's
   - the empty state text shows for a user with no subjects
3. **C** `Subjects/ShowTest`:
   - the owner gets 200 on `/subjects/{id}`; another user gets 404 over HTTP, the same as an id that
     doesn't exist; `Livewire::actingAs($other)->test(Show::class, ['subject' => $subject])->assertNotFound()`
   - milestones render in position order
   - adding two milestones to a subject gives them positions 1 and 2; with existing positions 1 and
     3 (a gap after a delete), the next milestone gets 4; a missing title fails with
     `milestoneForm.title` → `required` (`MilestoneForm` has the same rules as `SubjectForm`, so the
     length limits are tested once, in B)
   - `edit()` then `update()` with a new title saves it; an empty title fails with `form.title`;
     `cancelEdit()` clears the error
   - `delete()` removes the subject and its milestones and tasks, and redirects to `/subjects`
   - write actions authorize: mount as the owner, switch with `$this->actingAs($otherUser)`, then
     `call('update')` and `call('addMilestone')` give 404 (`assertNotFound()`) and change nothing
   - the empty state shows for a subject without milestones
4. **D** `Milestones/ShowTest`:
   - the owner gets 200; another user gets 404; a milestone that belongs to a different subject in
     the URL gives 404
   - adding a task gives it status `todo` and position max + 1; `javascript:alert(1)` as `url`
     fails validation, `https://laravel.com` passes; a 256-character title and a 256-character url fail
   - write actions authorize: mount as the owner, switch with `$this->actingAs($otherUser)`, then
     `call('toggle', $task->id)` and `call('addTask')` give 404 (`assertNotFound()`) and change nothing
   - `toggle` changes a `todo` task to `done`, a `done` task to `todo`, and an `in_progress` task to `done`
   - `toggle` with the id of a task from another milestone gives 404 (`assertNotFound()`), and that
     task is unchanged
   - editing and deleting the milestone work as in C; deleting redirects to the subject page
   - the empty state shows for a milestone without tasks
5. **E** `Milestones/ShowTest`:
   - `editTask` + `updateTask` changes a task's title and url; an empty title fails with
     `editTaskForm.title`
   - `deleteTask` removes the task; deleting the task that is being edited clears `editingTaskId`
   - `editTask` and `deleteTask` with a task id from another milestone give 404 (`assertNotFound()`)
     and change nothing
6. **F** After pushing, the `ci` check's "Run CI Checks" log shows the tests passing with
   `DB_CONNECTION: pgsql` in the step's environment.
7. Manual check at http://pathly.test (Herd; or the `php artisan serve` URL in a worktree), Docker
   running, logged in as the seeded `test@example.com` / `password`: Subjects → create a subject →
   open it → add a milestone → open it → add a task → tick it (still ticked after a reload) → edit
   the task → delete it → edit and delete the milestone → delete the subject. Every delete asks for
   confirmation first. A `Done` task shows checked and an `InProgress` task (from the seeder) unchecked.
8. Checked by eye: the sidebar shows "Subjects" and highlights it on subject and milestone pages.

## Out of scope

- Reordering subjects, milestones or tasks, and renumbering positions after a delete.
- Choosing the `in_progress` status in the UI (comes with the Phase 4 Kanban board).
- Progress bars, task counts, Kanban, drag and drop, real-time updates, notifications.
- Changes to the dashboard page, pagination, search, soft deletes, modals.
- A `MilestonePolicy` or `TaskPolicy`.

## Assumptions

1. Decided by the developer: other users get **404**, not 403, so it is never revealed that an id
   exists. Done in the policy with `Response::denyAsNotFound()`, so routes and components need no
   extra code.
2. Decided by the developer: edit and delete are part of this phase.
3. Decided by the developer: the checkbox is binary. Ticking an `InProgress` task makes it `Done`;
   unticking makes it `Todo`.
4. Ownership is checked only on the Subject. Milestones and tasks are protected by scoped bindings
   and by loading them through the parent relationship. Write actions authorize `update` (or
   `delete` for the subject itself) so the policy's methods are used.
5. Any verified user may create subjects, so the policy has no `create` method.
6. New items go last at max position + 1. Deleting leaves gaps in the positions, which ordering
   tolerates. Concurrent inserts are ignored (one user); there is no unique index on position.
7. Editing happens inline on the page, not in a modal.
8. The pages are behind the `verified` middleware, like the dashboard.
9. A cleared description is stored as `''`, not `null`. Views check it with `@if`, so both look the
   same, and converting is not worth the extra code.
10. `wire:confirm` is enough protection for deletes in the MVP, including the cascading subject
    delete. Soft deletes or typed confirmations can come later.
11. Phase 4's drag-and-drop will renumber positions when it reorders, so gaps left by deletes are fine.
