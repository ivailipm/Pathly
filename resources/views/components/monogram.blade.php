@props([
    'subject',
    'size' => 'sm',
])

@php
    $letter = mb_strtoupper(mb_substr(trim($subject->title), 0, 1));

    $classes = match ($size) {
        'sm' => 'size-5 rounded-[5px] text-[13px] font-medium',
        'md' => 'size-[22px] rounded-[5px] text-sm',
        'lg' => 'size-18 rounded-card text-[44px] italic',
    };
@endphp

<x-subject-tint
    :subject="$subject"
    as="span"
    aria-hidden="true"
    {{ $attributes->class(['flex shrink-0 items-center justify-center font-serif leading-none', $classes]) }}
>{{ $letter }}</x-subject-tint>
