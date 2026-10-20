# Spec review

## Verdict
NEEDS WORK

The cited files and patterns exist (models, `Route::livewire()`, the `auth`+`verified` group, phpstan level 7, one sidebar item). The main gap is that authorization only runs on the first page load.

## Problems

1. Requirements 7 and 11 (authorization): `->can('view', 'subject')` is route middleware. It runs on the initial GET, not on later Livewire update requests. The public `Subject`/`Milestone` properties can also be tampered with from the client unless locked. Another user could therefore add milestones or toggle tasks on a subject they don't own. Fix: add `#[Locked]` to `$subject` and `$milestone`. Call `$this->authorize('view', $this->subject)` at the start of each action (`addMilestone`, `addTask`, `toggle`). Better still, use `update` for the write actions, since the policy defines it. Add tests that call the actions as a non-owner and expect a 403.

2. Requirement 11 (scoped bindings): `scopeBindings()` needs the child relation name to resolve to `Subject::milestones()`. That works, but `milestones()` is ordered, so check that the binding still resolves cleanly. Note that `can('view','subject')` sees the bound parameter, so the middleware order matters (`SubstituteBindings` must run before `can`). This is fine in the web group, but the 404-vs-403 order is untested. Add an acceptance case: a non-owner opening a mismatched milestone URL (state which status you expect).

3. Requirement 9 and 13 (position = max + 1): this is vague about the empty case and about how to compute it. Specify `($this->subject->milestones()->max('position') ?? 0) + 1`. Without `reorder()`, the relation's `orderBy` is combined with the aggregate, which Postgres can reject. Use `->reorder()->max('position')` or query via the model. Larastan may also complain about `max()` returning mixed, so cast it to int.

4. Requirement 14 (toggle): `findOrFail` throwing in a Livewire action gives a 404 to the client, which is acceptable. Acceptance criterion 4 says the test expects `ModelNotFoundException`. Livewire's `->call()` rethrows it, but the spec should say so, or assert on the exception explicitly.

5. Requirements 4, 9 and 13 (forms): after a successful save you need `$this->reset(...)` and `resetValidation()`. Say which properties are reset. Neither the validation attribute style (`#[Validate]` or `rules()`) nor the property names are defined, but the tests rely on them for error keys. Name the properties (`title`, `description`, `url`). Two components each have a `title` property on a page that also displays `$subject->title`, so avoid clashes by using names like `newTitle`.

6. Requirement 12 (checkbox): the spec doesn't say how `flux:checkbox` is wired. Specify `wire:click="toggle({{ $task->id }})"` with `:checked`, and `wire:key` on each row. Without these, the checked state can go out of sync after a re-render. It doesn't cover `InProgress` tasks either. They appear unchecked, and the spec should say so. The `url` link also needs `rel="noopener noreferrer"`.

7. Requirement 13 (url validation): "valid URL" allows `javascript:` URIs under some rules. Rendering it as a link is an XSS risk. Restrict it with `url:http,https`, and add a test for a `javascript:` value.

8. Requirement 3 (newest first): `latest()` has a tie-break problem when `created_at` is equal, as it is in tests with factories. Use `latest('id')` or an explicit `orderByDesc('id')`, otherwise the order test is flaky.

9. Requirement 6 and acceptance 6: highlighting the sidebar is only manually checked. Say in the spec that it is verified by eye, or add a feature test that asserts the sidebar link renders.

10. Requirement 16 and the acceptance criteria: there is no test for the empty states (requirements 5, 10 and 15), the description max-length (2000), the milestone/task isolation between users on the index, or the checkbox shown as checked for `Done`. Add them.

11. Acceptance 5: it uses `http://pathly.test` and the seeded `test@example.com`. CLAUDE.md says the dev server runs on `php artisan serve --port=<port>` at `127.0.0.1`. Confirm that the Herd-style host exists. The seeded user must also be verified, or it will hit the `verified` redirect. Check `DatabaseSeeder`.

12. Policy registration: the spec doesn't say whether the policy is auto-discovered (it is, by the `App\Policies\SubjectPolicy` naming convention). Say so, so nobody adds a manual registration. `PolicyTest` should use `Gate`/`$user->can(...)`, not call the methods directly.

13. Context nit: "`Subject` fillable `title`, `description`" is correct, and `user_id` is not fillable, so creating through the relation is required. This is already covered by requirement 4. There is no problem here, but milestone creation via `$this->subject->milestones()->create([...])` sets `subject_id` itself, so `position` must be in `$fillable`. It is.

## Questions for the human

1. Should a non-owner get 403 or 404 for someone else's subject? 404 avoids revealing that the ID exists. The spec chooses 403.
2. Is it right that an `InProgress` task becomes `Done` when ticked, and then goes back to `Todo` when unticked? That loses the in-progress state.
3. Is no edit or delete acceptable for the MVP loop? A typo in a title can't be fixed until Phase 4.
4. Should milestones and tasks get their own policies later? The decision to rely on parent scoping only is fine for now, but it needs revisiting when edit and delete arrive.
5. Is the `verified` requirement wanted for the seeded and dev users?
