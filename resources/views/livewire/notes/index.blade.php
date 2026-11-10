<div>
    <x-page-header>{{ __('Field notes') }}</x-page-header>

    <div class="mx-auto flex w-full max-w-[920px] flex-col gap-10 px-4 pt-12 pb-24 sm:px-10">
        <section class="flex flex-col gap-2 border-b-2 border-ink pb-5">
            <x-eyebrow>{{ trans_choice(':count note|:count notes', $this->notes->total()) }}</x-eyebrow>
            <h1 class="font-serif text-6xl leading-none tracking-[-0.025em] sm:text-[72px]">{{ __('Field notes') }}</h1>
        </section>

        @if ($this->notes->isEmpty())
            <p class="font-serif text-xl text-secondary">{{ __('No field notes yet. Add one from a milestone\'s board.') }}</p>
        @else
            <div class="-mt-7 flex flex-col">
                @foreach ($this->notes as $note)
                    <x-note-row :note="$note" context wire:key="note-{{ $note->id }}" />
                @endforeach
            </div>

            <flux:pagination :paginator="$this->notes" />
        @endif
    </div>
</div>
