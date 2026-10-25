@props([
    'subject',
    'as' => 'div',
])

@php
    // R8, and the only place that knows how a subject maps to its tint. Step C reads $subject->colour here instead.
    $tint = ['clay', 'slate', 'moss', 'plum'][($subject->id - 1) % 4];

    // Written out in full so Tailwind compiles them. Children draw large decorative letters and
    // ruled lines in the shade with text-(color:--tint-shade).
    $classes = match ($tint) {
        'clay' => 'bg-clay text-clay-ink [--tint-shade:var(--color-clay-shade)]',
        'slate' => 'bg-slate text-slate-ink [--tint-shade:var(--color-slate-shade)]',
        'moss' => 'bg-moss text-moss-ink [--tint-shade:var(--color-moss-shade)]',
        'plum' => 'bg-plum text-plum-ink [--tint-shade:var(--color-plum-shade)]',
    };
@endphp

<{{ $as }} {{ $attributes->class($classes) }}>{{ $slot }}</{{ $as }}>
