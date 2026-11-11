@props([
    'subject',
    'as' => 'div',
])

@php
    // The only place that knows how a subject maps to its tones (App\Support\Tint): its background and text here,
    // and --tint-shade for children, which draw large decorative letters and ruled lines with text-(color:--tint-shade)
    $tint = $subject->tint;
@endphp

<{{ $as }} {{ $attributes
    ->class('bg-(--tint-bg) text-(color:--tint-ink)')
    ->merge(['style' => "--tint-bg: {$tint->background}; --tint-ink: {$tint->ink}; --tint-shade: {$tint->shade}"]) }}>{{ $slot }}</{{ $as }}>
