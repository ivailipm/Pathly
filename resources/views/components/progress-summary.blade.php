@props([
    'done',
    'total',
])

<div {{ $attributes->class('space-y-1.5') }}>
    @if ($total > 0)
        <flux:text size="sm">
            {{ trans_choice(':done of :total task done|:done of :total tasks done', $total, ['done' => $done, 'total' => $total]) }}
        </flux:text>

        
        <flux:progress
            wire:key="progress-{{ $done }}-{{ $total }}"
            :value="$done"
            :max="$total"
            :aria-label="__('Tasks done')"
        />
    @else
        <flux:text size="sm">{{ __('No tasks yet') }}</flux:text>
    @endif
</div>