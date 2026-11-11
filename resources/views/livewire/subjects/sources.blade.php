@use('App\Support\Link')

<section class="flex flex-col gap-1" aria-labelledby="sources-heading">
    <h2 id="sources-heading" class="border-b border-ink pb-3">
        <x-eyebrow>{{ __('Sources') }}</x-eyebrow>
    </h2>

    @forelse ($this->sources as $source)
        @if ($editingId === $source->id)
            <div wire:key="source-{{ $source->id }}" class="py-3">
                @include('livewire.subjects.partials.source-form', ['submitLabel' => __('Save')])
            </div>
        @else
            <div wire:key="source-{{ $source->id }}" class="flex items-start gap-3 border-b border-rule py-3">
                <span class="mt-0.5 flex size-7 flex-none items-center justify-center rounded-md bg-sand text-muted" aria-hidden="true">
                    @if ($source->url !== null)
                        <flux:icon.link variant="micro" class="size-3.5" />
                    @else
                        <flux:icon.book-open variant="micro" class="size-3.5" />
                    @endif
                </span>

                <div class="flex min-w-0 flex-1 flex-col gap-0.5">
                    @if ($source->url !== null)
                        <a
                            href="{{ $source->url }}"
                            target="_blank"
                            rel="noopener noreferrer"
                            class="text-[15px] leading-snug font-medium break-words text-ink hover:text-vermilion focus-visible:outline-2 focus-visible:outline-ink"
                        >
                            {{ $source->title }}<span class="sr-only"> {{ __('(opens in a new tab)') }}</span>
                        </a>
                        <span class="truncate font-mono text-xs text-muted">{{ Link::host($source->url) }}</span>
                    @else
                        <span class="text-[15px] leading-snug font-medium break-words text-ink">{{ $source->title }}</span>
                        <span class="font-mono text-xs text-muted">{{ __('No link') }}</span>
                    @endif
                </div>

                <flux:dropdown align="end">
                    <flux:button variant="ghost" size="sm" icon="ellipsis-horizontal" :aria-label="__('Actions for :title', ['title' => $source->title])" class="text-muted!" />

                    <flux:menu>
                        <flux:menu.item icon="pencil" wire:click="edit({{ $source->id }})">{{ __('Edit') }}</flux:menu.item>
                        <flux:menu.item
                            icon="trash"
                            variant="danger"
                            wire:click="remove({{ $source->id }})"
                            wire:confirm="{{ __('Remove this source?') }}"
                        >
                            {{ __('Remove') }}
                        </flux:menu.item>
                    </flux:menu>
                </flux:dropdown>
            </div>
        @endif
    @empty
        <p class="border-b border-rule py-4 text-sm text-secondary">{{ __('The docs, courses and books you learn this from.') }}</p>
    @endforelse

    @if ($adding)
        <div class="pt-3">
            @include('livewire.subjects.partials.source-form', ['submitLabel' => __('Add source')])
        </div>
    @else
        <button
            type="button"
            wire:click="add"
            class="flex min-h-11 items-center gap-2 self-start rounded-button px-1 text-sm text-muted hover:text-ink focus-visible:outline-2 focus-visible:outline-ink"
        >
            <flux:icon.plus class="size-[15px]" />
            {{ __('Add source') }}
        </button>
    @endif
</section>
