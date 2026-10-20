# Spec review

## Verdict
GOOD, with a few small fixes worth making before building.

Revision 1 problems: all resolved or accepted (see the end). The Context section matches the code. `app/Policies/` does not exist yet. `tests/Feature/Settings/ProfileUpdateTest.php`, `Profile.php` and `routes/settings.php` exist. The `layouts::app` default is in `vendor/livewire/livewire/config/livewire.php`. `composer test` runs Pint, phpstan and the tests. `Livewire\Form::validate()` returns unprefixed data and only prefixes the error keys, so `create($this->form->validate())` is correct. `PersistentMiddleware` does list `Authorize` and `Authenticate` as persistent, so the claim in Context is true.

## Problems

1. Acceptance C/D/E, authorization of write actions: the `authorize('update')` and `authorize('delete')` calls in `update`, `delete`, `addMilestone`, `addTask`, `toggle`, `updateTask` and `deleteTask` have no test. `Livewire::test()` skips the persistent middleware, and a non-owner cannot mount the component because `mount()` authorizes. So a non-owner test of an action can't be written the simple way. Fix: either add one test per component that mounts as the owner, then calls `$this->actingAs($other)` and invokes an action, expecting 403. Or state in the spec that these calls are covered only by the policy test and by review. Without that, a developer can delete the calls and every test stays green.

2. C10/C15/E19, `edit()` and `editTask()` do not authorize, while every other action does. This is low risk, since the data is only the user's own and the route middleware runs in production. Fix: add `authorize('view', ...)` or note that it is deliberate. Also say `cancelEdit()` and `cancelTaskEdit()` call `resetValidation()`. Otherwise old errors stay visible after Cancel, and when the same row is opened again.

3. E21, `deleteTask`: if the deleted task is the one being edited (`editingTaskId`), the id stays set, and a later `updateTask()` throws 404. Fix: in `deleteTask`, clear `editingTaskId` and reset `editTaskForm` when the ids match. Add a one-line test.

4. C10/D15, `$this->form` is filled by `edit()`, but the spec does not say how. Name the call (`$this->form->fill([...])` or setting the two properties). Tests also need `description => null` to round-trip. An empty textarea submits `''`, not `null`, because Livewire updates do not go through `ConvertEmptyStringsToNull`. Decide whether `''` is stored. Either add a nullable cast in the form's `save` step (`?: null`) or accept `''`. The views use "when present", so `''` is harmless but untidy.

5. Assumption 4 / Context: `public Subject $subject` and `public Milestone $milestone` are not `#[Locked]`. The Context says model properties cannot be replaced from the browser, and the persistent middleware re-checks the route. I did not find a vendor check that blocks replacing the model property entirely. `EloquentModelSynth::set` handles only attribute updates under a validation rule. Adding `#[Locked]` costs nothing and removes the doubt. Fix: add `#[Locked]` to both properties, or point to the vendor code that proves it is unneeded.

6. Acceptance D, 404 vs 403 order: the mismatched-milestone 404 case is only stated for the owner. A non-owner with a mismatched URL gets 404 (`scopeBindings` runs before `can`). That is fine, but add the expected status if you want it tested. Not blocking.

7. Acceptance B/C/D, missing cases: (a) adding a milestone or task after a delete gives max + 1, not count + 1, which is the reason for the `reorder()->max()` approach. Add one test with a gap. (b) The 2000-character description limit is tested on the Subject form only. Add it for `MilestoneForm`, or note that the forms share the rules. (c) Checkbox rendering: a `Done` task renders checked and an `InProgress` task unchecked (stated in D14 but not in acceptance 4). (d) A 256-character task title and a 256-character URL.

8. Test coverage gap, `reorder()`: Context says SQLite hides the Postgres `ORDER BY` bug, so the tests cannot catch a missing `reorder()`. Fix: add a test that asserts the SQL, for example `DB::listen` or `toRawSql()` on the query without an `order by`. Or add a manual check on Postgres to acceptance 6. At the moment only convention protects this.

9. Acceptance 6, manual check: it uses `http://pathly.test` (Herd), while CLAUDE.md documents `php artisan serve --port=<port>`. Say "or the `artisan serve` URL". `DatabaseSeeder` creates `test@example.com` through `UserFactory`, which is verified, so that part is fine.

10. Working tree: `bootstrap/app.php` has uncommitted changes that this spec does not mention. Check they are not part of this feature before the step A commit, so they don't end up in it.

## Questions for the human

1. After deleting a subject or milestone, positions keep gaps (Assumption 6). Is that acceptable if Phase 4 reorders by position? Renumbering is out of scope, so it will be needed later.
2. Should an empty description be stored as `null` or `''`? (Problem 4.)
3. Is there any need to prevent deleting a subject that has many tasks without a stronger confirmation than `wire:confirm`? The cascade removes everything, and soft deletes are out of scope.

## Revision 1 status
1 (authorization): resolved via `mount()` authorize, persistent middleware and `update` in actions. Test coverage is the remaining gap (Problem 1 above). 2: resolved apart from Problem 6. 3: resolved (`reorder()`, int cast). 4: resolved (`ModelNotFoundException`). 5: resolved with form objects. 6: resolved. 7: resolved (`url:http,https`). 8: resolved (`latest('id')`). 9: resolved ("by eye"). 10: mostly resolved, see Problem 7. 11: see Problem 9. 12: resolved. 13: no issue.
