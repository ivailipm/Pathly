# Spec review (round 2, revision 2)

## Verdict
NEEDS WORK (small: a step-ordering bug, an ambiguous "No tasks" assertion and one existing test the spec forgets. Everything else from round 1 is fixed or deliberately left.)

## Round 1 follow-up
Fixed in revision 2:
- R1-1 icon: step 14 now runs `php artisan flux:icon grip-vertical` and commits the file. Verified: `IconCommand` exists, takes `{icons?*}`, fetches from the Lucide GitHub repo and writes `resources/views/flux/icon/<name>.blade.php`. The generated stub supports `variant="micro"` (size-4, stroke 2.5), and the project's published `layout-grid` has the same `micro` branch, so `<flux:icon.grip-vertical variant="micro" />` works.
- R1-2 attribute order: now stated in Context and C13, with a Blade comment required. Verified at `livewire.js:16713-16716`: `group-id` is `continue`d, `wire:sort:group` hits `return`. The substring-count test can't catch the order, and the spec says so and relies on the manual check. Acceptable.
- R1-3 handler semantics and min-height: fixed (C13: the list itself has `min-h-24` and holds only cards; the empty text sits outside it).
- R1-4 fresh data: fixed (B10 loads `tasks()->get()` in the transaction and never reads `$this->columns`). The Livewire computed-cache note in Context is accurate for a render after the action.
- R1-5 label and "Move to" string: fixed (A1, B9). Grep confirms only the enum holds `'In Progress'`.
- R1-6 `editTask` has no authorization: fixed by stating it stays that way.
- R1-7 undeclared `*_count`: fixed by reading them only in Blade. The `$this->milestones->sum(...)` in Blade is not analysed by Larastan.
- R1-8 testable query count: fixed in criterion 1 (see Problem 5 for details).
- R1-9 `trans_choice` and `aria-label`: fixed. Verified: `flux/progress.blade.php` renders `<ui-progress {{ $attributes->class(...) }}>`, so `aria-label` passes through. The JS sets `role="progressbar"` and `aria-valuemin` (grep of `flux-lite.min.js`), and no name, so the explicit label is needed and correct.
- R1-10 count argument: fine, the other-status column never contains the moved task.
- R1-11 and R1-13: no change needed. R1-12 (enum `tryFrom` bullet): the spec keeps the explanation inside B10. It is harmless.
- R1-14 edit during move: fixed (criterion 2 test).
- R1-15 manual-check URL: fixed (Context points to CLAUDE.md for worktrees).
- R1-16 no migration, policy or route: fixed (stated at the top).
- R1-17 missing tests: fixed (index isolation noted, empty board, no-op move, `updated_at`).
- R1 questions 1-5: answered in Assumptions 1-4. Q4 (many rows per move versus Phase 5 events) is handled in Out of scope.

## Problems
1. **Requirement 5 vs step order.** Step A (A5) says the milestone page progress is computed "from `$this->columns` (B6)". `columns()` is only created in step B, so step A can't be built or tested on its own (the commit-per-step plan and "composer test passes after each step" break). Fix: in A5 compute from `$this->tasks` (`$this->tasks->where('status', TaskStatus::Done)->count()` and `->count()`), and switch to `columns` in B6 as part of replacing `tasks()`. Or move A5 and its milestone-page criterion into step B.
2. **Criterion 2, "No tasks" three times.** `progress-summary` shows "No tasks yet" when `total` is 0, and on an empty milestone it renders under the heading. "No tasks" is a substring of "No tasks yet", so a count of the substring gives 4, not 3. Fix: make the column text distinct (for example `__('Nothing here')`), or assert the exact count with a wrapper such as `data-test="empty-column"`, or have `progress-summary` render nothing for 0 on the milestone page.
3. **Existing empty-state text and test are dropped without mention.** The current view has `No tasks yet. Add a resource or exercise below.` in `@forelse`/`@empty`, and `tests/Feature/Milestones/ShowTest.php:176` asserts `assertSee('No tasks yet.')` (with the full stop). B replaces the list with columns, so that text goes away and the test fails. Fix: add to Requirement 16 that this test is changed to the new empty-board assertion, and say whether the hint to add a resource below is kept (it was useful for first-time users).
4. **Requirement 16, Step A tests.** The list says "a test for the label" but no file is named. Fix: name one (for example `tests/Unit/` or `tests/Feature/Enums/TaskStatusTest.php`), so the builder doesn't skip it.
5. **Criterion 1, query-count test: sound only with details.** It works if (a) all data for both runs is created before `DB::enableQueryLog()`, (b) `DB::flushQueryLog()` is called between the two runs (the log is cumulative per connection, and a second `Livewire::test()` would add to the first count), and (c) the 1-subject run also has tasks, so the same code path runs. Add these three lines to the criterion. Also note that the counts must be compared for the same user (an extra `actingAs` user lookup is the same either way).
6. **Criterion 2, `travel()` + `updated_at` test: sound with details.** `$this->travel(5)->minutes()` works in Pest closures, and Eloquent skips the save for unchanged attributes (an enum cast compares equal by value), so it will pass. Specify that the tasks are created first, `travel` comes next, `moveTask` after it, and `fresh()` values are compared to the earlier `updated_at` (SQLite stores seconds, so the 5 minutes makes it reliable). Add `$this->travelBack()` or rely on Laravel's auto-reset in `tearDown`; also, a 'renumbered task gets a new `updated_at`' positive assertion (T2, T3 in the first move example) would prove the test can detect a write at all. Without it a bug that never writes would also pass.
7. **Requirement 9, accessibility of the card's menu.** `flux:dropdown` with an icon button needs the button to be a `flux:button` with `variant="ghost"`; say it is the trigger (`<flux:button ... />` as the first child), otherwise a builder may use a bare `<button>`. Minor.
8. **Requirement 10, item key type.** `wire:sort` passes `$item` as the string/number from `wire:sort:item` and `$position` as an int, which `int` type hints accept. Nothing to fix, but the test should call `moveTask` with ints as written; no test covers the sort-handler argument shape (`moveTask(id, position, groupId)`), which relies on the manual check. This is already accepted in Assumption 10.
9. **Larastan on `moveTask`.** `columns()` returns `array<string, Collection<int, Task>>`, but Phase 3's `tasks()` return type is `Illuminate\Database\Eloquent\Collection`. Say which `Collection` class (Eloquent's, as `groupBy` on an Eloquent collection returns a base `Collection`), so level 7 passes. If the builder groups with `->groupBy(fn)` the result type is `Support\Collection<string, Eloquent\Collection>`. Write the docblock to match.
10. **Progress bar from `flux:progress` with `value=0`.** `<flux:progress :value="0" :max="N">` is an edge case worth one assertion (a milestone with only todo tasks shows "0 of 2 tasks done"). Add it to the milestone page criterion.

## Questions for the human
No open product questions remain from round 1. Two small new ones:
1. On an empty milestone, should the page still say "Add a resource or exercise below" (current text), or is "No tasks" in the columns plus the add form enough?
2. A task moved to Done by the menu or drag is silently placed at the end of the Done column. Is that the order you want for finished work (oldest done first), or should newly done tasks go to the top? (Assumption 2 settles the menu case but not a drag-to-Done case, where position is the drop position.)
