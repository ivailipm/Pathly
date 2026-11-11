@use('App\Enums\TaskStatus')

@php
    $milestones = $this->milestones->keyBy('id');
    $currentId = $this->milestones->first(fn ($milestone) => ! $milestone->is_done)?->id;
    $taskCount = $this->milestones->sum('tasks_count');
@endphp

<div class="flex flex-col gap-4">
    @if ($this->milestones->isEmpty())
        <p class="border-b border-rule py-5 text-secondary">{{ __('No milestones yet. Sketch the path first, then add tasks to each milestone.') }}</p>
    @else
        @if ($taskCount === 0)
            <p class="text-secondary">{{ __('No tasks yet. Open a milestone on the path to add its tasks.') }}</p>
        @endif

        <div class="grid items-start gap-4 lg:grid-cols-3">
            @foreach (TaskStatus::cases() as $status)
                @php
                    $column = $this->columns[$status->value];
                @endphp

                <section wire:key="subject-column-{{ $status->value }}" class="flex min-w-0 flex-col gap-3 rounded-card bg-sand p-3.5" aria-labelledby="subject-column-{{ $status->value }}-heading">
                    <h3 id="subject-column-{{ $status->value }}-heading" class="flex items-center gap-2 px-1 pt-0.5 font-mono text-xs tracking-label uppercase">
                        <span aria-hidden="true" @class([
                            'size-[7px] rounded-full',
                            'border border-muted' => $status === TaskStatus::Todo,
                            'bg-vermilion' => $status === TaskStatus::InProgress,
                            'bg-ink' => $status === TaskStatus::Done,
                        ])></span>
                        {{ __($status->label()) }}
                        <span class="text-muted">{{ $column->count() }}</span>
                    </h3>

                    {{-- wire:sort must come first: Livewire stops reading this element's attributes at wire:sort:group.
                         Milestone headings sit between the cards; wire:sort only counts the cards. --}}
                    <div
                        wire:sort="moveTask"
                        wire:sort:group="subject-{{ $subject->id }}"
                        wire:sort:group-id="{{ $status->value }}"
                        class="flex min-h-16 flex-col gap-2"
                    >
                        @foreach ($column->groupBy('milestone_id') as $milestoneId => $tasks)
                            @php
                                $milestone = $milestones[$milestoneId];
                                $folded = $this->isFolded($milestone);
                            @endphp

                            <div wire:key="subject-group-{{ $status->value }}-{{ $milestoneId }}" class="flex items-center justify-between gap-2 px-1 pt-2 first:pt-0">
                                <a
                                    href="{{ route('milestones.show', [$subject, $milestone]) }}"
                                    wire:navigate
                                    @class([
                                        'flex min-w-0 items-center gap-1.5 font-mono text-[11px] tracking-label uppercase hover:text-ink focus-visible:outline-2 focus-visible:outline-ink',
                                        'text-vermilion' => $milestone->id === $currentId,
                                        'text-muted' => $milestone->id !== $currentId,
                                    ])
                                >
                                    @if ($milestone->is_done)
                                        <flux:icon.check variant="micro" class="size-3.5 flex-none" />
                                    @endif
                                    <span class="truncate">{{ sprintf('%02d', $milestone->number) }} · {{ $milestone->title }}</span>
                                </a>

                                @if ($milestone->is_done)
                                    <button
                                        type="button"
                                        wire:click="toggle({{ $milestone->id }})"
                                        aria-expanded="{{ $folded ? 'false' : 'true' }}"
                                        class="flex min-h-8 flex-none items-center gap-1 rounded-button px-1.5 font-mono text-[11px] text-muted hover:text-ink focus-visible:outline-2 focus-visible:outline-ink"
                                    >
                                        {{ $folded ? trans_choice('Show :count task|Show :count tasks', $tasks->count()) : __('Hide') }}
                                        <flux:icon.chevron-down variant="micro" @class(['size-3.5', 'rotate-180' => ! $folded]) />
                                    </button>
                                @endif
                            </div>

                            @unless ($folded)
                                @foreach ($tasks as $task)
                                    @php
                                        $isDone = $task->status === TaskStatus::Done;
                                        $minutes = (int) $task->minutes_logged;
                                    @endphp

                                    <article
                                        wire:key="subject-task-{{ $task->id }}"
                                        wire:sort:item="{{ $task->id }}"
                                        @class([
                                            'group flex items-start gap-2 rounded-task border',
                                            'border-rule bg-white p-3' => ! $isDone,
                                            'border-rule/70 bg-paper px-3 py-2.5' => $isDone,
                                        ])
                                    >
                                        @if ($isDone)
                                            <flux:icon.check variant="micro" class="mt-0.5 size-4 flex-none text-muted" />
                                        @endif

                                        <div class="flex min-w-0 flex-1 flex-col gap-1">
                                            @if (! $isDone && ($task->type || $minutes > 0))
                                                <div class="flex items-baseline justify-between gap-2">
                                                    <span class="font-mono text-[11px] tracking-label text-muted uppercase">{{ $task->type ? __($task->type->label()) : '' }}</span>
                                                    @if ($minutes > 0)
                                                        <x-duration :minutes="$minutes" class="font-mono text-[11px] whitespace-nowrap text-muted" />
                                                    @endif
                                                </div>
                                            @endif

                                            {{-- Opens the task panel --}}
                                            <button
                                                type="button"
                                                wire:click="$dispatch('open-task', { id: {{ $task->id }} })"
                                                @class([
                                                    'self-start text-left underline-offset-4 hover:underline focus-visible:outline-2 focus-visible:outline-ink',
                                                    'text-[15px] leading-[1.35] font-medium text-ink' => ! $isDone,
                                                    'text-sm text-muted line-through decoration-zinc-400' => $isDone,
                                                ])
                                            >{{ $task->title }}</button>

                                            @if ($task->url && ! $isDone)
                                                <a
                                                    href="{{ $task->url }}"
                                                    target="_blank"
                                                    rel="noopener noreferrer"
                                                    class="flex max-w-full min-w-0 items-center gap-1.5 self-start font-mono text-xs text-muted hover:text-ink"
                                                >
                                                    <flux:icon.link variant="micro" class="size-[13px] flex-none" />
                                                    <span class="min-w-0 truncate">{{ $task->display_url }}</span>
                                                </a>
                                            @endif
                                        </div>

                                        <div class="-me-1.5 -mt-1.5 flex flex-none items-center">
                                            <span
                                                wire:sort:handle
                                                aria-hidden="true"
                                                class="flex size-8 cursor-grab items-center justify-center text-zinc-400 group-focus-within:opacity-100 group-hover:opacity-100 active:cursor-grabbing pointer-fine:opacity-0"
                                            >
                                                <flux:icon.grip-vertical variant="micro" />
                                            </span>

                                            <flux:dropdown align="end">
                                                <flux:button variant="ghost" size="sm" icon="ellipsis-horizontal" :aria-label="__('Actions for :title', ['title' => $task->title])" class="text-muted!" />

                                                <flux:menu>
                                                    @foreach (TaskStatus::cases() as $other)
                                                        @continue($other === $status)

                                                        <flux:menu.item wire:click="moveTask({{ $task->id }}, {{ $this->columns[$other->value]->count() }}, '{{ $other->value }}')">
                                                            {{ __('Move to :status', ['status' => __($other->label())]) }}
                                                        </flux:menu.item>
                                                    @endforeach

                                                    <flux:menu.separator />

                                                    <flux:menu.item icon="pencil" wire:click="$dispatch('open-task', { id: {{ $task->id }}, edit: true })">{{ __('Edit') }}</flux:menu.item>
                                                </flux:menu>
                                            </flux:dropdown>
                                        </div>
                                    </article>
                                @endforeach
                            @endunless
                        @endforeach

                        @if ($column->isEmpty())
                            <p class="px-1 text-sm text-muted">{{ __('Nothing here yet') }}</p>
                        @endif
                    </div>
                </section>
            @endforeach
        </div>
    @endif

    <livewire:tasks.panel :subject="$subject" :key="'subject-panel-'.$subject->id" />
</div>
