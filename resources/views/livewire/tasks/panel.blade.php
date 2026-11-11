@use('App\Enums\TaskStatus')
@use('App\Enums\TaskType')

@php
    // Read once: Livewire doesn't cache a computed property that returns null, so each read would query again
    $task = $this->task;
@endphp

<div>
    <flux:modal wire:model="open" variant="flyout" @close="close" class="w-full md:w-[480px]">
        @if ($task === null)
            <p class="pt-8 text-secondary">{{ __('This task was deleted.') }}</p>
        @elseif ($editing)
            <form wire:submit="save" class="flex flex-col gap-5">
                <flux:heading size="lg">{{ __('Edit task') }}</flux:heading>

                <flux:input wire:model="form.title" :label="__('Title')" required />
                <flux:input wire:model="form.url" :label="__('Link')" type="url" placeholder="https://" />
                <flux:select wire:model="form.type" :label="__('Type')">
                    <flux:select.option value="">{{ __('No type') }}</flux:select.option>
                    @foreach (TaskType::cases() as $type)
                        <flux:select.option :value="$type->value">{{ __($type->label()) }}</flux:select.option>
                    @endforeach
                </flux:select>
                <flux:textarea
                    wire:model="form.description"
                    :label="__('Description')"
                    rows="7"
                    :placeholder="__('What to do, page numbers, where you stopped…')"
                />

                <div class="flex justify-end gap-2">
                    <flux:button type="button" variant="ghost" wire:click="cancelEdit">{{ __('Cancel') }}</flux:button>
                    <flux:button type="submit" variant="primary">{{ __('Save') }}</flux:button>
                </div>
            </form>
        @else
            <article class="flex flex-col gap-8">
                {{-- pe-8 keeps clear of the flyout's close button --}}
                <header class="flex flex-col gap-3 pe-8">
                    <a
                        href="{{ route('milestones.show', [$subject, $task->milestone]) }}"
                        wire:navigate
                        class="self-start font-mono text-[11px] tracking-label text-muted uppercase hover:text-ink focus-visible:outline-2 focus-visible:outline-ink"
                    >
                        {{ sprintf('%02d', $task->milestone->number) }} · {{ $task->milestone->title }}
                    </a>

                    <h2 class="font-serif text-[30px] leading-[1.12] text-balance text-ink">{{ $task->title }}</h2>

                    @if ($task->type || (int) $task->minutes_logged > 0)
                        <p class="flex flex-wrap items-center gap-x-2 font-mono text-xs text-muted">
                            @if ($task->type)
                                <span class="tracking-label uppercase">{{ __($task->type->label()) }}</span>
                            @endif
                            @if ($task->type && (int) $task->minutes_logged > 0)
                                <span aria-hidden="true">·</span>
                            @endif
                            @if ((int) $task->minutes_logged > 0)
                                <span><x-duration :minutes="(int) $task->minutes_logged" /> {{ __('logged') }}</span>
                            @endif
                        </p>
                    @endif

                    @if ($task->url)
                        <a
                            href="{{ $task->url }}"
                            target="_blank"
                            rel="noopener noreferrer"
                            class="flex max-w-full min-w-0 items-center gap-1.5 self-start font-mono text-xs text-secondary hover:text-ink"
                        >
                            <flux:icon.link variant="micro" class="size-[13px] flex-none" />
                            <span class="min-w-0 truncate">{{ $task->display_url }}</span>
                            <span class="sr-only">{{ __('(opens in a new tab)') }}</span>
                        </a>
                    @endif
                </header>

                <div role="group" aria-label="{{ __('Status') }}" class="grid grid-cols-3 gap-1 rounded-button bg-sand p-1">
                    @foreach (TaskStatus::cases() as $status)
                        <button
                            type="button"
                            wire:click="move('{{ $status->value }}')"
                            aria-pressed="{{ $task->status === $status ? 'true' : 'false' }}"
                            @class([
                                'min-h-10 rounded-[6px] px-2 text-sm focus-visible:outline-2 focus-visible:outline-ink',
                                'bg-white font-medium text-ink shadow-[0_1px_2px_rgba(25,25,23,0.08)]' => $task->status === $status,
                                'text-secondary hover:text-ink' => $task->status !== $status,
                            ])
                        >{{ __($status->label()) }}</button>
                    @endforeach
                </div>

                <section class="flex flex-col gap-2.5" aria-labelledby="task-description-heading">
                    <h3 id="task-description-heading"><x-eyebrow>{{ __('Description') }}</x-eyebrow></h3>

                    @if (filled($task->description))
                        <p class="text-[15px] leading-relaxed whitespace-pre-line text-body">{{ $task->description }}</p>
                    @else
                        <button type="button" wire:click="edit" class="self-start text-left text-sm text-muted underline-offset-4 hover:text-ink hover:underline">
                            {{ __('Add a description: what to do, page numbers, where you stopped.') }}
                        </button>
                    @endif
                </section>

                <section class="flex flex-col" aria-labelledby="task-notes-heading">
                    <div class="flex items-baseline justify-between border-b border-ink pb-2.5">
                        <h3 id="task-notes-heading"><x-eyebrow>{{ __('Field notes') }}</x-eyebrow></h3>
                        <span class="font-mono text-xs text-muted">{{ $this->notes->count() }}</span>
                    </div>

                    @forelse ($this->notes as $note)
                        <div wire:key="panel-note-{{ $note->id }}" class="flex flex-col gap-1.5 border-b border-rule py-4">
                            <x-local-date :value="$note->created_at" format="ddd D MMM" class="font-mono text-[11px] text-muted uppercase" />
                            <p class="font-serif text-[17px] leading-[1.45] whitespace-pre-line text-ink">{{ $note->body }}</p>
                        </div>
                    @empty
                        <p class="py-4 text-sm text-muted">{{ __('No field notes on this task yet.') }}</p>
                    @endforelse
                </section>

                <footer class="flex flex-wrap items-center gap-2">
                    <flux:button icon="pencil" wire:click="edit">{{ __('Edit') }}</flux:button>

                    @if ($task->url !== null && ! $this->linkInSources)
                        <flux:button icon="bookmark" wire:click="pinSource">{{ __('Add link to sources') }}</flux:button>
                    @endif

                    <flux:spacer />

                    <flux:button
                        variant="ghost"
                        icon="trash"
                        wire:click="delete"
                        wire:confirm="{{ __('Delete this task?') }}"
                        class="text-red-700!"
                    >
                        {{ __('Delete') }}
                    </flux:button>
                </footer>
            </article>
        @endif
    </flux:modal>
</div>
