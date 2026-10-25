# Spec review

## Verdict
NEEDS WORK. The Context section is accurate and the plan is mostly sound. I checked the files, test line numbers, route names and `config/app.php:68`. But several things shown in the mockups have no contract. Some [B]/[V] hand-offs are unspecified. Two steps cannot end green in the "[B] first, then [V]" order.

## Problems

### Data contracts (mockup data with no contract, or contracts [V] would have to guess)

1. **B12, B13, F35, Data contract B/F, milestone "number".** Several views show a zero-padded number for a milestone that is not iterated in order: Library table and Today table ("04 · Real-time with Reverb"), Today "now studying", and the note rows on the Subject page ("TUE 20 OCT · 04"). The contract only says `current_milestone` is a `Milestone|null`, and `number` exists only as a computed property on `Milestones\Show`. Fix: define a `number` attribute (int) on every milestone [B] hands to views. Name it in each contract: `current_milestone.number`, `first_milestone.number`, `nowStudying.milestone.number`, `recentNotes[].milestone_number`. Say how it is computed without a query per row.

2. **B15, "Now on" / "First up".** `current_milestone` and `first_milestone` are both in the contract, but the rule for "no milestone started" is never defined. Is it no done milestones? No tasks in progress or done? [V] has to guess, and it is untestable. Fix: add one rule, for example "started = the subject has at least one task not in To do". State which label shows when the subject has milestones but none are current (all done) versus no milestones. Test it in [B].

3. **D28 / B15, Library band label.** The band label ("Studied today", "Yesterday", "N days ago", "Not started") only gets data in step D. Steps B and C say nothing about what the band shows. Fix: say "no label until D", or have [B] provide `studied_label` (a string) so [V] does not do date maths in Blade.

4. **D24-D27, sessions with no task.** `task_id` is nullable (LogSession allows "no task", and task delete nulls it). But there is no `milestone_id` or `subject_id` on `study_sessions`. Time logged per milestone and subject, `last_studied_at` and `nowStudying` are all derived through the task. A session without a task is lost from every total except the week chart and streak. Deleting a task silently lowers the subject total. Fix: make `task_id` required in `SessionForm`, or add a nullable `milestone_id` and derive the subject from it. Add a test. This is also a product question (see Questions).

5. **F35, `desk` and the summary sentence.** `desk` is capped at 5, but the sentence says "N tasks are on your desk". With 8 in progress it would say 5. Fix: add `deskCount` (uncapped) to the contract. Also say what the sentence shows when the milestone has 0 remaining tasks and when `upNext` is null (the "Up next" card is not specified for that case).

6. **F35/F36, week chart.** In the mockup, bar height is minutes divided by the week's maximum (Tue 80 = 100%, Mon 45 = 56%). The contract only has `week` (7 ints) and `weekMinutes`. [V] would invent the scale. Fix: add `weekMax`, or `week` as 7 objects with `minutes`, `percent`, `is_today` and `is_future`. Also resolve the precedence between "today's bar in vermilion" and "0-minute days as a 2px line". What does today look like at 0 minutes? What is the `aria-label` when today has no minutes ("Fri 25 so far" in the mockup)?

7. **F36, `latestNote` and `desk` links.** The milestone route needs the subject (`subjects/{subject}/milestones/{milestone}`). Contract F says `latestNote` "with milestone" but not `milestone.subject`. The same applies to the `recentlyFinished` and `nowStudying` links. Fix: list `milestone.subject` explicitly. Also define the `desk` secondary line when a task has neither URL nor type (mockup: URL, else "Subject · Type").

8. **F36 / D28, duration format.** The `duration` examples are "4 h 10 min", "50 min" and "0 min". The Today mockup shows "3 h 00 min" with the minutes muted. Fix: state whether minutes are zero-padded when hours are shown. Test the component's output for 0, 59, 60 and 180.

9. **C20, colour mapping.** A9 uses `id % 4` (clay, slate, moss, plum). C19 migrates "colours in rotation by id" to keep the look. The two only match if the mapping is identical (is id 1 clay or slate?). Fix: write the formula once (e.g. `cases()[($id - 1) % 4]`) and use it in both places. Also define "next colour in rotation for the user" for new subjects, e.g. by count of the user's subjects. Otherwise it is untestable.

10. **A8/A9, Tailwind class names.** Tint classes built dynamically (`bg-{{ $colour }}`) are not compiled by Tailwind v4. Fix: say the `subject-tint` component maps each colour to full literal class strings.

### [B]/[V] split and step order

11. **A4 vs A3/A7, and the same pattern in D, E and F.** [B] lands first. A4 removes `appearance.edit`, but `components/settings/layout.blade.php` (the [V] part, A3) still calls `route('appearance.edit')`. Every settings page then throws until [V] lands. The same happens in F: [B] swaps `Route::view('dashboard')` for `Today::class` and deletes `dashboard.blade.php`, but `livewire/today.blade.php` is [V]. Notes\Index (E) and `sessions/log-session.blade.php` (D) have the same problem. A Livewire test renders the view, so none of these can be green between the two halves. Fix: in each step, either let [B] ship a minimal placeholder view, or have [V] remove the Appearance link first. State this order in "Who builds what". Delete `dashboard.blade.php` only after the replacement exists.

12. **D25, opening LogSession.** `public bool $open` lives in the embedded component, but the "Log a session" buttons are in the parent's header slot (milestone page) and in Today's page header. A button outside the component cannot set `open`. Fix: name the mechanism. Either the trigger is rendered inside `LogSession`, or the parent dispatches a named event that the component listens to, or the modal is a named `flux:modal` opened with `flux:modal.trigger`. Put the name in Data contract D.

13. **D25 / D27, `session-logged` listeners.** Step D only says "pages listening refresh". Only Today (F) is given `#[On('session-logged')]`. `Milestones\Show` (`minutesLogged`, `minutes_logged` on cards) and `Subjects\Show` need listeners too, otherwise totals are stale after saving. Fix: add `#[On('session-logged')]` to both and test it. The G listener `refreshBoard` clears the cached `minutesLogged` but is only fired from other tabs.

14. **D25, LogSession authorization.** Authorization is specified for the task (404) but not for the `milestone` prop. Fix: `mount()` authorizes `view` on the milestone's subject, like CLAUDE.md's rule for components that receive a model. Test it.

15. **C21, existing data.** The new rule "milestone done means `completed_at` is not null" replaces rule 10. The migration adds `completed_at` as null. Every already-complete milestone then becomes "not done", and the current-milestone logic and Library labels change. Fix: C19 backfills `completed_at` (for example `updated_at` or `now()`) for milestones that are complete today. Test the migration or backfill.

16. **C21, `syncCompletion` call list.** It lists `moveTask`, `addTask`, `deleteTask` and "when a task is created in a non-Done column". The last item is already `addTask`. Fix: remove it, and say whether `updateTask` is excluded on purpose (it does not change status). State that factories and seeders do not call it.

17. **A5, timezone change.** `APP_TIMEZONE=Europe/Sofia` changes how Laravel reads existing `timestamp without time zone` columns. Existing `created_at` values written as UTC shift by 2-3 hours. That affects "Started", the streak and any "today" boundary. Fix: either keep storage in UTC and convert only for display and day boundaries (one helper), or state that data is re-interpreted and accepted. Also say how the streak and `week` group by day. Group in PHP in the app timezone, not with SQL date functions. CLAUDE.md warns that SQLite tests pass things Postgres rejects, and `date()` differs between them.

18. **D26, `stopTimer`.** Elapsed time is uncapped, while manual logs are capped at 1-600. A timer left running for two days saves 2880 minutes and ruins the week chart and totals. Also say what happens when the user starts a timer on a task and then moves it to Done. Fix: cap at 600 (or discard over a limit), and test it. Specify which ability `startTimer` and `stopTimer` authorize (`update`) and that `runningSession` is scoped to the user, not the milestone.

19. **E29, `notes.minutes`.** Two notes on the same task on the same day each show the full day's minutes, double-counting. A note with no task shows 0. A note whose task was deleted should hide the "On …" text. Fix: define these three cases. Also load the minutes without an N+1 (a single grouped query).

20. **E31-E32, notes authorization.** `Note` has no `user_id`. `Notes\Index` must scope through milestones and subjects. Name the scope and test that another user's notes never appear. Say whether a `NotePolicy` is needed, or note that `addNote()` authorizes `update` on the subject. CLAUDE.md requires 404, not 403.

21. **G37, `toOthers()`.** I checked the vendor source and this works. Livewire's JS sends `X-Socket-ID` when `window.Echo` exists (`livewire.js`, supportLaravelEcho), and the `#[On('echo-private:milestones.{milestone.id},.board.changed')]` form resolves `{milestone.id}` via `data_get` (`SupportEvents.php:125`). No change needed. But the plan has holes: `LogSession::save()` and `Milestone` edits from the Subject page never broadcast `BoardChanged`, so other tabs miss new session minutes. Either dispatch it there too, or list it as out of scope. Also say that the dev who has no queue worker running sees no live updates (documented in 40; fine).

### Vague or untestable rules

22. **B10/C21, current milestone and "up next".** A milestone with 0 tasks is never done (`tasks_count > 0`), so an empty first milestone blocks the path forever as "current". Is that intended? State it, or skip empty milestones.

23. **F35, `nowStudying` fallback.** "Newest non-archived subject that has a current milestone": newest by `id`, `created_at` or `updated_at`? Decide. Also say what happens when the most recent session belongs to a subject that is now archived or has no current milestone (fall through to the fallback?).

24. **F35, `streak`.** Define the day boundary (app timezone, by `started_at`). Define whether a running (null `minutes`) session counts (it should not). Say what "Day N of your streak" shows when the streak ends yesterday (N counts through yesterday). Add boundary tests: today only, yesterday only, a gap, a manual back-dated session.

25. **F35, `greeting`.** "Before 12:00 morning, before 18:00 afternoon, otherwise evening" makes 2 a.m. "Good morning". That is a product choice, not a bug. Confirm it and test the boundaries with `Carbon::setTestNow` (11:59, 12:00, 17:59, 18:00).

26. **C22, `recentlyFinished`.** "Current month" is by the app timezone. Does it include archived subjects? Is it shown on the Archive view? What does the Archive view show when empty (no copy given)? Where does "Recently finished" sit when the Table view is active?

27. **C20, tags.** "At most 5, each at most 24 characters" – what happens on more than 5: validation error or silent truncation? Say "validation error on `form.tags`" and test it.

### Smaller gaps

28. **B15, copy.** "No subjects yet. Add your first one above." (IndexTest:82) no longer makes sense when the form is a modal. Keep the string only if you want the test unchanged, but say so. Otherwise change the copy and the test.

29. **B14, `addTask`.** "Its board position comes from the existing renumbering rules" is unclear. `addTask` today uses `max(position) + 1`, which already lands last in any column. Say that, and drop the sentence. State what happens when `addingTo` is set and the user presses the same "+" again (toggle?), and whether the add form stays open after saving for adding several tasks.

30. **C23 / task type.** `type` is nullable. Say what the mono label row shows for a todo card with no type and no "Up next" (nothing?).

31. **F34-F35, convention.** `App\Livewire\Today` breaks the `app/Livewire/<Area>/<Name>` layout in CLAUDE.md. That is fine for a top-level page, but note it, and give `render()` explicitly. `LogSession` and `Notes\Index` must follow CLAUDE.md for explicit `render()` and `@property` docblocks on the new models (`StudySession`, `Note`), with `HasMany<…, $this>` docblocks and factories.

32. **A, acceptance.** "Match their mockup by eye" is not testable. Add something checkable per step. For example: no `dark:` in `resources/views` (a grep), `/settings/appearance` returns 404 (the test is present), and the specified `aria-label` strings appear.

33. **A7, "New subject" in the sidebar.** In step A the link goes to `?new=1`, which does nothing until step B. Say that explicitly, or add the link in B.

## Questions for the human

1. Sessions with no task: should they be allowed? If yes, should they count toward a milestone or subject total? (Needs a `milestone_id` on `study_sessions`.)
2. Should the timezone be one app-wide setting (Europe/Sofia) with the existing UTC data re-read, or kept in UTC in storage with display conversion?
3. Is an empty milestone (0 tasks) allowed to be "current", or should it be skipped on the path?
4. Should a stopped timer be capped (for example 600 minutes), or should a very long one ask for confirmation?
5. When "Started" shows `created_at`, is that right for imported or backdated subjects? Should it be an editable start date?
6. Greeting: is "Good morning" from midnight to noon what you want?
7. Archived subjects: are their milestones included in "Recently finished" and their sessions in the week chart and streak? (The spec only excludes them from the sidebar, desk and `nowStudying`.)
8. Is a milestone that was complete, then reopened, then completed again meant to lose its first `completed_at` date?
