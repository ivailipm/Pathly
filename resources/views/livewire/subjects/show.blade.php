@use('App\Enums\SubjectStatus')

@php
    $letter = mb_strtoupper(mb_substr(trim($subject->title), 0, 1));

    // The cover word: the title up to the first space, without leading non-letters ("Laravel")
    $coverWord = (string) preg_replace('/^[^\p{L}]+/u', '', (string) str($subject->title)->trim()->before(' '));
    $coverWord = $coverWord !== '' ? $coverWord : $letter;

    // Long words get a smaller size so more of them shows; the band still clips what doesn't fit
    $coverSize = match (true) {
        mb_strlen($coverWord) <= 7 => 'text-[160px] sm:text-[220px]',
        mb_strlen($coverWord) <= 10 => 'text-[130px] sm:text-[170px]',
        default => 'text-[110px] sm:text-[130px]',
    };

    $taskTotal = $this->milestones->sum('tasks_count');
    $nextNumber = sprintf('%02d', $this->milestones->count() + 1);
@endphp

<div>
    <x-page-header>
        <x-slot:breadcrumbs>
            <flux:breadcrumbs>
                <flux:breadcrumbs.item :href="route('subjects.index')" separator="slash" wire:navigate>{{ __('Library') }}</flux:breadcrumbs.item>
                <flux:breadcrumbs.item separator="slash">{{ $subject->title }}</flux:breadcrumbs.item>
            </flux:breadcrumbs>
        </x-slot:breadcrumbs>

        <x-slot:actions>
            <flux:dropdown align="end">
                <flux:button icon="ellipsis-horizontal" :aria-label="__('More options')" />

                <flux:menu>
                    <flux:menu.item icon="pencil" wire:click="edit">{{ __('Edit subject') }}</flux:menu.item>
                    <flux:menu.item
                        icon="trash"
                        variant="danger"
                        wire:click="delete"
                        wire:confirm="{{ __('Delete this subject and all its milestones and tasks?') }}"
                    >
                        {{ __('Delete subject') }}
                    </flux:menu.item>
                </flux:menu>
            </flux:dropdown>
        </x-slot:actions>
    </x-page-header>

    <x-subject-tint
        :subject="$subject"
        aria-hidden="true"
        class="relative h-[200px] overflow-hidden bg-[image:repeating-linear-gradient(0deg,transparent_0_27px,var(--tint-shade)_27px_28px)]"
    >
        <span class="absolute right-[6%] -bottom-[0.18em] font-serif leading-none whitespace-nowrap italic text-(color:--tint-shade) {{ $coverSize }}">{{ $coverWord }}</span>
    </x-subject-tint>

    <div class="mx-auto flex w-full max-w-[1120px] flex-col gap-10 px-4 pb-24 sm:px-10">
        <section class="relative -mt-11 flex flex-col gap-[22px]">
            <div class="flex size-22 items-center justify-center rounded-2xl border border-rule bg-paper shadow-[0_2px_10px_rgba(25,25,23,0.06)]">
                <x-monogram :subject="$subject" size="lg" />
            </div>

            <h1 class="font-serif text-[clamp(44px,5.5vw,68px)] leading-[1.02] tracking-[-0.02em] text-balance">{{ $subject->title }}</h1>

            <dl class="grid max-w-[640px] grid-cols-[minmax(110px,160px)_1fr] gap-x-4 gap-y-0.5 text-sm">
                <dt class="flex min-h-[34px] items-center gap-2 text-muted">
                    <flux:icon.signal variant="outline" class="size-[15px]" />
                    {{ __('Status') }}
                </dt>
                <dd class="flex min-h-[34px] items-center">
                    <span @class([
                        'flex items-center gap-1.5 rounded-full px-2.5 py-0.5 text-[13px] font-medium',
                        'bg-vermilion/10 text-vermilion' => $subject->status === SubjectStatus::Active,
                        'bg-active text-muted' => $subject->status !== SubjectStatus::Active,
                    ])>
                        <span aria-hidden="true" @class([
                            'size-1.5 rounded-full',
                            'bg-vermilion' => $subject->status === SubjectStatus::Active,
                            'bg-muted' => $subject->status !== SubjectStatus::Active,
                        ])></span>
                        {{ __($subject->status->label()) }}
                    </span>
                </dd>

                <dt class="flex min-h-[34px] items-center gap-2 text-muted">
                    <flux:icon.calendar variant="outline" class="size-[15px]" />
                    {{ __('Started') }}
                </dt>
                <dd class="flex min-h-[34px] items-center">
                    <x-local-date :value="$subject->created_at" format="D MMMM YYYY" />
                </dd>

                <dt class="flex min-h-[34px] items-center gap-2 text-muted">
                    <flux:icon.flag variant="outline" class="size-[15px]" />
                    {{ __('Target') }}
                </dt>
                <dd class="flex min-h-[34px] flex-wrap items-center gap-x-2.5">
                    @if ($subject->target_date)
                        {{-- A date, not a timestamp: shown as stored (R1) --}}
                        <span>{{ $subject->target_date->isoFormat('D MMMM YYYY') }}</span>
                        <span class="font-mono text-xs text-muted">
                            @if ($subject->target_days_left > 0)
                                {{ trans_choice(':count day left|:count days left', $subject->target_days_left) }}
                            @elseif ($subject->target_days_left === 0)
                                {{ __('Due today') }}
                            @else
                                {{ trans_choice(':count day over|:count days over', abs($subject->target_days_left)) }}
                            @endif
                        </span>
                    @else
                        <span class="text-muted">{{ __('No date') }}</span>
                    @endif
                </dd>

                <dt class="flex min-h-[34px] items-center gap-2 text-muted">
                    <flux:icon.clock variant="outline" class="size-[15px]" />
                    {{ __('Time logged') }}
                </dt>
                <dd class="flex min-h-[34px] items-center">
                    <x-duration :minutes="$this->minutesLogged" />
                </dd>

                <dt class="flex min-h-[34px] items-center gap-2 text-muted">
                    <flux:icon.chart-bar variant="outline" class="size-[15px]" />
                    {{ __('Progress') }}
                </dt>
                <dd class="flex min-h-[34px] items-center gap-3">
                    @if ($taskTotal > 0)
                        <x-progress-segments
                            :done="$this->milestones->sum('done_tasks_count')"
                            :in-progress="$this->milestones->sum('in_progress_tasks_count')"
                            :total="$taskTotal"
                            class="w-full max-w-[200px]"
                        />
                        <span class="font-mono text-xs whitespace-nowrap text-muted" aria-hidden="true">{{ $this->milestones->sum('done_tasks_count') }} / {{ $taskTotal }}</span>
                    @else
                        <span class="text-muted">{{ __('No tasks yet') }}</span>
                    @endif
                </dd>

                @if ($subject->tags !== [])
                    <dt class="flex min-h-[34px] items-center gap-2 text-muted">
                        <flux:icon.tag variant="outline" class="size-[15px]" />
                        {{ __('Tags') }}
                    </dt>
                    <dd class="flex min-h-[34px] flex-wrap items-center gap-1.5">
                        @foreach ($subject->tags as $tag)
                            <span class="rounded-[5px] bg-active/70 px-2 py-px text-[13px]">{{ $tag }}</span>
                        @endforeach
                    </dd>
                @endif
            </dl>

            @if (filled($subject->description))
                <p class="max-w-[680px] font-serif text-[21px] leading-normal text-pretty text-body">{{ $subject->description }}</p>
            @endif
        </section>

        <section class="flex flex-wrap gap-14 pt-2">
            <div class="flex min-w-0 flex-[2_1_520px] flex-col">
                <div class="flex flex-wrap items-baseline justify-between gap-3 border-b-2 border-ink pb-3.5">
                    <h2 class="font-serif text-[34px] leading-tight">{{ __('The path') }}</h2>

                    @if ($this->milestones->isNotEmpty())
                        <x-eyebrow>
                            {{ trans_choice(':done of :total milestone|:done of :total milestones', $this->milestones->count(), ['done' => $this->doneMilestonesCount, 'total' => $this->milestones->count()]) }}
                        </x-eyebrow>
                    @endif
                </div>

                @if ($this->milestones->isEmpty())
                    <p class="border-b border-rule py-5 text-secondary">{{ __('No milestones yet. Add the first step below.') }}</p>
                @else
                    <ol class="flex flex-col">
                        @foreach ($this->milestones as $milestone)
                            @php
                                $number = sprintf('%02d', $milestone->number);
                                $taskLabel = trans_choice(':done of :total task done|:done of :total tasks done', $milestone->tasks_count, ['done' => $milestone->done_tasks_count, 'total' => $milestone->tasks_count]);
                            @endphp

                            @if ($milestone->id === $this->currentMilestoneId)
                                {{-- The current milestone: the raised card --}}
                                <li wire:key="milestone-{{ $milestone->id }}" class="-mx-3 my-2 rounded-card border border-rule bg-white shadow-[0_1px_3px_rgba(25,25,23,0.05)] sm:-mx-5">
                                    <a
                                        href="{{ route('milestones.show', [$subject, $milestone]) }}"
                                        wire:navigate
                                        class="group flex gap-4 rounded-card px-3 py-[22px] text-ink focus-visible:outline-2 focus-visible:outline-ink sm:gap-5 sm:px-5"
                                    >
                                        <span class="w-10 flex-none font-serif text-[30px] leading-none text-vermilion sm:w-12">{{ $number }}</span>

                                        <div class="flex min-w-0 flex-1 flex-col gap-2.5">
                                            <div class="flex flex-col gap-1">
                                                <span class="font-serif text-[26px] leading-[1.15] group-hover:text-vermilion">{{ $milestone->title }}</span>
                                                @if (filled($milestone->description))
                                                    <span class="text-sm text-secondary">{{ $milestone->description }}</span>
                                                @endif
                                            </div>

                                            @if ($milestone->tasks_count > 0)
                                                <div class="flex items-center gap-3">
                                                    <x-progress-segments
                                                        :done="$milestone->done_tasks_count"
                                                        :in-progress="$milestone->in_progress_tasks_count"
                                                        :total="$milestone->tasks_count"
                                                        class="max-w-[280px] flex-1"
                                                    />
                                                    <span class="font-mono text-xs text-muted" aria-hidden="true">{{ $milestone->done_tasks_count }} / {{ $milestone->tasks_count }}</span>
                                                </div>
                                            @else
                                                <span class="text-sm text-muted">{{ __('No tasks yet') }}</span>
                                            @endif
                                        </div>

                                        <span class="self-start font-mono text-xs whitespace-nowrap text-vermilion">
                                            {{ $milestone->done_tasks_count + $milestone->in_progress_tasks_count > 0 ? __('In progress') : __('Up next') }}
                                        </span>
                                    </a>
                                </li>
                            @else
                                <li wire:key="milestone-{{ $milestone->id }}" class="border-b border-rule">
                                    <a
                                        href="{{ route('milestones.show', [$subject, $milestone]) }}"
                                        wire:navigate
                                        class="group flex gap-4 py-5 focus-visible:outline-2 focus-visible:outline-ink sm:gap-5"
                                    >
                                        <span @class([
                                            'w-10 flex-none font-serif text-[30px] leading-none sm:w-12',
                                            'text-zinc-400' => $milestone->is_done,
                                            'text-ink' => ! $milestone->is_done,
                                        ])>{{ $number }}</span>

                                        <div class="flex min-w-0 flex-1 flex-col gap-1">
                                            <span @class([
                                                'text-[17px] font-medium group-hover:text-vermilion',
                                                'text-muted' => $milestone->is_done,
                                                'text-ink' => ! $milestone->is_done,
                                            ])>{{ $milestone->title }}</span>
                                            @if (filled($milestone->description))
                                                <span @class(['text-sm', 'text-muted' => $milestone->is_done, 'text-secondary' => ! $milestone->is_done])>{{ $milestone->description }}</span>
                                            @endif
                                        </div>

                                        @if ($milestone->is_done)
                                            <span class="flex items-center gap-1.5 self-start font-mono text-xs whitespace-nowrap text-muted">
                                                <flux:icon.check variant="micro" class="size-3.5" />
                                                {{ __('Done') }} · <x-local-date :value="$milestone->completed_at" format="D MMM" />
                                            </span>
                                        @elseif ($milestone->tasks_count > 0)
                                            <span class="self-start font-mono text-xs whitespace-nowrap text-muted" aria-hidden="true">{{ $milestone->done_tasks_count }} / {{ $milestone->tasks_count }}</span>
                                        @else
                                            <span class="self-start font-mono text-xs whitespace-nowrap text-muted">{{ __('No tasks yet') }}</span>
                                        @endif

                                        @if ($milestone->tasks_count > 0)
                                            <span class="sr-only">{{ $taskLabel }}</span>
                                        @endif
                                    </a>
                                </li>
                            @endif
                        @endforeach
                    </ol>
                @endif

                @if ($addingMilestone)
                    <form wire:submit="addMilestone" class="mt-6 flex flex-col gap-4 rounded-card border border-rule bg-white p-5">
                        <x-eyebrow>{{ __('Milestone :number', ['number' => $nextNumber]) }}</x-eyebrow>
                        <flux:input wire:model="milestoneForm.title" :label="__('Title')" required autofocus />
                        <flux:textarea wire:model="milestoneForm.description" :label="__('Description')" rows="2" />

                        <div class="flex gap-2">
                            <flux:button type="submit" variant="primary">{{ __('Add milestone') }}</flux:button>
                            <flux:button type="button" variant="ghost" wire:click="$set('addingMilestone', false)">{{ __('Cancel') }}</flux:button>
                        </div>
                    </form>
                @else
                    <button
                        type="button"
                        wire:click="$set('addingMilestone', true)"
                        class="flex min-h-13 items-center gap-2.5 self-start rounded-button px-1 text-[15px] text-muted hover:text-ink focus-visible:outline-2 focus-visible:outline-ink"
                    >
                        <flux:icon.plus class="size-4" />
                        {{ __('Add milestone :number', ['number' => $nextNumber]) }}
                    </button>
                @endif
            </div>

            <aside class="flex min-w-0 flex-[1_1_260px] flex-col gap-12">
                @if ($this->recentNotes->isNotEmpty())
                    <section class="flex flex-col gap-1" aria-labelledby="margins-heading">
                        <h2 id="margins-heading" class="border-b border-ink pb-3">
                            <x-eyebrow>{{ __('In the margins') }}</x-eyebrow>
                        </h2>

                        @foreach ($this->recentNotes as $note)
                            <a
                                href="{{ route('milestones.show', [$subject, $note->milestone]) }}"
                                wire:navigate
                                wire:key="margin-note-{{ $note->id }}"
                                class="group flex flex-col gap-1.5 border-b border-rule py-4 focus-visible:outline-2 focus-visible:outline-ink"
                            >
                                <span class="font-mono text-[11px] text-muted uppercase">
                                    <x-local-date :value="$note->created_at" format="ddd D MMM" /> · {{ sprintf('%02d', $note->milestone->number) }}
                                </span>
                                <p class="line-clamp-5 font-serif text-[17px] leading-[1.45] text-ink group-hover:text-vermilion">{{ $note->body }}</p>
                            </a>
                        @endforeach

                        <a href="{{ route('notes.index') }}" wire:navigate class="self-start pt-3 text-sm text-muted underline underline-offset-4 hover:text-ink">
                            {{ __('All field notes') }}
                        </a>
                    </section>
                @endif

                <livewire:subjects.sources :subject="$subject" :key="'sources-'.$subject->id" />
            </aside>
        </section>
    </div>

    <flux:modal wire:model="editing" @close="cancelEdit" class="w-full max-w-md">
        <form wire:submit="update" class="space-y-6">
            <flux:heading size="lg">{{ __('Edit subject') }}</flux:heading>

            <flux:input wire:model="form.title" :label="__('Title')" required />
            <flux:textarea wire:model="form.description" :label="__('Description')" rows="3" />

            <div class="grid gap-6 sm:grid-cols-2">
                <flux:select wire:model="form.status" :label="__('Status')">
                    @foreach (SubjectStatus::cases() as $status)
                        <flux:select.option :value="$status->value">{{ __($status->label()) }}</flux:select.option>
                    @endforeach
                </flux:select>

                <flux:input wire:model="form.target_date" :label="__('Target date')" type="date" />
            </div>

            <flux:input
                wire:model="form.tags"
                :label="__('Tags')"
                description:trailing="{{ __('Separate tags with commas, up to 5.') }}"
                placeholder="backend, portfolio"
            />

            <x-colour-picker wire:model="form.colour" />

            <div class="flex justify-end gap-2">
                <flux:modal.close>
                    <flux:button variant="ghost">{{ __('Cancel') }}</flux:button>
                </flux:modal.close>
                <flux:button type="submit" variant="primary">{{ __('Save') }}</flux:button>
            </div>
        </form>
    </flux:modal>
</div>
