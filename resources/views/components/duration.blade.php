@props([
    'minutes',
])

@php
    $minutes = max(0, (int) $minutes);
    $hours = intdiv($minutes, 60);
@endphp

{{-- "45 min", or "4 h 10 min" with the minutes muted --}}
@if ($hours > 0)
    <span {{ $attributes }}>{{ __(':hours h', ['hours' => $hours]) }} <span class="text-muted">{{ __(':minutes min', ['minutes' => sprintf('%02d', $minutes % 60)]) }}</span></span>
@else
    <span {{ $attributes }}>{{ __(':minutes min', ['minutes' => $minutes]) }}</span>
@endif
