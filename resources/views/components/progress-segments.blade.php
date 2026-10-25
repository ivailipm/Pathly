@props([
    'done',
    'inProgress' => 0,
    'total',
])

@php
    $done = (int) $done;
    $inProgress = (int) $inProgress;
    $total = (int) $total;
    $todo = max(0, $total - $done - $inProgress);

    $label = trans_choice(':done of :total task done|:done of :total tasks done', $total, ['done' => $done, 'total' => $total]);

    if ($inProgress > 0) {
        $label .= __(', :count in progress', ['count' => $inProgress]);
    }
@endphp

@if ($total > 0)
    <div role="img" aria-label="{{ $label }}" {{ $attributes->class('flex gap-1') }}>
        @if ($total <= 20)
            @for ($i = 0; $i < $total; $i++)
                <span @class([
                    'h-1.5 flex-1 rounded-[2px]',
                    'bg-ink' => $i < $done,
                    'bg-vermilion' => $i >= $done && $i < $done + $inProgress,
                    'bg-control' => $i >= $done + $inProgress,
                ])></span>
            @endfor
        @else
            {{-- Past 20 tasks the segments get too thin: three parts sized by their counts instead --}}
            @foreach ([[$done, 'bg-ink'], [$inProgress, 'bg-vermilion'], [$todo, 'bg-control']] as [$count, $colour])
                @if ($count > 0)
                    <span class="h-1.5 rounded-[2px] {{ $colour }}" style="flex: {{ $count }} 1 0%"></span>
                @endif
            @endforeach
        @endif
    </div>
@endif
