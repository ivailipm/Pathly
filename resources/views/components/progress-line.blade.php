@props([
    'value',
    'max',
])

@php
    $percent = $max > 0 ? min(100, round($value / $max * 100)) : 0;
@endphp

<div {{ $attributes->class('flex items-center gap-2.5') }}>
    <span class="flex h-1 flex-1 overflow-hidden rounded-[2px] bg-rule" aria-hidden="true">
        <span class="rounded-[2px] bg-ink" style="width: {{ $percent }}%"></span>
    </span>
    <span class="font-mono text-xs text-muted">{{ $value }}/{{ $max }}</span>
</div>
