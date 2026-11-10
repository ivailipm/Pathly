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
        </section>

        @if ($this->subjects->isEmpty())
            <p class="font-serif text-xl text-secondary">{{ __('No subjects yet. Start your first one.') }}</p>
        @endif

        <section class="grid grid-cols-[repeat(auto-fit,minmax(min(240px,100%),1fr))] gap-6">
            @foreach ($this->subjects as $subject)
                @php($current = $subject->current_milestone)

                <a
                    href="{{ route('subjects.show', $subject) }}"
                    wire:navigate
                    wire:key="subject-{{ $subject->id }}"
                    class="group flex flex-col overflow-hidden rounded-card border border-rule bg-white text-ink hover:border-control focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ink"
                >
                    <x-subject-tint :subject="$subject" class="relative flex h-42 items-end overflow-hidden p-4">
                        <span aria-hidden="true" class="absolute -top-6.5 -right-1.5 font-serif text-[180px] leading-none italic text-(color:--tint-shade)">{{ mb_strtoupper(mb_substr(trim($subject->title), 0, 1)) }}</span>
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

            <button
                type="button"
                wire:click="$set('creating', true)"
                class="flex min-h-[300px] flex-col items-center justify-center gap-2.5 rounded-card border border-dashed border-zinc-300 p-6 text-sm text-muted hover:border-zinc-400 hover:text-ink focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ink"
            >
                <flux:icon.plus class="size-5.5" />
                <span class="font-serif text-xl text-ink">{{ __('Start a new subject') }}</span>
                <span>{{ __('Name it, then sketch its path') }}</span>
            </button>
        </section>
    </div>

    <flux:modal wire:model="creating" class="w-full max-w-md">
        <form wire:submit="save" class="space-y-6">
            <div>
                <flux:heading size="lg">{{ __('New subject') }}</flux:heading>
                <flux:text class="mt-1">{{ __('Name it now; you can sketch its milestones next.') }}</flux:text>
            </div>

            <flux:input wire:model="form.title" :label="__('Title')" required autofocus />
            <flux:textarea wire:model="form.description" :label="__('Description')" rows="3" />

            <div class="flex justify-end gap-2">
                <flux:modal.close>
                    <flux:button variant="ghost">{{ __('Cancel') }}</flux:button>
                </flux:modal.close>
                <flux:button type="submit" variant="primary">{{ __('Create subject') }}</flux:button>
            </div>
        </form>
    </flux:modal>
</div>
