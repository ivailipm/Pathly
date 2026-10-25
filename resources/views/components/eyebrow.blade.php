@props([
    'size' => 'md',
    'accent' => false,
])

<span {{ $attributes->class([
    'font-mono uppercase tracking-label',
    'text-xs' => $size === 'md',
    'text-[11px]' => $size === 'sm',
    'text-vermilion' => $accent,
    'text-muted' => ! $accent,
]) }}>{{ $slot }}</span>
