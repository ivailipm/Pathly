# Spec review (revision 2)

## Verdict
NEEDS WORK. All 33 problems from round 1 are resolved or only have small residue (listed below). The new parts are mostly sound, but one item is wrong against the vendor source (the sidebar view composer), and a few rules have holes.

## Round 1 check
Resolved: 1-10, 12-15, 17-31 and 33. Not fully resolved:

- **11 (placeholders, step order).** Placeholders fix the Appearance and Today cases. But [B] writes "all tests" first, and several acceptance items assert [V] output. Examples: `duration` and `local-date` "Blade tests by [B]" (A), `/subjects?new=1` renders the modal open (B), and "a brand-new user sees every empty state (a test asserts the texts)" (F). Against a placeholder these tests fail. Today's `<livewire:sessions.log-session>` embed and the milestone page's embed also live in the [V] views, so [B] cannot test them first. See problem 7.
- **16 (`syncCompletion`).** The call list is fixed, but R6 has a gap with zero tasks. See problem 4.
- **32 (acceptance).** `grep "dark:"` is added, but it does not catch `class="dark"`. See problem 2.

## Problems

1. **Requirement 6 / A7, the view composer targets the wrong view name (checked in vendor).** The spec shares `$sidebarSubjects` with `layouts.app.sidebar`. The sidebar is rendered as `<x-layouts::app.sidebar>`, and `layouts` is registered by Livewire as an anonymous component path (`LivewireServiceProvider.php:128-146`, `config/livewire.php` `component_namespaces`). `BladeCompiler::anonymousComponentPath` registers it under an `xxh128` prefix hash, and `ComponentTagCompiler::guessAnonymousComponentUsingPaths` resolves it to `<hash>::app.sidebar`. A composer on `'layouts.app.sidebar'` will probably never fire, so the variable is undefined. Fix: register the composer with a pattern such as `'*::app.sidebar'`, or avoid the composer (a class-based Blade component that queries the subjects, or a small Livewire sidebar component). Add a test that renders a page and asserts the subjects appear in the sidebar, and a query-count test for "one query".

2. **A3, light only is incomplete.** `class="dark"` is on `<html>` in five layouts, not one: `layouts/app/sidebar`, `layouts/app/header`, and `layouts/auth/{card,simple,split}`. The spec names only the sidebar layout. The auth pages share `partials/head`, and Flux's own CSS keys off `.dark`, so login and register stay dark. Fix: list all five. Make acceptance also say `grep -rn 'class="dark"' resources/views` finds nothing. Decide whether `layouts/app/header` is deleted or cleaned.

3. **R2, a window function is wrong when the query is filtered.** `ROW_NUMBER() OVER (PARTITION BY subject_id ORDER BY position, id)` computed in a query with `WHERE completed_at in this month` (recentlyFinished) or reached through notes (recentNotes, notes) numbers only the filtered rows. The number would then be 1 instead of 4. Fix: say that `number` is computed over all of the subject's milestones: a count of earlier siblings subquery, or a PHP pass over the full milestone set. Add a test where the done milestone is number 4 of 6 and appears in `recentlyFinished`.

4. **R6, zero tasks.** The rule sets `completed_at = null` "as soon as one task is not done". Deleting the only (done) task of a completed milestone leaves no task that is "not done", so `completed_at` stays set. R3 says an empty milestone is never done, so R3 and the stored date disagree. Fix: add "also cleared when the milestone has no tasks" and a test.

5. **R3 from step C, factories, seeder and step-B tests.** After step C a milestone is done only if `completed_at` is set, and R6 is not called by factories or seeders. So:
   - The step-B tests for R3/R4 (built with `Task::factory()->done()`) go red at step C.
   - `migrate --seed` data (`DatabaseSeeder` assigns random task statuses) shows no done milestones, so the redesign cannot be checked by eye.
   Fix: add a `Milestone::factory()->completed()` state and say step C updates the R3/R4 tests. The seeder calls `syncCompletion()` after creating tasks. Optionally seed sessions and notes, so Today, the streak and Field notes show something.

6. **R1 / `local-date` on date-only values.** `target_date` is a `date` cast. Converting a UTC-midnight value to the display timezone moves it to the previous day for any negative offset. `app.display_timezone` is env-configurable, and the spec elsewhere supports changing it. Fix: say that `local-date` converts timestamps only. `target_date` is formatted as is, and "N days left" compares dates in the display timezone. Test with `APP_DISPLAY_TIMEZONE=America/New_York`.

7. **Acceptance, [B] tests of [V] output.** See "Round 1 check, 11". Fix: in "Who builds what", say that tests asserting view text or embeds (the `duration`/`local-date` tests, the modal/`?new=1` test, empty-state texts, `assertSeeLivewire`) are committed after [V] and are expected red against the placeholder. Or order [V] first for those items. Without this, "both in the step's PR" is fine but the developer cannot reach green after [B].

8. **F35, summary sentence with an empty current milestone.** R4 deliberately allows an empty current milestone. Then `remainingTasks` is 0, and the table says "is ready to finish." That is wrong for a milestone with no tasks. Fix: add a case for `total == 0`, such as "*milestone* has no tasks yet. Add some to get started." Do the same for the Up next card and the "N steps from done" wording.

9. **F35, the "Up next" line has undefined parts.** "Up next · type · `duration` logged": nothing says what happens with a null type or 0 minutes. Fix: the parts are dropped, as on cards. Also list the missing fields in Data contract F: `nowStudying.subject.id` and `nowStudying.milestone.id`, `title`, `description` (nullable, see 14).

10. **D25, timer and deleted tasks.**
    - Deleting the task being timed nulls `task_id` (FK `nullOnDelete`) and leaves a running session. `runningSession` ("its task is in this milestone") can never find it, and `stopTimer()` 404s. Only `startTimer` elsewhere cleans it up.
    - Meanwhile, nothing stops the user having a "running" session for ever.
    Fix: `deleteTask` stops (or discards) the running session of that task. Say which. Also say that `startTimer` on a Done task is allowed or refused (the button hides it, but the server accepts it).
    The Alpine counter also needs `started_at` as an ISO string or epoch. Say so in Data contract D, since it is a UTC value from a timestamp-without-timezone column.

11. **D24, LogSession rules conflict.**
    - `subject_id` must be non-archived, but with a milestone it is "fixed to the milestone's subject". An archived subject's milestone page is still reachable (from the Archive view), so the modal 404s there. Fix: only enforce non-archived when no milestone is given.
    - `task_id` is "a task of the selected subject", but with a milestone the options are the milestone's tasks. A task from another milestone of the same subject would pass validation. Fix: "a task of the milestone when there is one".
    - On Today, changing the subject leaves a stale `task_id` from the previous subject. Fix: reset `task_id` in `updatedFormSubjectId` (or the equivalent Form hook) and test it.

12. **G36, event class details.** `toOthers()` is not on `Event::dispatch()`. `BoardChanged` must `use InteractsWithSockets` (so `toOthers()` can read the socket id), and be fired with `broadcast(new BoardChanged($id))->toOthers()`. The spec says "dispatched with `->toOthers()`", which will not work with `Dispatchable::dispatch()`. Fix: name the trait and the call. Note that `Event::assertDispatched` still works with `broadcast()`.

13. **E28, notes `minutes` and R1.** "Sessions that started on the note's date (R1), loaded with one grouped query" conflicts with R1's "never SQL date functions". Fix: one query for the sessions of the page's tasks within the date range, grouped in PHP.

14. **Contracts, nullability.** `subject.description` and `milestone.description` are nullable columns, but the contracts do not mark them `?`. The views render them in serif blocks. Fix: mark them `?` and hide the block when null.

15. **Mockup items with no contract and not in Out of scope** (small):
    - Today: the sentence tail "Pick up where you left off.", and the "4 / 7 done" text next to the Now studying bar. Either add them or drop them on purpose.
    - Library: the "Name it, then sketch its path" sub-line on the dashed card.
    - Subject page: the cover band's ruled lines and the first word of the title (`title` is enough, but the "first word" rule is not stated; say what happens for a title that starts with a symbol or is one long word).
    Fix: one line each, or add to Out of scope.

16. **R8, backfill in the migration.** "Step C's migration backfills `colour = forIndex(id - 1)`" suggests calling the enum from a migration. A migration should not depend on an app enum that may change. Fix: use literal strings in the migration (`['clay','slate','moss','plum'][($id - 1) % 4]`). Also say whether "the number of the user's existing subjects" for new subjects includes archived ones.

17. **Context, tests list.** `tests/Feature/DashboardTest.php` (guest redirect and 200 for a signed-in user) is not listed. It is affected by the swap to `Today` (the page must render for a user with no data). Add it to the list, plus `Auth/*` tests that assert a redirect to `dashboard` (they only check the redirect and keep passing).

## Questions for the human

1. Should Paused subjects be eligible for `nowStudying` and the desk, or only Active ones? The spec excludes only Archived (R9).
2. When a timer is left running, should `stopTimer` store the capped 600 minutes silently, or should the user be asked? A forgotten timer from yesterday saves 600 minutes of fake study time.
3. Should a task deleted while its timer runs discard the running session or save it (problem 10)?
4. Are the fixed "Europe/Sofia" default and the noon-of-the-day storage for manual logs acceptable? A session logged for "today" at 23:30 local shows noon, so "studied today" is right but any later "time of day" feature is not.
5. Should "Day N of your streak" show N when the streak ends yesterday (the user has not studied today yet)? The text then claims a day the user has not studied.
