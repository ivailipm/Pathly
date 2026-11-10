@use('App\Enums\TaskStatus')
@use('App\Enums\TaskType')

@php
    $taskCount = collect($this->columns)->sum(fn ($column) => $column->count());
    $doneCount = $this->columns[TaskStatus::Done->value]->count();

    // Read once: Livewire doesn't cache a computed property that returns null, so each read would query again
    $runningSession = $this->runningSession;
@endphp

<div>
    <x-page-header>
        <x-slot:breadcrumbs>
            <flux:breadcrumbs>
                <flux:breadcrumbs.item :href="route('subjects.index')" separator="slash" wire:navigate>{{ __('Library') }}</flux:breadcrumbs.item>
                <flux:breadcrumbs.item :href="route('subjects.show', $subject)" separator="slash" wire:navigate>{{ $subject->title }}</flux:breadcrumbs.item>
                <flux:breadcrumbs.item separator="slash">{{ $milestone->title }}</flux:breadcrumbs.item>
            </flux:breadcrumbs>
        </x-slot:breadcrumbs>

        <x-slot:actions>
            <flux:button wire:click="edit">{{ __('Edit') }}</flux:button>

            <flux:modal.trigger name="log-session">
                <flux:button variant="primary" icon="clock">{{ __('Log a session') }}</flux:button>
            </flux:modal.trigger>
        </x-slot:actions>
    </x-page-header>

    <div class="mx-auto flex w-full max-w-[1180px] flex-col gap-9 px-4 pt-12 pb-24 sm:px-10">
        <section class="flex flex-col gap-4">
            <x-eyebrow accent>
                {{ __('Milestone :number of :count', ['number' => sprintf('%02d', $this->number), 'count' => sprintf('%02d', $this->milestoneCount)]) }}
            </x-eyebrow>

            <h1 class="font-serif text-[clamp(44px,5.5vw,68px)] leading-[1.02] tracking-[-0.02em] text-balance">{{ $milestone->title }}</h1>

            @if (filled($milestone->description))
                <p class="max-w-[640px] font-serif text-[21px] leading-[1.45] text-pretty text-body">{{ $milestone->description }}</p>
            @endif

            <div class="flex flex-wrap gap-x-10 gap-y-3 pt-2">
                <div class="flex flex-col gap-1.5">
                    <x-eyebrow size="sm">{{ __('Progress') }}</x-eyebrow>

                    @if ($taskCount > 0)
                        <div class="flex items-center gap-2.5">
                            <x-progress-segments
                                :done="$doneCount"
                                :in-progress="$this->columns[TaskStatus::InProgress->value]->count()"
                                :total="$taskCount"
                                class="w-[168px]"
                            />
                            <span class="text-sm" aria-hidden="true">{{ __(':done of :total', ['done' => $doneCount, 'total' => $taskCount]) }}</span>
                        </div>
                    @else
                        <span class="text-sm">{{ __('No tasks yet. Add a resource or exercise below.') }}</span>
                    @endif
                </div>

                <div class="flex flex-col gap-1.5">
                    <x-eyebrow size="sm">{{ __('Time logged') }}</x-eyebrow>
                    <x-duration :minutes="$this->minutesLogged" class="text-sm" />
                </div>

                <div class="flex flex-col gap-1.5">
                    <x-eyebrow size="sm">{{ __('Started') }}</x-eyebrow>
                    <x-local-date :value="$milestone->created_at" format="D MMMM" class="text-sm" />
                </div>
            </div>
        </section>

        <div class="flex flex-wrap items-center justify-between gap-3 border-b border-rule">
            <h2 class="-mb-px flex min-h-11 items-center border-b-2 border-ink text-sm font-medium">{{ __('Board') }}</h2>
            <span class="text-[13px] text-muted">{{ __('Drag cards between columns') }}</span>
        </div>

        <section class="grid grid-cols-[repeat(auto-fit,minmax(min(280px,100%),1fr))] items-start gap-5" aria-label="{{ __('Board') }}">
            @foreach (TaskStatus::cases() as $status)
                @php
                    $column = $this->columns[$status->value];
                @endphp

                <div wire:key="column-{{ $status->value }}" class="flex flex-col gap-2.5 rounded-card bg-sand p-3">
                    <div class="flex min-h-9 items-center justify-between gap-2 ps-1">
                        <h3 class="flex items-center gap-2 font-mono text-xs tracking-label text-body uppercase">
                            <span aria-hidden="true" @class([
                                'size-2 rounded-full',
                                'border-[1.5px] border-muted' => $status === TaskStatus::Todo,
                                'bg-vermilion' => $status === TaskStatus::InProgress,
                                'bg-ink' => $status === TaskStatus::Done,
                            ])></span>
                            {{ __($status->label()) }}
                            <span class="text-muted">{{ $column->count() }}</span>
                        </h3>

                        <flux:button
                            variant="ghost"
                            icon="plus"
                            wire:click="openAddTask('{{ $status->value }}')"
                            :aria-label="__('Add task to :column', ['column' => __($status->label())])"
                            class="text-muted!"
                        />
                    </div>

                    @if ($column->isEmpty())
                        <p class="px-1 text-sm text-muted">{{ __('Nothing here yet') }}</p>
                    @endif

                    {{-- wire:sort must come first: Livewire stops reading this element's attributes at wire:sort:group --}}
                    <div
                        wire:sort="moveTask"
                        wire:sort:group="tasks"
                        wire:sort:group-id="{{ $status->value }}"
                        class="flex min-h-16 flex-col gap-2.5"
                    >
                        @foreach ($column as $task)
                            @php
                                $isDone = $task->status === TaskStatus::Done;
                                $isUpNext = $task->id === $this->upNextTaskId;
                                $typeLabel = $task->type ? __($task->type->label()) : null;
                                $label = implode(' · ', array_filter([$typeLabel, $isUpNext ? __('Up next') : null]));
                                $minutes = (int) $task->minutes_logged;
                                $isRunning = $runningSession?->task_id === $task->id;
                                $canTime = ! $isDone && ($isUpNext || $task->status === TaskStatus::InProgress);
                            @endphp

                            <article
                                wire:key="task-{{ $task->id }}"
                                wire:sort:item="{{ $task->id }}"
                                @class([
                                    'group flex items-start gap-2.5 rounded-task border',
                                    'border-rule bg-white p-3.5' => ! $isDone,
                                    'shadow-[0_1px_2px_rgba(25,25,23,0.05)]' => $isUpNext,
                                    'border-rule/70 bg-paper px-3.5 py-3' => $isDone,
                                ])
                            >
                                @if ($editingTaskId === $task->id)
                                    <form wire:submit="updateTask" class="min-w-0 flex-1 space-y-3">
                                        <flux:input wire:model="editTaskForm.title" :label="__('Title')" required />
                                        <flux:input wire:model="editTaskForm.url" :label="__('Link')" type="url" placeholder="https://" />
                                        <flux:select wire:model="editTaskForm.type" :label="__('Type')">
                                            <flux:select.option value="">{{ __('No type') }}</flux:select.option>
                                            @foreach (TaskType::cases() as $type)
                                                <flux:select.option :value="$type->value">{{ __($type->label()) }}</flux:select.option>
                                            @endforeach
                                        </flux:select>

                                        <div class="flex gap-2">
                                            <flux:button type="submit" variant="primary">{{ __('Save') }}</flux:button>
                                            <flux:button type="button" variant="ghost" wire:click="cancelTaskEdit">{{ __('Cancel') }}</flux:button>
                                        </div>
                                    </form>
                                @else
                                    @if ($isDone)
                                        <flux:icon.check variant="micro" class="mt-0.5 size-4 flex-none text-muted" />
                                    @endif

                                    <div @class(['flex min-w-0 flex-1 flex-col', 'gap-2.5' => ! $isDone, 'gap-0.5' => $isDone])>
                                        @if (! $isDone && ($label !== '' || $minutes > 0))
                                            <div class="flex items-baseline justify-between gap-2">
                                                <span @class([
                                                    'font-mono text-[11px] tracking-label uppercase',
                                                    'text-vermilion' => $isUpNext,
                                                    'text-muted' => ! $isUpNext,
                                                ])>{{ $label }}</span>

                                                @if ($minutes > 0)
                                                    <x-duration :minutes="$minutes" class="font-mono text-[11px] whitespace-nowrap text-muted" />
                                                @endif
                                            </div>
                                        @endif

                                        <span @class([
                                            'text-[15px] leading-[1.35] font-medium text-ink' => ! $isDone,
                                            'text-sm text-muted line-through decoration-zinc-400' => $isDone,
                                        ])>{{ $task->title }}</span>

                                        @if ($task->url)
                                            <a
                                                href="{{ $task->url }}"
                                                target="_blank"
                                                rel="noopener noreferrer"
                                                @class([
                                                    'flex max-w-full min-w-0 items-center gap-1.5 self-start font-mono text-muted hover:text-ink',
                                                    'text-xs' => ! $isDone,
                                                    'text-[11px]' => $isDone,
                                                ])
                                            >
                                                @unless ($isDone)
                                                    <flux:icon.link variant="micro" class="size-[13px] flex-none" />
                                                @endunless
                                                <span class="min-w-0 truncate">{{ $task->display_url }}</span>
                                            </a>
                                        @elseif ($isDone && $typeLabel)
                                            <span class="font-mono text-[11px] text-muted">{{ $typeLabel }}</span>
                                        @endif

                                        @if ($isRunning)
                                            <div class="flex flex-wrap items-center gap-2 pt-1">
                                                <flux:button size="sm" icon="stop" wire:click="stopTimer" class="min-h-10">{{ __('Stop timer') }}</flux:button>

                                                {{-- Display only: the server works out the minutes when the timer stops --}}
                                                <span
                                                    wire:key="clock-{{ $runningSession->id }}"
                                                    x-data="{
                                                        start: Date.parse(@js($runningSession->started_at->toIso8601String())),
                                                        now: Date.now(),
                                                        tick: null,
                                                        init() { this.tick = setInterval(() => this.now = Date.now(), 1000) },
                                                        destroy() { clearInterval(this.tick) },
                                                        get elapsed() {
                                                            const seconds = Math.max(0, Math.floor((this.now - this.start) / 1000));
                                                            return String(Math.floor(seconds / 60)).padStart(2, '0') + ':' + String(seconds % 60).padStart(2, '0');
                                                        },
                                                    }"
                                                    x-text="elapsed"
                                                    role="timer"
                                                    aria-label="{{ __('Time on this task') }}"
                                                    class="font-mono text-xs text-vermilion tabular-nums"
                                                >00:00</span>
                                            </div>
                                        @elseif ($canTime)
                                            <div class="pt-1">
                                                <flux:button size="sm" icon="play" wire:click="startTimer({{ $task->id }})" class="min-h-10">{{ __('Start timer') }}</flux:button>
                                            </div>
                                        @endif
                                    </div>

                                    <div class="-me-2 -mt-2 flex flex-none items-center">
                                        <span
                                            wire:sort:handle
                                            aria-hidden="true"
                                            class="flex size-8 cursor-grab items-center justify-center text-zinc-400 group-focus-within:opacity-100 group-hover:opacity-100 active:cursor-grabbing pointer-fine:opacity-0"
                                        >
                                            <flux:icon.grip-vertical variant="micro" />
                                        </span>

                                        <flux:dropdown align="end">
                                            <flux:button variant="ghost" icon="ellipsis-horizontal" :aria-label="__('Task actions')" class="text-muted!" />

                                            <flux:menu>
                                                @foreach (TaskStatus::cases() as $other)
                                                    @continue($other === $status)

                                                    <flux:menu.item wire:click="moveTask({{ $task->id }}, {{ $this->columns[$other->value]->count() }}, '{{ $other->value }}')">
                                                        {{ __('Move to :status', ['status' => __($other->label())]) }}
                                                    </flux:menu.item>
                                                @endforeach

                                                <flux:menu.separator />

                                                <flux:menu.item icon="pencil" wire:click="editTask({{ $task->id }})">{{ __('Edit') }}</flux:menu.item>
                                                <flux:menu.item
                                                    icon="trash"
                                                    variant="danger"
                                                    wire:click="deleteTask({{ $task->id }})"
                                                    wire:confirm="{{ __('Delete this task?') }}"
                                                >
                                                    {{ __('Delete') }}
                                                </flux:menu.item>
                                            </flux:menu>
                                        </flux:dropdown>
                                    </div>
                                @endif
                            </article>
                        @endforeach
                    </div>

                    @if ($addingTo === $status->value)
                        <form wire:submit="addTask" class="flex flex-col gap-3 rounded-task border border-rule bg-white p-3.5">
                            <flux:input wire:model="taskForm.title" :label="__('Title')" required autofocus />
                            <flux:input wire:model="taskForm.url" :label="__('Link (optional)')" type="url" placeholder="https://" />
                            <flux:select wire:model="taskForm.type" :label="__('Type')">
                                <flux:select.option value="">{{ __('No type') }}</flux:select.option>
                                @foreach (TaskType::cases() as $type)
                                    <flux:select.option :value="$type->value">{{ __($type->label()) }}</flux:select.option>
                                @endforeach
                            </flux:select>

                            <div class="flex gap-2">
                                <flux:button type="submit" variant="primary">{{ __('Add') }}</flux:button>
                                <flux:button type="button" variant="ghost" wire:click="closeAddTask">{{ __('Cancel') }}</flux:button>
                            </div>
                        </form>
                    @elseif ($status === TaskStatus::Todo)
                        <button
                            type="button"
                            wire:click="openAddTask('{{ TaskStatus::Todo->value }}')"
                            class="flex min-h-10 items-center gap-2 rounded-button px-1.5 text-sm text-muted hover:text-ink focus-visible:outline-2 focus-visible:outline-ink"
                        >
                            <flux:icon.plus class="size-[15px]" />
                            {{ __('Paste a link or add a task') }}
                        </button>
                    @endif
                </div>
            @endforeach
        </section>

        <section class="mt-6 flex flex-wrap gap-12 border-t-2 border-ink pt-8" aria-labelledby="field-notes-heading">
            <div class="flex flex-[1_1_240px] flex-col gap-2.5">
                <h2 id="field-notes-heading" class="font-serif text-[34px] leading-[1.1]">{{ __('Field notes') }}</h2>
                <p class="max-w-[260px] text-sm text-secondary">{{ __('What clicked, what didn\'t, and what to try next. Three lines after each session is plenty.') }}</p>
            </div>

            <div class="flex min-w-0 flex-[3_1_520px] flex-col">
                <form wire:submit="addNote" class="flex flex-col gap-2.5 rounded-card border border-rule bg-white p-[18px] focus-within:border-ink">
                    <label for="note-body" class="font-mono text-[11px] tracking-label text-muted uppercase">
                        {{ __('Today') }} · <x-local-date :value="now()" format="ddd D MMM" />
                    </label>

                    <textarea
                        id="note-body"
                        wire:model="noteForm.body"
                        rows="3"
                        placeholder="{{ __('What did you learn this session?') }}"
                        class="resize-y border-0 bg-transparent p-0 font-serif text-[19px] leading-[1.45] text-ink outline-none placeholder:text-muted"
                    ></textarea>

                    @error('noteForm.body')
                        <p class="text-sm text-red-700">{{ $message }}</p>
                    @enderror

                    <div class="flex flex-wrap items-end justify-between gap-2.5">
                        <flux:select wire:model="noteForm.task_id" :label="__('Linked to')" class="max-w-xs">
                            <flux:select.option value="">{{ __('Not linked') }}</flux:select.option>
                            @foreach (collect($this->columns)->flatten(1) as $task)
                                <flux:select.option :value="$task->id" wire:key="note-task-{{ $task->id }}">{{ $task->title }}</flux:select.option>
                            @endforeach
                        </flux:select>

                        <flux:button type="submit" variant="primary">{{ __('Add note') }}</flux:button>
                    </div>
                </form>

                @forelse ($this->notes as $note)
                    <x-note-row :note="$note" wire:key="note-{{ $note->id }}" />
                @empty
                    <p class="py-7 text-sm text-muted">{{ __('No notes on this milestone yet.') }}</p>
                @endforelse
            </div>
        </section>
    </div>

    <flux:modal wire:model="editing" @close="cancelEdit" class="w-full max-w-md">
        <form wire:submit="update" class="space-y-6">
            <flux:heading size="lg">{{ __('Edit milestone') }}</flux:heading>

            <flux:input wire:model="form.title" :label="__('Title')" required />
            <flux:textarea wire:model="form.description" :label="__('Description')" rows="3" />

            <div class="flex flex-wrap items-center justify-between gap-2">
                <flux:button
                    type="button"
                    variant="ghost"
                    icon="trash"
                    wire:click="delete"
                    wire:confirm="{{ __('Delete this milestone and all its tasks?') }}"
                    class="text-red-700!"
                >
                    {{ __('Delete milestone') }}
                </flux:button>

                <div class="flex gap-2">
                    <flux:modal.close>
                        <flux:button variant="ghost">{{ __('Cancel') }}</flux:button>
                    </flux:modal.close>
                    <flux:button type="submit" variant="primary">{{ __('Save') }}</flux:button>
                </div>
            </div>
        </form>
    </flux:modal>

    <livewire:sessions.log-session :milestone="$milestone" />
</div>
