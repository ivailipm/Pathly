@props([
    'current' => false,
])

<a
    {{ $attributes->class([
        'flex min-h-10 items-center gap-2.5 rounded-button px-2.5 text-sm focus-visible:outline-2 focus-visible:outline-ink',
        'bg-active font-medium text-ink' => $current,
        'text-body hover:bg-active/60 hover:text-ink' => ! $current,
    ]) }}
    @if ($current) aria-current="page" @endif
    wire:navigate
>{{ $slot }}</a>
