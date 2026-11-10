@php
    // Read once: Livewire doesn't cache a computed property that returns null, so each read would query again
    $now = $this->nowStudying;
    $upNext = $this->upNext;
    $latestNote = $this->latestNote;
    $deskCount = $this->deskCount;

    // "2 tasks are on your desk, and <em>Reverb</em> is 3 steps from done. Pick up where you left off."
    $summary = e($deskCount > 0
        ? trans_choice(':count task is on your desk|:count tasks are on your desk', $deskCount)
        : __('Nothing is on your desk yet'));

    if ($now !== null) {
        $summary .= ', '.e(__('and')).' <em>'.e($now['milestone']->title).'</em> '.e(match (true) {
            $now['total'] === 0 => __('has no tasks yet.'),
            $this->remainingTasks === 0 => __('is ready to finish.'),
            default => trans_choice('is :count step from done.|is :count steps from done.', $this->remainingTasks),
        });
    } else {
        $summary .= '.';
    }

    if ($upNext !== null) {
        $summary .= ' '.e(__('Pick up where you left off.'));
    } elseif ($deskCount === 0) {
        $summary .= ' '.e(__('Pick a task to start.'));
    }

    $chartLabel = __('Minutes studied:').' '.collect($this->week)
        ->reject(fn (array $day) => $day['is_future'])
        ->map(fn (array $day) => $day['date']->isoFormat('ddd').' '.$day['minutes'].($day['is_today'] ? ' '.__('so far') : ''))
        ->implode(', ');
@endphp

<div>
    <x-page-header>
        {{ __('Today') }}

        <x-slot:actions>
            <flux:modal.trigger name="log-session">
                <flux:button variant="primary" icon="clock">{{ __('Log a session') }}</flux:button>
            </flux:modal.trigger>
        </x-slot:actions>
    </x-page-header>

    <div class="mx-auto flex w-full max-w-[1120px] flex-col gap-14 px-4 pt-12 pb-24 sm:px-10">
        {{-- The date, the greeting and what the day looks like --}}
        <section class="flex flex-col gap-5">
            <div class="flex flex-wrap justify-between gap-2 border-b-2 border-ink pb-2.5 font-mono text-xs tracking-label uppercase">
                <x-local-date :value="now()" format="dddd, D MMMM YYYY" />
                <span>
                    {{ __('Week :number', ['number' => $this->weekNumber]) }}
                    @if ($this->streakIncludesToday)
                        · {{ __('Day :count of your streak', ['count' => $this->streak]) }}
                    @elseif ($this->streak > 0)
                        · {{ __(':count-day streak, study today to keep it', ['count' => $this->streak]) }}
                    @endif
                </span>
            </div>

            <h1 class="mt-2 font-serif text-[clamp(44px,6vw,76px)] leading-[1.02] tracking-[-0.02em] text-balance">
                {{ $this->greeting }}, <em>{{ $this->firstName }}.</em>
            </h1>

            {{-- Every piece is escaped with e(); only the <em> around the milestone title is markup --}}
            <p class="max-w-[680px] font-serif text-[22px] leading-[1.4] text-pretty text-secondary">{!! $summary !!}</p>
        </section>

        {{-- Now studying, and the desk --}}
        <section class="flex flex-wrap gap-12">
            <article class="flex min-w-0 flex-[2_1_520px] flex-col gap-[18px]">
                @if ($now)
                    <x-eyebrow accent>{{ __('Now studying') }} · {{ $now['subject']->title }}</x-eyebrow>

                    <h2 class="font-serif text-[40px] leading-[1.05] tracking-[-0.015em] text-balance sm:text-5xl">
                        <a href="{{ route('milestones.show', [$now['subject'], $now['milestone']]) }}" wire:navigate class="hover:text-vermilion focus-visible:outline-2 focus-visible:outline-ink">{{ $now['milestone']->title }}</a>
                    </h2>

                    @if (filled($now['milestone']->description))
                        <p class="max-w-[560px] text-base text-pretty text-secondary">{{ $now['milestone']->description }}</p>
                    @endif

                    @if ($now['total'] > 0)
                        <div class="flex items-center gap-3.5">
                            <x-progress-segments
                                :done="$now['done']"
                                :in-progress="$now['in_progress']"
                                :total="$now['total']"
                                class="max-w-[360px] flex-1"
                            />
                            <span class="font-mono text-xs whitespace-nowrap text-muted" aria-hidden="true">{{ __(':done / :total done', ['done' => $now['done'], 'total' => $now['total']]) }}</span>
                        </div>
                    @endif

                    <div class="mt-2 flex flex-wrap items-center justify-between gap-5 rounded-card border border-rule bg-white px-6 py-[22px]">
                        @if ($upNext)
                            <div class="flex min-w-0 flex-col gap-1.5">
                                <x-eyebrow size="sm">
                                    {{ __('Up next') }}@if ($upNext->type) · {{ __($upNext->type->label()) }}@endif
                                    @if ((int) $upNext->minutes_logged > 0)
                                        · <x-duration :minutes="$upNext->minutes_logged" /> {{ __('logged') }}
                                    @endif
                                </x-eyebrow>
                                <span class="font-serif text-2xl leading-tight">{{ $upNext->title }}</span>
                            </div>

                            <a
                                href="{{ route('milestones.show', [$now['subject'], $now['milestone']]) }}"
                                wire:navigate
                                class="flex min-h-11 items-center gap-2 rounded-button bg-ink px-[18px] text-sm font-medium text-paper hover:bg-zinc-800 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ink"
                            >
                                {{ __('Continue') }}
                                <flux:icon.arrow-right class="size-[15px]" />
                            </a>
                        @else
                            <p class="text-secondary">{{ __('No tasks yet. Add some to this milestone.') }}</p>

                            <a
                                href="{{ route('milestones.show', [$now['subject'], $now['milestone']]) }}"
                                wire:navigate
                                class="flex min-h-11 items-center gap-2 rounded-button border border-control px-[18px] text-sm font-medium hover:bg-white focus-visible:outline-2 focus-visible:outline-ink"
                            >
                                {{ __('Open milestone') }}
                            </a>
                        @endif
                    </div>
                @else
                    <x-eyebrow>{{ __('Now studying') }}</x-eyebrow>
                    <p class="font-serif text-2xl text-secondary">
                        <a href="{{ route('subjects.index') }}" wire:navigate class="underline underline-offset-4 hover:text-ink">{{ __('Start a subject in your library.') }}</a>
                    </p>
                @endif
            </article>

            <aside class="flex min-w-0 flex-[1_1_280px] flex-col gap-1 border-rule sm:border-l sm:ps-7" aria-labelledby="desk-heading">
                <h2 id="desk-heading" class="pb-2.5"><x-eyebrow>{{ __('On your desk') }}</x-eyebrow></h2>

                @if ($this->desk->isEmpty())
                    <p class="border-t border-rule py-3.5 text-sm text-muted">{{ __('Nothing in progress.') }}</p>
                @else
                    <ol class="flex flex-col border-b border-rule">
                        @foreach ($this->desk as $task)
                            <li wire:key="desk-{{ $task->id }}" class="border-t border-rule">
                                <a
                                    href="{{ route('milestones.show', [$task->milestone->subject, $task->milestone]) }}"
                                    wire:navigate
                                    class="group flex gap-3.5 py-3.5 text-ink focus-visible:outline-2 focus-visible:outline-ink"
                                >
                                    <span class="font-serif text-[22px] leading-none text-vermilion">{{ $loop->iteration }}</span>
                                    <span class="flex min-w-0 flex-col gap-1">
                                        <span class="text-[15px] leading-[1.35] font-medium group-hover:text-vermilion">{{ $task->title }}</span>
                                        <span class="truncate font-mono text-xs text-muted">
                                            @if ($task->display_url)
                                                {{ $task->display_url }}
                                            @else
                                                {{ $task->milestone->subject->title }}@if ($task->type) · {{ __($task->type->label()) }}@endif
                                            @endif
                                        </span>
                                    </span>
                                </a>
                            </li>
                        @endforeach
                    </ol>

                    @if ($deskCount > $this->desk->count())
                        <span class="pt-2 font-mono text-xs text-muted">{{ trans_choice('and :count more in progress|and :count more in progress', $deskCount - $this->desk->count()) }}</span>
                    @endif
                @endif
            </aside>
        </section>

        {{-- This week, and the latest field note --}}
        <section class="flex flex-wrap gap-12 border-t border-rule pt-8">
            <div class="flex min-w-0 flex-[1_1_380px] flex-col gap-[18px]">
                <div class="flex items-baseline justify-between gap-3">
                    <h2><x-eyebrow>{{ __('This week') }}</x-eyebrow></h2>
                    <x-duration :minutes="$this->weekMinutes" class="font-serif text-[28px]" />
                </div>

                <div role="img" aria-label="{{ $chartLabel }}" class="grid h-[140px] grid-cols-7 items-end gap-2.5">
                    @foreach ($this->week as $day)
                        <div class="flex h-full flex-col justify-end">
                            @if ($day['is_future'])
                                <div class="h-full rounded-[3px] border border-dashed border-zinc-300"></div>
                            @elseif ($day['minutes'] === 0)
                                <div @class(['h-0.5 rounded-[1px]', 'bg-vermilion' => $day['is_today'], 'bg-zinc-300' => ! $day['is_today']])></div>
                            @else
                                <div
                                    @class(['rounded-[3px]', 'bg-vermilion' => $day['is_today'], 'bg-ink' => ! $day['is_today']])
                                    style="height: {{ max(2, $day['percent']) }}%"
                                ></div>
                            @endif
                        </div>
                    @endforeach
                </div>

                <div class="grid grid-cols-7 gap-2.5 text-center font-mono text-[11px] text-muted" aria-hidden="true">
                    @foreach ($this->week as $day)
                        <span @class(['font-medium text-vermilion' => $day['is_today']])>{{ $day['date']->isoFormat('ddd') }}</span>
                    @endforeach
                </div>
            </div>

            @if ($latestNote)
                <figure class="flex min-w-0 flex-[1_1_380px] flex-col justify-center gap-4">
                    <x-eyebrow>{{ __('From your field notes') }}</x-eyebrow>
                    <blockquote class="line-clamp-6 font-serif text-[26px] leading-tight tracking-[-0.01em] text-pretty italic sm:text-[30px]">“{{ $latestNote->body }}”</blockquote>
                    <figcaption class="font-mono text-xs text-muted">
                        <x-local-date :value="$latestNote->created_at" format="ddd D MMM" />
                        ·
                        <a href="{{ route('milestones.show', [$latestNote->milestone->subject, $latestNote->milestone]) }}" wire:navigate class="underline underline-offset-4 hover:text-ink">{{ $latestNote->milestone->title }}</a>
                    </figcaption>
                </figure>
            @endif
        </section>

        {{-- The Library table --}}
        <section class="flex flex-col gap-3.5">
            <div class="flex flex-wrap items-baseline justify-between gap-3">
                <h2 class="font-serif text-[30px]">{{ __('Across your subjects') }}</h2>
                <a href="{{ route('subjects.index') }}" wire:navigate class="text-sm text-muted underline underline-offset-4 hover:text-ink">{{ __('Open library') }}</a>
            </div>

            @if ($this->subjects->isNotEmpty())
                <x-subjects-table :subjects="$this->subjects" />
            @else
                <p class="border-t border-ink pt-4 text-sm text-muted">{{ __('No subjects yet.') }}</p>
            @endif
        </section>
    </div>

    <livewire:sessions.log-session />
</div>
