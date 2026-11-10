@props([
    'note',
    // Show the subject and milestone the note belongs to (the Field notes page)
    'context' => false,
])

<article {{ $attributes->class('grid grid-cols-[64px_1fr] gap-4 border-b border-rule py-7 sm:grid-cols-[96px_1fr] sm:gap-5') }}>
    <span class="font-mono text-xs leading-relaxed text-muted uppercase">
        <x-local-date :value="$note->created_at" format="ddd" class="block" />
        <x-local-date :value="$note->created_at" format="D MMM" class="block" />
    </span>

    <div class="flex min-w-0 flex-col gap-2.5">
        <p class="font-serif text-xl leading-normal text-pretty break-words whitespace-pre-line text-ink">{{ $note->body }}</p>

        @if ($context)
            <span class="text-[13px] text-muted">
                <a href="{{ route('subjects.show', $note->milestone->subject) }}" wire:navigate class="hover:text-ink">{{ $note->milestone->subject->title }}</a>
                ·
                <a href="{{ route('milestones.show', [$note->milestone->subject, $note->milestone]) }}" wire:navigate class="hover:text-ink">
                    {{ sprintf('%02d', $note->milestone->number) }} {{ $note->milestone->title }}
                </a>
            </span>
        @endif

        @if ($note->task)
            <span class="text-[13px] text-muted">
                {{ __('On') }} <span class="text-body">{{ $note->task->title }}</span>@if ($note->minutes > 0) · <x-duration :minutes="$note->minutes" />@endif
            </span>
        @endif
    </div>
</article>
