# Kanban board and progress bars (Phase 4)

> Feature: Show each milestone's tasks as a Kanban board with To do / In progress / Done columns,
> move tasks between and within columns by drag-and-drop (with a menu as the keyboard and touch
> fallback), and show "x of y tasks done" progress bars for subjects and milestones.

Built in steps A–C below, one commit each, on branch `feature/kanban` off `main` (after the Phase 3
PR from `feature/subject-crud` is merged). Revision 2 addresses spec-review-1.md. Revision 3
addresses spec-review.md (round 2).

No migrations, policies or routes are added or changed in this phase.

## Context

- Phase 3 (on `feature/subject-crud`) built the CRUD pages:
  - `app/Livewire/Subjects/Index.php`: `#[Computed] subjects()` returns
    `Auth::user()->subjects()->latest('id')->get()`. View `resources/views/livewire/subjects/index.blade.php`
    shows each subject as a `flux:card` link.
  - `app/Livewire/Subjects/Show.php`: `#[Computed] milestones()` returns `$this->subject->milestones()->get()`.
    View `resources/views/livewire/subjects/show.blade.php` shows each milestone as a `flux:card` link.
  - `app/Livewire/Milestones/Show.php`: `#[Computed] tasks()`, actions `addTask`, `toggle`, `editTask`,
    `updateTask`, `cancelTaskEdit`, `deleteTask`, and a private `findTask(int $taskId)` that loads through
    `$this->milestone->tasks()->findOrFail()`. View `resources/views/livewire/milestones/show.blade.php`
    renders the tasks as a checklist (`flux:checkbox` + `wire:click="toggle(...)"`).
    `editTask()` only reads a task into the form and does not authorize; that stays as it is.
- `App\Enums\TaskStatus` has `Todo`, `InProgress`, `Done` and `label()`, which returns `'To do'`,
  `'In Progress'`, `'Done'`. Only the enum references these strings today.
- `Subject::tasks()` is a `HasManyThrough` (no ordering); `Milestone::tasks()` orders by `position`.
  Positions are per milestone with a `[milestone_id, position]` index and no unique constraint.
- `withCount()` drops the relation's `orderBy` from its subquery
  (`vendor/laravel/framework/src/Illuminate/Database/Eloquent/Concerns/QueriesRelationships.php:910`),
  so counting ordered relations is safe on Postgres without `reorder()`. The `*_count` attributes are
  not declared on the models, so they are read only in Blade (which Larastan does not analyse).
- Livewire computed properties are cached for the rest of the request once read. An action that
  changes data and runs before anything reads `$this->columns` gets a fresh value in the render.
- Livewire 4.4.7 ships drag-and-drop as `wire:sort` (built on SortableJS, bundled in `livewire.js`;
  no npm package needed). Source: `vendor/livewire/livewire/dist/livewire.js`, "supportWireSort.js"
  section (from line 16700).
  - The list element gets `wire:sort="method"`; each child gets `wire:sort:item="{{ $id }}"`.
  - `wire:sort:group="name"` on several lists lets items move between them; `wire:sort:group-id="x"`
    passes `x` as a third argument. The handler is called once, on the list the item was dropped
    into, as `method($item, $position, $groupId)`, where `$position` is the 0-based index among the
    `wire:sort:item` children of that list.
  - **Attribute order matters:** the init loop walks the element's attributes in order and stops at
    `wire:sort:group` (`livewire.js:16715-16716`). `wire:sort` must come before `wire:sort:group`
    on the element, or drops silently do nothing.
  - If any element inside the list has `wire:sort:handle`, only handles start a drag.
- Free Flux includes `progress` (`<flux:progress :value="3" :max="10" />`; its JS sets
  `role="progressbar"`, `aria-valuemin`, `aria-valuenow` and `aria-valuemax`, but no accessible name),
  `badge`, `dropdown` and `menu` (`menu.item`, `menu.separator`).
- Free Flux icons are Heroicons. Lucide icons are added with `php artisan flux:icon <name>`, which
  downloads the SVG from GitHub into `resources/views/flux/icon/<name>.blade.php` (the project already
  has four this way, e.g. `layout-grid`). `grip-vertical` is not there yet.
- Phase 3 conventions stay: authorize `update` on the subject in every write action, load tasks
  through `findTask()`, other users get 404.
- Manual checks run at http://pathly.test (main checkout, Herd, Docker running) or at the
  `php artisan serve --port=<port>` URL in a worktree (see CLAUDE.md).

## Requirements

### A. Progress bars
1. Change the `InProgress` label to `'In progress'` (sentence case like the other two). Views show
   labels with `__($status->label())`.
2. Anonymous Blade component `resources/views/components/progress-summary.blade.php` with int props
   `done` and `total` (`done <= total` is assumed). When `total > 0` it shows the text
   `trans_choice(':done of :total task done|:done of :total tasks done', $total, ['done' => $done, 'total' => $total])`
   and `<flux:progress :value="$done" :max="$total" :aria-label="__('Tasks done')" />`.
   When `total` is 0 it shows `__('No tasks yet')` and no bar.
3. `Subjects\Index::subjects()` adds
   `withCount(['tasks', 'tasks as done_tasks_count' => fn ($query) => $query->where('status', TaskStatus::Done)])`.
   Each subject card shows `<x-progress-summary :done="$subject->done_tasks_count" :total="$subject->tasks_count" />`.
   The counts come from that one query, not from a query per subject.
4. `Subjects\Show::milestones()` adds the same `withCount` on `Milestone::tasks()`. Each milestone card
   shows its progress summary. Under the subject heading, the view shows the subject's overall progress
   with `:done="$this->milestones->sum('done_tasks_count')"` and `:total="$this->milestones->sum('tasks_count')"`.
5. The milestone page's progress is part of step B (B7), because it is computed from the board's
   columns, which step A doesn't have yet.

### B. Board on the milestone page (replaces the checklist)
6. Replace `#[Computed] tasks()` with `#[Computed] columns()`, keyed by `TaskStatus` value in
   `TaskStatus::cases()` order. Every status has a key, even when it has no tasks. It loads the tasks
   once with `$this->milestone->tasks()->get()` and builds each column with
   `$tasks->filter(fn (Task $task) => $task->status === $status)->values()`, so each column is an
   Eloquent collection in `position` order. Docblock:
   `@return array<string, \Illuminate\Database\Eloquent\Collection<int, Task>>` (no `groupBy`, whose
   result is a base collection).
7. The view renders three columns side by side from `md:` up (`grid md:grid-cols-3`), stacked below
   that. Each column has a heading `__($status->label())` with a `flux:badge` showing its task count.
   An empty column shows `__('Nothing here yet')`, placed outside the sortable list element (C13).
   Under the milestone heading:
   - when the milestone has tasks, `<x-progress-summary>` with done = the `done` column's count and
     total = the sum of all column counts, computed in the view;
   - when it has none, Phase 3's hint `__('No tasks yet. Add a resource or exercise below.')`
     instead of the component (so the page doesn't also say "No tasks yet" from A2).
8. Each task card (`wire:key="task-{{ $task->id }}"`) shows the title and, when present, the link
   (`flux:link`, `target="_blank"`, `rel="noopener noreferrer"`, as in Phase 3). Cards in the Done
   column show the title struck through, as the checklist did.
9. Each card has one `flux:dropdown` whose first child, the trigger, is
   `<flux:button size="sm" variant="ghost" icon="ellipsis-horizontal" :aria-label="__('Task actions')" />`
   (not a bare `<button>`), followed by a `flux:menu` containing:
   - for each of the two other statuses, an item labelled
     `__('Move to :status', ['status' => __($other->label())])` with
     `wire:click="moveTask({{ $task->id }}, {{ $this->columns[$other->value]->count() }}, '{{ $other->value }}')"`,
     which puts the task at the end of that column;
   - a separator, then "Edit" (`editTask`) and "Delete" (`deleteTask` with the existing
     `wire:confirm`). These replace Phase 3's separate edit and delete icon buttons.
   The inline edit form from Phase 3 (E) renders inside the card being edited.
10. `moveTask(int $taskId, int $position, string $status)` (the status is a `string`, not a
    `TaskStatus` parameter: Livewire binds enum parameters with `tryFrom()`, so an unknown value would
    become `null` and throw a `TypeError` instead of a clean 422):
    - authorizes `update` on the subject;
    - resolves `TaskStatus::tryFrom($status)`; an unknown status aborts with 422 and changes nothing;
    - inside one `DB::transaction()`:
      - loads the task with `findTask($taskId)` (404 for another milestone's task);
      - loads all the milestone's tasks fresh with `$this->milestone->tasks()->get()` (not from
        `$this->columns`), removes the moved task, and groups the rest by status in
        `TaskStatus::cases()` order;
      - inserts the moved task into the target column at `max(0, min($position, count))` and sets its status;
      - renumbers every task in the milestone `1..n` in board order (all To do, then In progress,
        then Done), saving only tasks that are dirty (`position` or `status` changed).
    - It does not read `$this->columns`, so the render after the action shows fresh columns and progress.
11. Remove `toggle()` and the checkbox. Moving a task to Done replaces ticking it.
12. `addTask()` is unchanged: new tasks get status `todo` and max position + 1, so they appear at the
    bottom of To do.

### C. Drag and drop
13. Each column's list element has, in this order, `wire:sort="moveTask"`, `wire:sort:group="tasks"`
    and `wire:sort:group-id="{{ $status->value }}"`, with a Blade comment saying `wire:sort` must stay
    first (see Context). The list element itself has the minimum height (e.g. `min-h-24`), so an empty
    column is still a drop target, and contains only the task cards. Each card has
    `wire:sort:item="{{ $task->id }}"`.
14. Run `php artisan flux:icon grip-vertical` and commit `resources/views/flux/icon/grip-vertical.blade.php`.
    Each card has a drag handle: an element with `wire:sort:handle` containing
    `<flux:icon.grip-vertical variant="micro" />`, with `aria-hidden="true"` on the handle (keyboard
    and screen-reader users move tasks with the menu). Because a handle exists, buttons, links and the
    inline edit inputs never start a drag.
15. No new npm or composer packages.

### Tests
16. Step A adds tests to `tests/Feature/Subjects/IndexTest.php` and `tests/Feature/Subjects/ShowTest.php`,
    and a new Pest unit test `tests/Unit/TaskStatusTest.php` for the label. Steps B and C update
    `tests/Feature/Milestones/ShowTest.php`:
    - the three `toggle` tests are replaced by `moveTask` tests;
    - "authorizes every write action" calls `moveTask` instead of `toggle`;
    - "lists tasks in position order" becomes a `columns` test;
    - "shows an empty state when the milestone has no tasks" (line 175) asserts the new empty board
      (criterion 2) instead of `'No tasks yet.'`.
    (Phase 3 already tests that the index hides other users' subjects.)

## Acceptance criteria

Each step is done when its tests pass (`php artisan test <file>`) and `composer test` passes
(Pint, Larastan level 7, full suite). CI runs the suite on Postgres.

1. **A**
   - Subjects index: a subject with 3 tasks (2 done, 1 in progress) across two milestones shows
     "2 of 3 tasks done"; a subject with 1 done task shows "1 of 1 task done"; a subject without tasks
     shows "No tasks yet"; another user's done tasks do not change the counts.
   - Subjects index runs the same number of queries for 1 subject as for 3: create user A with 1
     subject and user B with 3 subjects, every subject with tasks, before logging starts; then
     `DB::enableQueryLog()`, render `Livewire::actingAs($a)->test(Index::class)`, take
     `count(DB::getQueryLog())`, `DB::flushQueryLog()`, render for `$b`, and compare the two counts.
   - Subject page: each milestone card shows its own count, and the heading area shows the subject
     total ("2 of 3 tasks done" for the data above).
   - `tests/Unit/TaskStatusTest.php`: `TaskStatus::InProgress->label()` is `'In progress'`.
2. **B** `Milestones/ShowTest`:
   - `columns` (`$component->instance()->columns`) has keys `todo`, `in_progress`, `done` in that
     order, each holding that status's task ids in position order.
   - A milestone with no tasks has three empty columns; the page shows "Nothing here yet" three times
     (`substr_count` on the HTML) and "No tasks yet. Add a resource or exercise below.", and no
     progress bar.
   - Milestone progress: "1 of 2 tasks done" for one done and one todo task; "0 of 2 tasks done"
     for two todo tasks.
   - With tasks T1 (todo, 1), T2 (todo, 2), T3 (in_progress, 3), T4 (done, 4):
     `moveTask(T1, 1, 'in_progress')` gives T1 status `in_progress` and positions T2=1, T3=2, T1=3, T4=4.
   - Reordering inside a column: `moveTask(T2, 0, 'todo')` puts T2 before T1 and leaves statuses alone.
   - `moveTask` into an empty column works; a position past the end puts the task last; a negative
     position puts it first.
   - With gapped positions (1, 5, 9), any move leaves positions 1..n.
   - Only changed rows are saved. Create T1–T4, note their `updated_at`, then `$this->travel(5)->minutes()`:
     - a move that changes nothing (`moveTask(T1, 0, 'todo')`) leaves every task's `fresh()->updated_at`
       unchanged;
     - in a separate test, `moveTask(T1, 1, 'in_progress')` gives T1, T2 and T3 a new `updated_at`
       (their status or position changed) and leaves T4's unchanged. This proves the test can see a write.
   - `moveTask(T1, 0, 'archived')` gives 422 (`assertStatus(422)`) and changes nothing.
   - `moveTask` with a task from another milestone gives 404 and changes nothing.
   - Mounted as the owner then `actingAs($otherUser)`: `moveTask` gives 404 and changes nothing.
   - After `moveTask(T1, 0, 'done')` the page shows the updated progress.
   - With task A being edited (`editTask(A)`), `moveTask(B, ...)` keeps `editingTaskId` = A.
   - The page renders "Move to In progress" and "Move to Done" for a todo task.
   - Existing edit/delete task tests still pass.
3. **C** `Milestones/ShowTest`: the HTML contains `wire:sort="moveTask"` three times and
   `wire:sort:item="<id>"` for each task. (The attribute order from C13 is covered by the manual check.)
4. Manual check (URL per Context), logged in as `test@example.com` / `password`: drag a task to another
   column, to another spot in the same column, and into an empty column; each change survives a
   reload and the progress bar updates. Clicking a link or the menu button on a card doesn't start a
   drag. With the keyboard only (Tab, Enter, arrow keys), open a card's menu and move the task. At
   phone width the columns stack and the menu still moves tasks.
5. Checked by eye: the subjects index, subject page and milestone page show progress bars that match
   the counts; the board looks right in light and dark mode.

## Out of scope

- Real-time updates between tabs or users. Phase 5 should broadcast one event per `moveTask` call
  (for example "this milestone's board changed"), not one model event per renumbered row.
- Reordering subjects or milestones; a board across a whole subject; WIP limits; swimlanes.
- Progress on the dashboard; weighting tasks; counting in-progress tasks as partly done.
- A `completed_at` timestamp on tasks (Phase 6 can add one if the nudge needs it).
- New npm packages (SortableJS comes with Livewire).

## Assumptions

Decided by Claude on the developer's behalf (2026-10-19), from spec-review-1.md's questions:

1. Progress = done tasks / all tasks. In-progress counts as not done; the bar shows what is
   finished, and the In progress column already shows what is under way.
2. "Move to…" puts the task at the end of the target column, like adding a task does.
   A drag drops it where it was released, in every column including Done: the user chose that spot.
3. Every move renumbers the whole milestone `1..n` in board order, so positions stay unique per
   milestone and match the board. Milestones are small, so this is a few row updates at most, and
   only changed rows are saved. Phase 5 broadcasts per move, not per row (see Out of scope).
4. The drag handle is Lucide's `grip-vertical` (published with `flux:icon`), the standard handle
   look; the project already uses Lucide icons this way.

Decided by Claude (2026-10-19), from spec-review.md's (round 2) questions:

- An empty milestone keeps Phase 3's hint "No tasks yet. Add a resource or exercise below." in place
  of the progress line; the empty columns say "Nothing here yet".
- A task dragged into Done stays where it was dropped (Assumption 2).

Other assumptions:

5. The board replaces the checklist on the milestone page; there is no list/board switch and no
   separate board route.
6. The board is per milestone, because positions are per milestone.
7. Renumbered tasks get a new `updated_at`. That's fine: the user did act on the milestone.
8. Concurrent moves are ignored (one user, no row locks).
9. An unknown status from the client is treated as tampering: 422, no change.
10. Drag-and-drop is not tested in a browser test; the server action is tested and the drag is
    checked by hand (criterion 4).
