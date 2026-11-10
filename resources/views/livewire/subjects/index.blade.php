@use('App\Enums\SubjectStatus')

<div>
    <x-page-header>
        {{ __('Library') }}

        <x-slot:actions>
            <flux:button variant="primary" icon="plus" wire:click="$set('creating', true)">{{ __('New subject') }}</flux:button>
        </x-slot:actions>
    </x-page-header>

    <div class="mx-auto flex w-full max-w-[1120px] flex-col gap-10 px-4 pt-12 pb-24 sm:px-10">
        <section class="flex flex-wrap items-end justify-between gap-6 border-b-2 border-ink pb-5">
            <div class="flex flex-col gap-2">
                <x-eyebrow>
                    {{ trans_choice(':count subject|:count subjects', $this->subjects->count()) }}
                    · {{ trans_choice(':count milestone|:count milestones', $this->milestoneTotal) }}
                </x-eyebrow>
                <h1 class="font-serif text-6xl leading-none tracking-[-0.025em] sm:text-[72px]">{{ __('Library') }}</h1>
            </div>

            <div role="group" aria-label="{{ __('View') }}" class="flex gap-0.5 rounded-[9px] bg-sand p-[3px]">
                @foreach (['gallery' => __('Gallery'), 'table' => __('Table'), 'archive' => __('Archive')] as $value => $label)
                    <button
                        type="button"
                        wire:click="$set('view', '{{ $value }}')"
                        aria-pressed="{{ $view === $value ? 'true' : 'false' }}"
                        @class([
                            'min-h-10 rounded-[7px] px-3.5 text-[13px] focus-visible:outline-2 focus-visible:outline-ink',
                            'bg-white font-medium text-ink shadow-[0_1px_2px_rgba(25,25,23,0.08)]' => $view === $value,
                            'text-muted hover:text-ink' => $view !== $value,
                        ])
                    >{{ $label }}</button>
                @endforeach
            </div>
        </section>

        @if ($this->subjects->isEmpty())
            <p class="font-serif text-xl text-secondary">
                {{ $view === 'archive' ? __('No archived subjects.') : __('No subjects yet. Start your first one.') }}
            </p>
        @endif

        @if ($view === 'table')
            @if ($this->subjects->isNotEmpty())
                <x-subjects-table :subjects="$this->subjects" />
            @endif
        @else
            <section class="grid grid-cols-[repeat(auto-fit,minmax(min(240px,100%),1fr))] gap-6">
                @foreach ($this->subjects as $subject)
                    @php
                        $current = $subject->current_milestone;
                    @endphp

                    <a
                        href="{{ route('subjects.show', $subject) }}"
                        wire:navigate
                        wire:key="subject-{{ $subject->id }}"
                        class="group flex flex-col overflow-hidden rounded-card border border-rule bg-white text-ink hover:border-control focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ink"
                    >
                        <x-subject-tint :subject="$subject" class="relative flex h-42 items-end overflow-hidden p-4">
                            <span aria-hidden="true" class="absolute -top-6.5 -right-1.5 font-serif text-[180px] leading-none italic text-(color:--tint-shade)">{{ mb_strtoupper(mb_substr(trim($subject->title), 0, 1)) }}</span>

                            @php
                                $studied = match ($subject->days_since_studied) {
                                    null => __('Not started'),
                                    0 => __('Studied today'),
                                    1 => __('Yesterday'),
                                    default => trans_choice(':count day ago|:count days ago', $subject->days_since_studied),
                                };
                            @endphp

                            <span class="relative font-mono text-[11px] tracking-label uppercase">
                                @if ($subject->status !== SubjectStatus::Active)
                                    {{ __($subject->status->label()) }} ·
                                @endif
                                {{ $studied }}
                            </span>
                        </x-subject-tint>

                        <div class="flex flex-1 flex-col gap-2.5 p-[18px]">
                            <span class="font-serif text-2xl leading-[1.15] group-hover:text-vermilion">{{ $subject->title }}</span>

                            <span class="text-[13px] text-secondary">
                                @if ($subject->milestones_count === 0)
                                    {{ __('No milestones yet') }}
                                @elseif ($current === null)
                                    {{ __('All milestones done') }}
                                @else
                                    {{ $subject->is_started ? __('Now on') : __('First up') }}
                                    <em class="font-serif text-[15px]">{{ $current->title }}</em>
                                @endif
                            </span>

                            <x-progress-line :value="$subject->done_milestones_count" :max="$subject->milestones_count" class="mt-auto" />

                            @if ($subject->tasks_count > 0)
                                <x-progress-segments
                                    :done="$subject->done_tasks_count"
                                    :in-progress="$subject->in_progress_tasks_count"
                                    :total="$subject->tasks_count"
                                />
                            @else
                                <span class="font-mono text-xs text-muted">{{ __('No tasks yet') }}</span>
                            @endif
                        </div>
                    </a>
                @endforeach

                @if ($view === 'gallery')
                    <button
                        type="button"
                        wire:click="$set('creating', true)"
                        class="flex min-h-[300px] flex-col items-center justify-center gap-2.5 rounded-card border border-dashed border-zinc-300 p-6 text-sm text-muted hover:border-zinc-400 hover:text-ink focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ink"
                    >
                        <flux:icon.plus class="size-5.5" />
                        <span class="font-serif text-xl text-ink">{{ __('Start a new subject') }}</span>
                        <span>{{ __('Name it, then sketch its path') }}</span>
                    </button>
                @endif
            </section>
        @endif

        @if ($view !== 'archive' && $this->recentlyFinished->isNotEmpty())
            <section class="flex flex-wrap gap-12 border-t border-rule pt-8">
                <div class="flex flex-[1_1_220px] flex-col gap-2">
                    <x-eyebrow>{{ __('Recently finished') }}</x-eyebrow>
                    <p class="font-serif text-xl leading-[1.35] text-secondary">{{ __('Milestones you closed this month, newest first.') }}</p>
                </div>

                <ol class="flex min-w-0 flex-[3_1_520px] flex-col border-b border-rule">
                    @foreach ($this->recentlyFinished as $finished)
                        <li wire:key="finished-{{ $finished->id }}" class="flex flex-wrap items-baseline gap-x-4 gap-y-1.5 border-t border-rule py-4">
                            <a
                                href="{{ route('milestones.show', [$finished->subject, $finished]) }}"
                                wire:navigate
                                class="flex-[1_1_260px] font-serif text-[22px] leading-snug text-ink hover:text-vermilion focus-visible:outline-2 focus-visible:outline-ink"
                            >{{ $finished->title }}</a>
                            <span class="text-[13px] text-muted">{{ $finished->subject->title }} · {{ sprintf('%02d', $finished->number) }}</span>
                            <x-local-date :value="$finished->completed_at" format="D MMM" class="min-w-14 text-right font-mono text-xs text-muted" />
                        </li>
                    @endforeach
                </ol>
            </section>
        @endif
    </div>

    <flux:modal wire:model="creating" class="w-full max-w-md">
        <form wire:submit="save" class="space-y-6">
            <div>
                <flux:heading size="lg">{{ __('New subject') }}</flux:heading>
                <flux:text class="mt-1">{{ __('Name it now; you can sketch its milestones next.') }}</flux:text>
            </div>

            <flux:input wire:model="form.title" :label="__('Title')" required autofocus />
            <flux:textarea wire:model="form.description" :label="__('Description')" rows="3" />
            <x-colour-picker wire:model="form.colour" />

            <div class="flex justify-end gap-2">
                <flux:modal.close>
                    <flux:button variant="ghost">{{ __('Cancel') }}</flux:button>
                </flux:modal.close>
                <flux:button type="submit" variant="primary">{{ __('Create subject') }}</flux:button>
            </div>
        </form>
    </flux:modal>
</div>
