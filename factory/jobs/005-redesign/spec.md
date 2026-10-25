# Pathly redesign: paper theme, Today, sessions, notes, streak and real-time (Phases 5 + redesign)

> Feature: Rebuild the app to match the mockups in `pathly-design/design/` (`today.html`, `library.html`,
> `subject.html`, `milestone.html`, plus `HANDOFF.md`): design tokens and fonts, the new app shell, the four
> screens, and every new feature in the handoff (study sessions, subject properties, task type, field notes,
> milestone completion, streak, live indicator with Reverb broadcasting). Light theme only.

Revision 3 (round 1 review: `spec-review-1.md`; round 2 review: `spec-review.md`). Built on
branch `feature/redesign` off `main`, in steps A–G, one PR per step. Every step ends with a working app and
a green `composer test`.

## Who builds what

Each requirement is tagged:

- **[B] Backend, the developer:** migrations, models, enums, casts, factories, seeders, form objects,
  Livewire component classes (PHP), routes, policies, events, channels, config, PHP support classes, and
  **all tests**.
- **[V] Views, Claude:** Blade views and anonymous Blade components, `resources/css/app.css`, the fonts
  list in `vite.config.js`, and small Alpine snippets inside views.

Order inside a step: **[B] first, then [V], both in the step's PR.**

- **New components:** when [B] adds a Livewire component, it also adds a placeholder view (a single root
  `<div>` with a `{{-- [V] --}}` comment) so its tests run. [V] replaces it.
- **Removals:** when [B] removes a route or a view, [B] also removes every reference to it, so the app
  never points at something missing.
- **Test order:**
  - [B]'s first commit only has tests that check behaviour through component state or the database
    (`assertSet`, `instance()`, model assertions). They pass against the placeholders.
  - Tests that assert rendered output are committed by [B] after [V] lands, in the same PR. That covers
    texts, `aria-label`s, Blade components such as `duration`/`local-date`, `assertSeeLivewire` and modal
    state in HTML.
- **The data contract:** each step's **Data contract** lists every property, attribute and action the
  views use. [B] provides exactly those names; [V] uses nothing else. A value marked `?` can be `null`,
  and the view must handle that.

## Context

- **Mockups:** static HTML with inline styles (`pathly-design/design/*.html`). They are the visual source of
  truth, rebuilt in Tailwind v4 + Flux; inline styles are not copied. `HANDOFF.md` lists tokens, fonts and
  features.
  - The handoff says "the board already broadcasts through Reverb", but that is **not true**: there is no
    `app/Events`, and `routes/channels.php` has only `App.Models.User.{id}`.
  - The browser side exists: `resources/js/echo.js` configures Echo for Reverb.
- **Today's code** (Phases 3–4, merged):
  - `app/Livewire/Subjects/Index.php`: `save()`; `#[Computed] subjects()` = the user's subjects
    `latest('id')` with `withCount(['tasks', 'tasks as done_tasks_count' => ...])`.
  - `app/Livewire/Subjects/Show.php`: `edit()`, `update()`, `cancelEdit()`, `delete()`, `addMilestone()`,
    `$editing`, `SubjectForm $form`, `MilestoneForm $milestoneForm`; `#[Computed] milestones()` with the
    same `withCount` on `Milestone::tasks()`.
  - `app/Livewire/Milestones/Show.php`: `edit()`, `update()`, `cancelEdit()`, `delete()`, `addTask()`
    (`max(position) + 1`, so a new task is always last in its column), `editTask()`, `updateTask()`,
    `cancelTaskEdit()`, `deleteTask()`, `moveTask(int, int, string)`, `#[Computed] columns()` (keyed by
    `TaskStatus` value), private `findTask()`.
  - Views: `resources/views/livewire/{subjects/index,subjects/show,milestones/show}.blade.php`,
    `resources/views/components/progress-summary.blade.php`, `resources/views/dashboard.blade.php`
    (starter-kit placeholder, served by `Route::view('dashboard', 'dashboard')`).
  - `App\Enums\TaskStatus` labels are `To do`, `In progress`, `Done`: the board's column names in the mockup.
  - `SubjectPolicy` answers 404 to other users; routes use `->can('view', 'subject')` and `scopeBindings()`.
- **Shell:**
  - `resources/views/layouts/app/sidebar.blade.php`: the Flux sidebar and the starter-kit nav, rendered as
    `<x-layouts::app.sidebar>`. Livewire registers `layouts` as an anonymous component path with a hashed
    prefix (`LivewireServiceProvider.php:143`, `BladeCompiler::anonymousComponentPath`), so a view composer
    on `layouts.app.sidebar` would never fire.
  - five layouts force `class="dark"` on `<html>`: `layouts/app/sidebar`, `layouts/app/header` and
    `layouts/auth/{card,simple,split}`
  - `resources/views/partials/head.blade.php`: `@fonts`, `@fluxAppearance`
  - `resources/views/components/settings/layout.blade.php`: Profile / Security / Appearance
  - the Appearance page `App\Livewire\Settings\Appearance` at `settings/appearance` (`appearance.edit`,
    `routes/settings.php:15`); `tests/Feature/Auth/EmailVerificationTest.php:37` visits `appearance.edit`
- **Theme and fonts:**
  - `resources/css/app.css` has a Tailwind v4 `@theme`: Instrument Sans, a zinc scale, the Flux accent
    variables, and a `.dark` layer.
  - `vite.config.js` self-hosts fonts with `laravel-vite-plugin/fonts` `bunny('Instrument Sans', ...)`,
    output by `@fonts`.
  - Tailwind only compiles class names that appear literally in source, so tint classes must be written
    out in full.
  - Free Flux has `sidebar`, `breadcrumbs`, `button`, `dropdown`, `menu`, `modal` (+ `modal.trigger`),
    `input`, `textarea`, `select`, `radio`, `badge`, `table` and `toast`; no tabs (Flux Pro).
  - Named modals open from anywhere with `<flux:modal.trigger name="...">` and close from PHP with
    `Flux::modal('name')->close()` (`vendor/livewire/flux/src/Concerns/InteractsWithComponents.php:38-52`).
- **Time:** `config/app.php:68` is `'timezone' => 'UTC'`, and every timestamp column is
  `timestamp without time zone`. Changing the app timezone would make existing UTC values read as local
  time, so this spec keeps storage in UTC and adds a display timezone (rule R1).
- **Queues and broadcasting:**
  - `.env.example`: `QUEUE_CONNECTION=redis`, `BROADCAST_CONNECTION=reverb`; `phpunit.xml`: `sync` and `null`.
  - `composer dev` runs `queue:listen`; Reverb needs `php artisan reverb:start`.
  - Livewire sends `X-Socket-ID` when `window.Echo` exists, so `toOthers()` works.
  - `#[On('echo-private:milestones.{milestone.id},...')]` resolves `{milestone.id}` from the component.
- **Tests that assert page text** (they keep passing, or [B] updates them in the step that moves the text):
  - `tests/Feature/Subjects/IndexTest.php` lines 82 and 105–107: "No subjects yet. Add your first one
    above.", "2 of 3 tasks done", "1 of 1 task done", "No tasks yet".
  - `tests/Feature/Subjects/ShowTest.php` lines 151 and 164: "No milestones yet.", and the per-milestone
    "x of y tasks done" order.
  - `tests/Feature/Milestones/ShowTest.php` lines 249–264, 380–382 and 400–414:
    - "No tasks yet. Add a resource or exercise below." with no `data-flux-progress`
    - "Nothing here yet" ×3, and the "x of y tasks done" counts
    - "Move to In progress" / "Move to Done"
    - `wire:sort="moveTask"` ×3 and `wire:sort:item`
  - `tests/Feature/DashboardTest.php`: the guest redirect, and 200 for a signed-in user. It must pass once
    Today replaces the view, including for a user with no data.
  - `tests/Feature/Auth/*` assert redirects to `dashboard`; they keep passing.

## Shared rules (one definition each; [B] implements and tests them, [V] only displays them)

- **R1 Time.**
  - Storage stays UTC (`config('app.timezone')` unchanged).
  - New config `app.display_timezone` = `env('APP_DISPLAY_TIMEZONE', 'Europe/Sofia')`, also added to
    `.env.example`.
  - "Today", day grouping, the week, the streak, the greeting, "days left" and "studied N days ago" are
    computed in PHP in the display timezone, never with SQL date functions (they differ between SQLite
    and Postgres).
  - [V] formats every timestamp through the `local-date` component (requirement 8). Date-only values
    (`target_date`) are never converted: they are formatted as stored, and "days left" compares them with
    today's date in the display timezone.
- **R2 Milestone number:** a milestone's 1-based place among **all** of its subject's milestones
  (`position`, then `id`).
  - Every milestone handed to a view exposes `number` (int), without a query per row.
  - It is computed over the full sibling set (a "count of earlier siblings" subquery, or a PHP pass over
    all of the subject's milestones), never over a filtered result. A window function inside
    `recentlyFinished` would renumber the filtered rows.
  - Tested with milestone 4 of 6 appearing in `recentlyFinished`.
  - [V] zero-pads it: "04".
- **R3 Milestone done:**
  - step B: `tasks_count > 0` and every task is Done
  - from step C on: `completed_at` is not null (R6)
  - an empty milestone is never done
- **R4 Current milestone:** the first milestone in R2 order that is not done, including an empty one. An
  empty current milestone is intended: the path then invites adding tasks to it. `null` when the subject
  has no milestones or all are done.
- **R5 Up next:** the first In progress task in board order, else the first To do task, else `null`.
- **R6 Completion (step C):** `Milestone::syncCompletion()`.
  - It runs at the end of `moveTask`, `addTask` and `deleteTask`, not in `updateTask` (no status change),
    and not in factories or seeders.
  - It sets `completed_at = now()` when the milestone has tasks, all are done, and `completed_at` is null.
  - It sets `completed_at = null` as soon as one task is not done, or the milestone has no tasks left.
    Reopening loses the old date: the date is always the latest completion.
- **R7 Started subject:** at least one of its tasks is not To do, or (from step D) it has a completed session.
- **R8 Colour rotation:** `SubjectColour::forIndex(int $i)` = `SubjectColour::cases()[$i % 4]`, in the
  order clay, slate, moss, plum.
  - Before step C, [V] shows tint `forIndex($subject->id - 1)` (the same expression, inlined in one
    component).
  - Step C's migration backfills the same mapping with literal strings (`['clay', 'slate', 'moss',
    'plum'][($id - 1) % 4]`, not the enum, so the migration never depends on app code), so nothing
    changes visually.
  - A new subject's form default is `forIndex(number of the user's existing subjects, archived included)`.
- **R9 Archived and paused subjects** (from step C):
  - Archived subjects are left out of the sidebar, the Library gallery and table, "Recently finished",
    the desk, `nowStudying` and the Today table.
  - Paused subjects stay in the sidebar, the Library and the Today table, but are left out of the desk
    and `nowStudying`.
  - Sessions of both still count in the week chart and the streak (time studied is time studied).

## Design rules (apply to every [V] step)

- **Colours** (from `HANDOFF.md`):
  - paper `#FBFAF7`, sand (sidebar, columns) `#F4F2EC`, card `#FFFFFF`
  - ink `#191917`, body `#3D3C38`, secondary `#4A4945`, muted `#6B6A64` (the lowest grey for text)
  - rule `#E6E3DC`, control border `#E0DDD5`, active nav `#E8E5DD`
  - vermilion `#B8432A`, only for in progress / now / up next; live green `#3E7B4F`
  - four subject tints (bg / text / large-letter shade): clay `#EBDCD2`/`#7A3B22`/`#DCC6B7`, slate
    `#D9E2E8`/`#2D4B5F`/`#C3D1DA`, moss `#E2E5D3`/`#4B5320`/`#D0D5BC`, plum `#E7DDE7`/`#5B3A5B`/`#D8C8D8`
- **Fonts:** Newsreader for headlines, notes and quotes (italic for emphasis); Geist for the UI; Geist Mono
  for dates, labels and counts (uppercase labels with `0.08em` tracking).
- **Shapes:** radii of 12px for cards, 9px for task cards and 8px for buttons. The primary button has an
  ink background and paper text. A 2px ink rule goes under page and section heads, and 1px rules between rows.
- **Accessibility** as in the mockups:
  - icon-only buttons have an `aria-label`
  - segmented bars are `role="img"` with an `aria-label`
  - interactive targets are at least 40px high
  - decorative letters are `aria-hidden`
- **Layout:** pages reflow to one column at 390px wide (the mockups use wrapping flex and `auto-fit` grids).
- **Copy:** visible strings go through `__()`, and plural strings use `trans_choice`.

## Requirements

### A. Foundation: tokens, fonts, light theme, app shell

1. [V] `resources/css/app.css`:
   - the colours above as `@theme` tokens: `--color-paper`, `--color-sand`, `--color-ink`,
     `--color-body`, `--color-secondary`, `--color-muted`, `--color-rule`, `--color-control`,
     `--color-active`, `--color-vermilion`, `--color-live`, and for each tint `--color-<tint>`,
     `--color-<tint>-ink` and `--color-<tint>-shade`
   - fonts: `--font-sans` Geist, `--font-serif` Newsreader, `--font-mono` Geist Mono; and the radii
   - the Flux accent becomes ink (`--color-accent: #191917`, `--color-accent-foreground: #FBFAF7`)
   - the `.dark` layer and the `dark` custom variant are removed
2. [V] `vite.config.js`:
   - Newsreader 400 and 500 with italic 400, Geist 400/500/600, Geist Mono 400/500, via `bunny()`, using
     the plugin's option for italics
   - Instrument Sans removed
   - if Bunny lacks a family, use the mockups' Google Fonts `<link>` in `partials/head` instead, and say so
     in the PR
3. [V] Light only:
   - remove `class="dark"` from all five layouts (`layouts/app/sidebar`, `layouts/app/header` and
     `layouts/auth/{card,simple,split}`); the unused `layouts/app/header` is cleaned, not deleted
   - remove `@fluxAppearance` from `partials/head`
   - remove every `dark:` class in `resources/views`
   - the auth pages (login, register…) get the paper background and fonts through `partials/head` and
     their layouts
4. [B] Remove the Appearance page:
   - the `appearance.edit` route, `App\Livewire\Settings\Appearance` and its view
   - the Appearance item in `components/settings/layout.blade.php`
   - point `EmailVerificationTest.php:37` at another verified route
5. [B] R1: the `app.display_timezone` config and `.env.example` entry. `config/app.php`'s `timezone` stays UTC.
6. [B] A class-based Blade component `App\View\Components\SidebarSubjects`:
   - public `$subjects`: the signed-in user's subjects in Library order (`latest('id')`), with `id`,
     `title`, and from step C `colour`, and without archived ones from step C on. One query.
   - its view `resources/views/components/sidebar-subjects.blade.php` starts as a placeholder; [V] writes it
   - the sidebar renders `<x-sidebar-subjects />`. A view composer is not used (see Context: the layout's
     view name is hashed).
   - tests: a page shows the user's subject titles in the sidebar, and not another user's; a query-count test
7. [V] App shell (`layouts/app/sidebar.blade.php`), as in the mockups' `<aside>`:
   - brand: an ink italic "P" tile, "Pathly", and "`<first name>`'s library", where the first name is the
     user's `name` up to the first space
   - nav: Today (`dashboard`), Library (`subjects.index`), and Field notes (`notes.index`) added in step E
   - a "Subjects" eyebrow, then `<x-sidebar-subjects />`, whose view [V] writes: monogram + title links
     to `subjects.show`
   - the current page marked with `aria-current="page"` and the active background
   - the existing `x-desktop-user-menu` stays at the bottom for Settings and Log out (the mockups don't
     show it, but users need it)
   - mobile: Flux's collapsible sidebar and header stay
   - left out: Search and Weekly review (Out of scope); "New subject" comes in step B
8. [V] Anonymous components in `resources/views/components/`:
   - `page-header`: the top bar with a bottom rule; a `breadcrumbs` slot (`flux:breadcrumbs`) and an
     `actions` slot
   - `eyebrow`: the mono uppercase label
   - `monogram` (`subject`, `size` = `sm|md|lg`): the tinted letter tile; the letter is the title's first
     character, uppercased
   - `subject-tint` (`subject`): maps the tint to full literal class strings (`bg-clay`, `text-clay-ink`,
     and so on), the single place that knows R8 and, from step C, `colour`
   - `progress-segments` (`done`, `inProgress`, `total`):
     - done segments in ink, in-progress in vermilion, the rest in control grey
     - one segment per task up to 20 tasks, then three proportional parts
     - `role="img"` with `aria-label` = the `trans_choice` ":done of :total task done|:done of :total tasks
       done", plus ", :n in progress" when `inProgress` > 0
     - renders nothing when `total` is 0
   - `progress-line` (`value`, `max`): the thin bar with the mono "value/max"
   - `duration` (`minutes`): under 60, "N min"; from 60, "H h MM min" with the minutes zero-padded
     ("3 h 00 min", "4 h 10 min"); 0, "0 min". The `min` part after hours is muted, as in the Today
     mockup's "This week".
   - `local-date` (`value`, `format`): a **timestamp** `value` converted to `app.display_timezone`, then
     `isoFormat($format)`. Date-only values (`target_date`) don't use it (R1).
   `progress-summary` is replaced by these and deleted. Wherever a test asserts "x of y tasks done", the
   same text is in a bar's `aria-label`. "No tasks yet" stays visible text.

**Data contract A:** `SidebarSubjects` `$subjects` (Collection of Subject: `id`, `title`, and `colour`
from step C); `auth()->user()->name`.

**Acceptance A** (besides `composer test`):
- `grep -rn 'dark:' resources/views` and `grep -rn 'class="dark"' resources/views` find nothing
- `/settings/appearance` returns 404 (a test)
- the sidebar shows the user's subjects
- the `duration` and `local-date` components render the examples above (Blade tests by [B])

### B. The four screens' structure, restyled with today's data

9. [B] R2–R5 and R7 as model methods or query scopes with tests. `Task::displayUrl()` accessor
   (`display_url`): the URL without scheme, `www.` and trailing slash ("laravel.com/docs/reverb"); `null`
   without a URL.
10. [B] `Subjects\Index`:
    - `subjects()` adds `in_progress_tasks_count`, `milestones_count` and `done_milestones_count`
    - each subject exposes `current_milestone` (`?Milestone`, with `number`) and `is_started` (bool, R7),
      without a query per subject
    - `#[Computed] milestoneTotal(): int`: the sum of `milestones_count`
    - `#[Url(as: 'new')] public bool $creating = false`
    - `save()` sets `creating = false` after creating
11. [B] `Subjects\Show`:
    - `milestones()` adds `in_progress_tasks_count`; each milestone exposes `number` and `is_done` (R3)
    - `#[Computed] currentMilestoneId(): ?int` and `#[Computed] doneMilestonesCount(): int`
    - `$editing`, `edit()`, `update()` and `cancelEdit()` stay; `update()` sets `editing = false`
    - `public bool $addingMilestone = false`; `addMilestone()` sets it back to false
12. [B] `Milestones\Show`:
    - `#[Computed] number(): int` and `#[Computed] milestoneCount(): int`
    - `#[Computed] upNextTaskId(): ?int` (R5)
    - `public ?string $addingTo = null`: the status value of the column whose add form is open
    - `openAddTask(string $status)`: an unknown status gives 422. The same status as `addingTo` closes the
      form (toggle); otherwise it opens it.
    - `closeAddTask()`
    - `addTask()` creates the task with status `addingTo ?? todo` (still `max(position) + 1`, so it is last
      in its column), resets `taskForm`, and keeps `addingTo`, so several tasks can be added in a row
13. [V] Library (`subjects/index`), `library.html` gallery:
    - header and title:
      - `x-page-header` with the breadcrumb "Library" and a primary "New subject" button
        (`wire:click="$set('creating', true)"`)
      - the eyebrow "N subjects · M milestones" (`trans_choice`), and the 72px serif "Library" over a 2px
        ink rule
    - gallery cards (`auto-fit` grid):
      - the tint band with the big italic letter (`aria-hidden`); no band label until step D
      - the serif title, and the line below it:

        | Case | Line |
        |---|---|
        | no milestones | "No milestones yet" |
        | `current_milestone` null | "All milestones done" |
        | `is_started` | "Now on *title*" |
        | otherwise | "First up *title*" |

      - `progress-line` with `done_milestones_count/milestones_count`
    - the dashed "Start a new subject" card, with the sub-line "Name it, then sketch its path", opens the modal
    - the New subject form (`form.title`, `form.description`) in a `flux:modal` bound with
      `wire:model="creating"`
    - the empty state becomes "No subjects yet. Start your first one." ([B] updates `IndexTest.php:82`)
    - the progress text "x of y tasks done" from `IndexTest.php:105-107` stays as each card's
      `progress-segments` `aria-label`, in a small task bar under the milestone line
14. [V] Shell: a "New subject" button at the bottom of the sidebar, linking to
    `route('subjects.index', ['new' => 1])`, which opens the modal.
15. [V] Subject page (`subjects/show`), `subject.html`:
    - header:
      - `x-page-header` with breadcrumbs (Library / subject)
      - a "More options" `···` dropdown with Edit (`wire:click="edit"`) and Delete (the existing `wire:confirm`)
      - the edit form in a `flux:modal` bound to `editing`; closing it calls `cancelEdit`
    - the tint cover band with ruled lines (a CSS `repeating-linear-gradient` in the tint's shade) and the
      title's first word in large italic (`aria-hidden`)
      - the first word is the title up to the first space, with leading non-letters stripped
      - the monogram letter when that leaves nothing
      - a long word is clipped by the band's `overflow-hidden`
    - the monogram tile overlapping the band, and the serif `h1`
    - a property list (`dl`): Started (`local-date` of `created_at`, "D MMMM YYYY"); more in steps C and D
    - the description in serif (hidden when null or empty)
    - "The path":
      - a 34px serif heading with "x of y milestones" (`doneMilestonesCount` / count) on a 2px ink rule
      - rows numbered by `number` ("01")
      - done rows muted, with a check and "Done" ("Done · 8 Oct" from step C)
      - the `currentMilestoneId` row as the raised white card, with its number in vermilion, the serif
        title, description, `progress-segments` and "In progress" when any of its tasks is not To do,
        otherwise "Up next"
      - later rows with the mono "done / total"
      - every row links to its milestone page
      - the per-milestone "x of y tasks done" stays in each row's bar `aria-label` (`ShowTest.php:164`)
    - "Add milestone NN" (the next number) sets `addingMilestone` and shows the inline add form
    - the empty state "No milestones yet." stays
    - the side column ("In the margins") comes in step E; until then the path is full width
16. [V] Milestone page (`milestones/show`), `milestone.html`:
    - header:
      - `x-page-header` with breadcrumbs (Library / subject / milestone) and "Edit", which opens a
        `flux:modal` bound to `editing` with the milestone form, plus "Delete milestone" (the existing
        `wire:confirm`) in its footer
      - "Log a session" comes in step D and the Live dot in step G
    - the vermilion eyebrow "Milestone 04 of 06", the serif `h1` and the description
    - a stats row:
      - Progress (`progress-segments`, plus the visible "4 of 7")
      - Started (`local-date` of `created_at`)
      - Time logged from step D
    - the hint "Drag cards between columns" (no tabs; see Assumptions)
    - board columns (sand background):
      - a status dot (To do = muted ring, In progress = vermilion, Done = ink), the mono label and the count
      - a "+" button (`aria-label="Add task to <column>"`, `wire:click="openAddTask('<status>')"`)
      - the To do column also has a text button "Paste a link or add a task" (`openAddTask('todo')`)
      - the add form (`taskForm.title`, `taskForm.url`, Add, Cancel = `closeAddTask`) shows at the bottom of
        the `addingTo` column
      - "Nothing here yet" in empty columns
      - `wire:sort` / `wire:sort:group` / `wire:sort:item` unchanged, in Phase 4's attribute order
    - task cards (white, 9px radius):
      - a mono label row showing "Up next" in vermilion on the `upNextTaskId` card (type from step C,
        minutes from step D); the row is hidden when it would be empty
      - the title, and the link icon with `display_url`
      - Done cards: flat paper background, a check icon, the title struck through, and `display_url`
      - every card keeps the drag handle (`wire:sort:handle`, muted, visible on hover and focus) and the
        `···` "Task actions" menu (Move to…, Edit, Delete) for keyboard and touch users; the mockup
        doesn't draw them
      - the inline task edit form stays inside the card
    - with no tasks: "No tasks yet. Add a resource or exercise below." instead of the progress
    - the field notes section comes in step E

**Data contract B:**
- **Index:** `subjects` (each: `id`, `title`, `tasks_count`, `done_tasks_count`, `in_progress_tasks_count`,
  `milestones_count`, `done_milestones_count`, `?current_milestone` {`title`, `number`}, `is_started`);
  `milestoneTotal`; `creating`; `form.title`, `form.description`; `save()`.
- **Subjects\Show:**
  - `subject` (`title`, `?description`, `created_at`)
  - `milestones` (each: `id`, `title`, `?description`, `number`, `is_done`, `tasks_count`,
    `done_tasks_count`, `in_progress_tasks_count`)
  - `?currentMilestoneId`, `doneMilestonesCount`
  - `editing`, `edit()`, `update()`, `cancelEdit()`, `delete()`, `form.*`
  - `addingMilestone`, `milestoneForm.*`, `addMilestone()`
- **Milestones\Show:**
  - `subject`, `milestone` (`title`, `?description`, `created_at`), `number`, `milestoneCount`
  - `columns` (status value → tasks: `id`, `title`, `url`, `display_url`, `status`), `?upNextTaskId`
  - `editing`, `edit()`, `update()`, `cancelEdit()`, `delete()`, `form.*`
  - `?addingTo`, `openAddTask()`, `closeAddTask()`, `taskForm.*`, `addTask()`
  - `?editingTaskId`, `editTask()`, `editTaskForm.*`, `updateTask()`, `cancelTaskEdit()`, `deleteTask()`,
    `moveTask()`

**Acceptance B:**
- the rules R2–R5 and R7 have tests, including the cases in 13's table
- `openAddTask('in_progress')` + `addTask()` creates the task last in In progress, and the form stays open
- `openAddTask('archived')` gives 422
- `/subjects?new=1` renders the modal open
- drag and drop, Move to, edit and delete still pass their Phase 4 tests
- the text tests in Context pass, or were updated in this step as listed

### C. Subject properties, task type, milestone completion

17. [B] Enums with `label()`:
    - `App\Enums\SubjectStatus` (`Active`, `Paused`, `Archived`)
    - `App\Enums\SubjectColour` (`Clay`, `Slate`, `Moss`, `Plum`, with `forIndex()`, R8)
    - `App\Enums\TaskType` (`Docs`, `Video`, `Exercise`, `Notebook`)
18. [B] Migrations:
    - `subjects`: `status` string default `active`, `target_date` date nullable, `tags` json default `[]`,
      `colour` string default `clay`, backfilled with `forIndex(id - 1)`; cast to the enums, `date` and `array`
    - `tasks`: `type` string nullable, cast to `TaskType`
    - `milestones`: `completed_at` timestamp nullable, backfilled for milestones that are done by R3's
      step-B rule, with the newest `updated_at` among their tasks
    The migration is tested.
19. [B] Forms:
    - `SubjectForm`:
      - `status` (enum rule)
      - `target_date` (nullable date)
      - `tags`: a comma-separated string, saved as a trimmed, lowercase, de-duplicated array. More than 5
        tags or a tag over 24 characters is a validation error on `form.tags`.
      - `colour` (enum rule; the default for new subjects follows R8)
    - `TaskForm`: `type` (nullable enum)
20. [B] R6 from here on, replacing R3's step-B rule:
    - add a `Milestone::factory()->completed()` state (sets `completed_at`) and update the step-B tests for
      R3/R4, which built done milestones from done tasks only
    - `DatabaseSeeder` calls `syncCompletion()` for every seeded milestone, so `migrate --seed` shows done
      milestones
    - steps D and E extend the seeder with sessions over the last 10 days and a few notes, so Today, the
      streak and Field notes have something to show
21. [B] `Subjects\Index`:
    - `#[Url] public string $view = 'gallery'` (`gallery`, `table`, `archive`; unknown values fall back
      to `gallery`)
    - `subjects()` follows R9: non-archived for gallery and table, only archived for archive
    - each subject also exposes `status`, `colour` and `?target_date`
    - `#[Computed] recentlyFinished()`: the user's milestones (R9) with `completed_at` in the current
      month (R1), newest first, with `subject` and `number`
22. [V]
    - **Tint:** `subject-tint` switches from R8's expression to `colour` (one line).
    - **Library:**
      - the Gallery/Table/Archive segmented switch (`aria-pressed`, `wire:click="$set('view', '...')"`)
      - the Table view as in `today.html`'s "Across your subjects": Subject (monogram + title), Current
        milestone ("04 · title", or "—"), Milestones (`progress-line`), Target (`target_date` "D MMM", or
        "No date")
      - Archive uses the gallery cards, with the empty state "No archived subjects."
      - "Recently finished" under Gallery and Table (not Archive), with its eyebrow and intro sentence, a
        ruled list of milestone title, subject and `local-date` "D MMM"; hidden when empty
    - **Subject page** property list:
      - Status pill: Active = vermilion tint with a dot; Paused and Archived = muted
      - Started
      - Target: `target_date->isoFormat('D MMMM YYYY')` (a date, so no conversion, R1), plus mono
        "N days left" / "N days over" / "Due today" (`trans_choice`), from [B]'s `?target_days_left`; "No
        date" when there is none
      - Tags as chips, hidden when there are none
      - done rows read "Done · 8 Oct" from `completed_at`
    - **Edit modals:**
      - the subject modal adds status (`flux:select`), target date (`type="date"`), tags (comma-separated,
        with a hint) and colour (four swatch `flux:radio`s with visible names)
      - the task add and edit forms add a type `flux:select` ("No type" first)
    - **Task cards:** the mono label row shows the type ("Docs", "Exercise · Up next"). Done cards show
      `display_url`, else the type, else nothing.

**Data contract C:** Subject `status`, `colour`, `?target_date`, `?target_days_left` (int: positive =
left, 0 = today, negative = over; computed by [B] with R1's today), `tags` (array of strings); Task `?type`;
Milestone `?completed_at`; Index `view`, `recentlyFinished` (each: `title`, `number`, `completed_at`,
`subject`); `form.status`, `form.target_date`, `form.tags` (string), `form.colour`; `taskForm.type`,
`editTaskForm.type`.

**Acceptance C:**
- the migration backfills `colour` and `completed_at` as stated
- R6 has tests:
  - the last task to Done sets the date; one task back clears it
  - adding a task to a completed milestone clears it
  - deleting the last not-done task sets it
- the view switch lists the right subjects, and archived subjects leave the sidebar
- `form.tags` validation is covered
- "Recently finished" lists only this month's, newest first

### D. Study sessions and the timer

23. [B] Migration and model `study_sessions`:
    - columns: `id`, `user_id` (FK, cascade), `subject_id` (FK, cascade), `task_id` (nullable FK, null on
      delete), `started_at` timestamp, `minutes` unsigned int nullable (`null` = timer running), timestamps
    - index `[user_id, started_at]`
    - relations: `User::studySessions()`, `Subject::studySessions()`, `Task::studySessions()`, with
      factory and `@property` docblocks
    - **completed session** = `minutes` is not null; running sessions never count
    - **totals:**
      - a task: its completed sessions
      - a milestone: completed sessions on its tasks
      - a subject: its completed sessions by `subject_id`, which keeps counting after a task is deleted
24. [B] Livewire component `App\Livewire\Sessions\LogSession` (embedded, not a page; explicit `render()`):
    - used as `<livewire:sessions.log-session :milestone="$milestone" />` on the milestone page, and without
      a milestone on Today
    - `mount(?Milestone $milestone)` authorizes `view` on its subject (404 otherwise)
    - the modal is named `log-session`; buttons anywhere open it with
      `<flux:modal.trigger name="log-session">`
    - `SessionForm`:
      - `subject_id`, required:
        - with a milestone, fixed to the milestone's subject (archived or not)
        - without one, one of the user's non-archived subjects, 404 otherwise
      - `task_id`, nullable:
        - with a milestone, a task of that milestone
        - without one, a task of the selected subject
        - 404 otherwise
        - reset to null when `subject_id` changes (a Form `updated` hook), with a test
      - `minutes`: required, 1–600
      - `date`: required, not after today (R1), default today
    - `#[Computed] subjectOptions()`, and `#[Computed] taskOptions()` (the milestone's tasks when there is
      a milestone, otherwise the selected subject's tasks)
    - `save()`:
      - stores `started_at` = noon of `date` in the display timezone, converted to UTC
      - resets the form and closes the modal (`Flux::modal('log-session')->close()`)
      - dispatches `session-logged`
      - with a milestone, also dispatches `BoardChanged` (step G)
25. [B] Timer on `Milestones\Show` (both actions authorize `update`):
    - `startTimer(int $taskId)`: the task through `findTask()`. It first stops any other running session of
      the user (anywhere) by the `stopTimer` rule, then creates a running session with `subject_id` and
      `task_id`.
      Starting on a Done task is allowed (the button only shows on up-next and In progress cards).
    - `stopTimer()`:
      - stops the user's running session when its task is in this milestone (404 otherwise)
      - saves `minutes = max(1, ceil(elapsed minutes))`
      - when the elapsed time is over 600 minutes (a forgotten timer), the session is **deleted, not saved**,
        and a toast says "That timer ran over 10 hours, so it wasn't saved. Log the session by hand."
      - the same rule applies when `startTimer` stops another running session
      - moving or completing the task doesn't stop the timer
    - `deleteTask()`: when the user's running session is on that task, stop it first by the `stopTimer`
      rule, so the time is kept on the subject. The task's sessions then keep `subject_id` and lose
      `task_id`.
    - `#[Computed] runningSession(): ?StudySession`: the user's running session, if its task is in this
      milestone
26. [B] Totals and listeners, without a query per row:
    - Subjects\Show `#[Computed] minutesLogged(): int`
    - Milestones\Show `#[Computed] minutesLogged(): int`
    - each task in `columns` exposes `minutes_logged` (int)
    - Index subjects expose `?days_since_studied` (0 = today, 1 = yesterday, N, null = never; R1 days)
    - `Milestones\Show`, `Subjects\Show` and (step F) `Today` refresh on `#[On('session-logged')]`
27. [V]
    - **"Log a session":** a primary button with a clock icon in the milestone header, wrapped in
      `flux:modal.trigger name="log-session"`.
    - **The modal** (`resources/views/livewire/sessions/log-session.blade.php`), a `flux:modal
      name="log-session"`:
      - a subject select (hidden when fixed), a task select ("No particular task" first), minutes, date
        (`type="date"`, `max` = today), Save and Cancel
    - **Task cards:**
      - `minutes_logged` in mono on the right of the label row when above 0, via `duration`
      - "Start timer" (play icon) on the `upNextTaskId` card and on In progress cards
      - the running card shows "Stop timer" and an Alpine mm:ss counter from
        `runningSession.started_at->toIso8601String()` (UTC with offset, so the browser parses it correctly;
        `x-data` + `setInterval`, display only; the server computes minutes)
    - **Time logged:** on the milestone stats row and the subject property list (`duration`).
    - **Library band label:** "Studied today", "Yesterday", "N days ago" (`trans_choice`), or "Not started"
      when null.

**Data contract D:**
- LogSession: `form.subject_id`, `form.task_id`, `form.minutes`, `form.date`, `subjectOptions`,
  `taskOptions`, `?milestone`, `save()`
- Milestones\Show: `?runningSession` (`task_id`, `started_at`), `startTimer()`, `stopTimer()`,
  `minutesLogged`; each task's `minutes_logged`
- Subjects\Show: `minutesLogged`
- Index: each subject's `?days_since_studied`

**Acceptance D:**
- a 45-minute session on a task adds 45 to the task, milestone and subject totals
- a session without a task adds to the subject only
- deleting the task keeps the subject total
- starting a timer on task B stops the one on task A
- a stopped timer saves 1–600 minutes; over 600 it is deleted and the toast shows; running sessions don't count
- deleting the timed task keeps its minutes on the subject
- changing the subject in LogSession clears the task
- a milestone in an archived subject can still log sessions
- another user's subject or task gives 404
- the date can't be in the future
- `days_since_studied` has boundary tests around midnight in the display timezone

### E. Field notes

28. [B] Migration and model `notes`:
    - columns: `id`, `milestone_id` (FK, cascade), `task_id` (nullable FK, null on delete), `body` text,
      timestamps
    - relations: `Milestone::notes()` (newest first), `Subject::notes()` (has-many-through), with factory
      and `@property` docblocks
    - each note handed to a view exposes `minutes`: completed session minutes on its task that started on
      the note's local date (R1). It is 0 without a task.
      - loaded with one query for the completed sessions of the listed notes' tasks, within the listed
        date range, then grouped by task and local date in PHP (no SQL date functions)
      - two notes on the same task and day both show that day's total; it is a display value, never summed
29. [B] `Milestones\Show`:
    - `NoteForm` (`body`: required, max 2000; `task_id`: nullable, a task of this milestone, 404 otherwise)
    - `addNote()` (authorize `update`) resets the form
    - `#[Computed] notes()` (with `task`)
    - the `task_id` default is `runningSession`'s task, else `upNextTaskId`
    - no `NotePolicy`: notes are reached through the milestone, like tasks
30. [B] `Subjects\Show` `#[Computed] recentNotes()`: the subject's 3 newest notes, with `milestone` (and its
    `number`).
31. [B] Field notes page:
    - `Route::livewire('notes', Notes\Index::class)->name('notes.index')` in the auth group
    - `App\Livewire\Notes\Index` (explicit `render()`) lists the user's notes newest first, scoped through
      `milestone.subject.user_id`, with `milestone.subject` and `task`, paginated by 20
    - tests: another user's notes never appear
32. [V]
    - **Milestone page:** a "Field notes" section under the board, with a 2px ink rule:
      - the heading and intro sentence
      - the composer card: an eyebrow label "Today · Fri 23 Oct" (`local-date` now) for the serif textarea,
        "Linked to" as a `flux:select` of the milestone's tasks ("Not linked" first), and "Add note"
      - the notes list:
        - the mono date "TUE / 20 OCT" and the serif body
        - "On *task title*", plus " · `duration`" when `minutes` > 0
        - the "On" line hidden when the note has no task
    - **Subject page:** the "In the margins" side column:
      - the 3 newest notes, with "TUE 20 OCT · 04"
      - "All field notes" (to `notes.index`)
      - the path becomes the wide column
      - the column is hidden when there are no notes
    - **Field notes page:** the same note rows, with subject / milestone links and pagination; the empty
      state "No field notes yet. Add one from a milestone's board."
    - **Sidebar:** the "Field notes" nav item.

**Data contract E:**
- Milestones\Show: `notes` (each: `body`, `created_at`, `?task` {`title`}, `minutes`), `noteForm.body`,
  `noteForm.task_id`, `addNote()`
- Subjects\Show: `recentNotes` (each: `body`, `created_at`, `milestone` {`id`, `title`, `number`})
- Notes\Index: `notes` (a paginator; each: `body`, `created_at`, `?task`, `minutes`, `milestone`
  {`title`, `number`, `subject` {`id`, `title`}})

**Acceptance E:**
- a new note appears at the top of the milestone's notes, in the subject's margins and on `/notes`
- linking another milestone's task gives 404
- another user's notes never appear on `/notes`
- `minutes` has tests for: no task, a task with sessions that day, and a task with none

### F. Streak and the Today page

33. [B] `App\Livewire\Today` (a top-level page component: an intentional exception to the `<Area>/<Name>`
    layout; explicit `render()`):
    - `Route::livewire('dashboard', Today::class)->name('dashboard')` replaces the `Route::view`, keeping
      the URL and name (Fortify redirects there)
    - [B] deletes `resources/views/dashboard.blade.php` in the same change, because the placeholder view
      replaces it
34. [B] `Today` computed properties, all following R1 and R9:
    - `greeting`: "Good morning" 05:00–11:59, "Good afternoon" 12:00–17:59, otherwise "Good evening";
      `firstName`
    - `today` (date), `weekNumber` (ISO)
    - `streak` (int):
      - consecutive days with at least one completed session
      - counted back from today when today has one, otherwise from yesterday
      - 0 when neither day has one
      - tests: today only, yesterday only, a gap, a back-dated session, the midnight boundary
    - `streakIncludesToday` (bool)
    - `nowStudying` (`?`; `subject` {`id`, `title`}, `milestone` {`id`, `title`, `?description`, `number`},
      and the counts `done`, `in_progress`, `total`):
      - the current milestone of the subject with the latest completed session, when that subject is not
        archived and has a current milestone
      - otherwise the current milestone of the newest (highest id) non-archived subject that has one
      - otherwise null
    - `upNext` (`?`; a Task with `type`, `minutes_logged`): R5 within `nowStudying.milestone`
    - `remainingTasks` (int): the not-done tasks of `nowStudying.milestone`
    - `desk`:
      - the in-progress tasks across non-archived subjects, latest `updated_at` first, at most 5
      - each with `display_url`, `type`, and `milestone.subject`
    - `deskCount` (int, uncapped)
    - `week`:
      - 7 items, Monday to Sunday of the current ISO week
      - each with `date`, `minutes`, `percent` (0–100 of the week's highest day; 0 when all are 0),
        `is_today`, `is_future`
    - `weekMinutes` (int)
    - `latestNote` (`?`; Note with `milestone.subject`)
    - `subjects`: the Library table data (`current_milestone` with `number`, `done_milestones_count`,
      `milestones_count`, `target_date`, `colour`)
    - it listens for `session-logged`, and embeds `LogSession` without a milestone
35. [V] Today (`resources/views/livewire/today.blade.php`), `today.html`:
    - `x-page-header` with "Today" and the "Log a session" trigger. The "Weekly review" button is left out.
    - the date line: `local-date` "dddd, D MMMM YYYY", then "Week N" plus:
      - " · Day N of your streak" when `streakIncludesToday`
      - " · N-day streak, study today to keep it" when `streak` > 0 but not today
      - nothing when `streak` is 0
    - "Good morning/afternoon/evening, *firstName.*"
    - the summary sentence: the first part from this table, all counts via `trans_choice`

      | Case | First part |
      |---|---|
      | desk > 0 | "N tasks are on your desk" |
      | desk = 0 | "Nothing is on your desk yet" |

      then, when `nowStudying` is set:

      | Milestone state | Second part |
      |---|---|
      | `total` = 0 | ", and *milestone* has no tasks yet." |
      | `remainingTasks` = 0 | ", and *milestone* is ready to finish." |
      | otherwise | ", and *milestone* is N steps from done." |

      otherwise a full stop. Then " Pick up where you left off." when `upNext` is set, else " Pick a task
      to start." when the desk is empty.
    - "Now studying" (when `nowStudying`):
      - the vermilion eyebrow "Now studying · subject", the milestone serif title, the description (hidden
        when null), `progress-segments`, and the mono "done / total done" next to it
      - the Up next card:
        - the eyebrow "Up next · type · `duration` logged", where the type part is dropped when null and
          the minutes part when 0
        - the serif title, and "Continue" to the milestone
        - when `upNext` is null: "No tasks yet. Add some to this milestone." with a link to it
    - when `nowStudying` is null: the empty state "Start a subject in your library." linking to Library
    - "On your desk": a numbered list in vermilion, each with the title and the secondary line
      `display_url`, else "subject · type", else "subject"; or "Nothing in progress." when empty
    - "This week":
      - the total via `duration`
      - a 7-bar chart, `role="img"` with `aria-label` "Minutes studied: Mon 45, Tue 80, …, Fri 25 so far",
        where future days are left out and today says "so far"
      - bars: a future day is a dashed outline; a day with 0 minutes is a 2px line (vermilion on today);
        otherwise the bar is `percent` high, in ink, or vermilion on today
      - the day labels below, with today in vermilion
    - "From your field notes": the latest note as an italic serif blockquote, with `local-date` "ddd D MMM"
      and the milestone link; hidden when there is none
    - "Across your subjects": the Library table from step C, with "Open library"

**Data contract F:** Today `greeting`, `firstName`, `today`, `weekNumber`, `streak`, `streakIncludesToday`, `?nowStudying`,
`?upNext`, `remainingTasks`, `desk`, `deskCount`, `week`, `weekMinutes`, `?latestNote`, `subjects`.

**Acceptance F:**
- `/dashboard` renders Today
- the greeting boundaries are tested with `Carbon::setTestNow` at 04:59, 05:00, 11:59, 12:00, 17:59 and 18:00
- the streak and week rules are tested
- a brand-new user sees every empty state (a test asserts the texts)

### G. Real-time board (Phase 5)

36. [B] Event `App\Events\BoardChanged` (`int $milestoneId`):
    - implements `ShouldBroadcast` (queued) and uses `Dispatchable`, `InteractsWithSockets` and
      `SerializesModels`
    - on `PrivateChannel('milestones.'.$milestoneId)`, with `broadcastAs()` `board.changed` and no payload
      beyond the id
    - fired with `broadcast(new BoardChanged($id))->toOthers()`: `Dispatchable::dispatch()` has no
      `toOthers()`, and `InteractsWithSockets` supplies the socket id
    - fired once per action, after `moveTask`, `addTask`, `updateTask`, `deleteTask`, `startTimer`,
      `stopTimer`, `addNote`, the milestone's `update()`, and `LogSession::save()` with a milestone
    - `Event::fake()` + `Event::assertDispatched(BoardChanged::class)` still sees it
37. [B] `routes/channels.php`: `milestones.{milestone}` is authorized when
    `$user->can('view', $milestone->subject)`.
38. [B] `Milestones\Show::refreshBoard()` with
    `#[On('echo-private:milestones.{milestone.id},.board.changed')]` clears the cached `columns`, `notes`,
    `upNextTaskId`, `runningSession` and `minutesLogged`.
39. [B] `CLAUDE.md` documents the local run:
    - `php artisan reverb:start`, plus a queue worker (`php artisan queue:work`, or `composer dev`)
    - the `REVERB_*` keys in `.env`
    - rebuilding assets after changing `VITE_REVERB_*`
    - without a worker, nothing broadcasts
40. [V] The Live indicator in the milestone header:
    - an Alpine snippet bound to `window.Echo?.connector?.pusher?.connection` (`state_change` events)
    - a green dot with "Live" while `connected`; a muted dot with "Offline" otherwise
    - hidden when Echo is missing
    - `aria-live="polite"` on the label

**Data contract G:** none beyond B–F.

**Acceptance G:**
- `Event::assertDispatched(BoardChanged::class)` for every action in 36
- channel auth: the owner is allowed, another user is denied
- manual check:
  - with `reverb:start` and a worker running, moving a card in one tab moves it in a second tab without
    a reload
  - the dot shows "Offline" after Reverb is stopped

## Acceptance, every step

- `composer test` passes: Pint, Larastan level 7, the full suite, and Postgres in CI.
- Every new action has happy-path, validation and authorization/404 tests ([B]).
- Every "without a query per row" item has a query-count test that compares 1 row with 3, with the
  logging started after setup.
- The step's pages match their mockup at desktop width and reflow at 390px wide (checked by eye), and
  every action is reachable with the keyboard.

## Out of scope

- The Weekly review page, its nav item and its Today button (the handoff: "not designed yet").
- ⌘K search, the Share button, and "Pinned resources" on the subject page (in the mockups, not in the
  handoff's feature list).
- A task description field, the milestone List view, and the Board/List/Field notes tabs.
- Editing or deleting notes and sessions, and a sessions history page.
- Per-user timezones (one display timezone), an editable start date, and dark mode.
- Presence channels and live cursors.
- Changes to drag and drop or position rules (Phase 4 stays).

## Assumptions

**Decided by the developer (2026-10-23):**
- Scope is everything in the handoff.
- Light only; the Appearance page is removed.
- Phase 5 real-time is part of this spec.
- Sessions need a subject, and the task is optional.

**Decided by Claude, open to change:**
1. No milestone tabs: the List view isn't designed, and field notes sit under the board in the mockup anyway.
2. "New subject" from the sidebar goes to `/subjects?new=1`, which opens the Library's modal. There is no
   modal component in the layout.
3. The drag handle and `···` menu stay on task cards for keyboard and touch users (Phase 4).
4. The Field notes nav item gets a simple list page (31), so the nav never leads nowhere.
5. Tags are a JSON array on `subjects`, not a tags table: there is no tag filtering yet.
6. A manually logged session is stored at noon of its local date, so it lands on that day.
7. "Started" means `created_at`.
8. The broadcast is queued (`ShouldBroadcast`), because the Redis worker is needed for Phase 6 anyway.
   Tests use `sync`.
9. Timestamps stay in UTC; only display and day boundaries use `Europe/Sofia` (R1). This avoids
   re-reading existing data in another zone and daylight-saving ambiguity.
10. An empty current milestone is intended (R4).
11. A timer that runs over 600 minutes is treated as forgotten: it is discarded with a toast, not saved
    as 10 hours of fake study.
12. "Good evening" covers 18:00–04:59.
13. Paused subjects stay visible but don't feed the desk or "Now studying" (R9).
14. Deleting a task that is being timed saves the time so far on the subject.
15. When the streak ends yesterday, Today says "N-day streak, study today to keep it" instead of claiming
    "Day N" for a day without study.
16. Manual sessions stored at noon are fine: no feature shows the time of day of a session.
