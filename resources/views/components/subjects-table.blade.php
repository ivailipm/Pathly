@props([
    'subjects',
])

{{-- The Library table (also "Across your subjects" on Today). Subjects need withLibraryData(). --}}
<div {{ $attributes->class('overflow-x-auto border-t border-ink') }}>
    <table class="w-full min-w-[640px] text-left text-sm">
        <thead>
            <tr class="border-b border-rule font-mono text-[11px] tracking-label text-muted uppercase">
                <th scope="col" class="w-[32%] px-1 py-2.5 font-normal">{{ __('Subject') }}</th>
                <th scope="col" class="w-[32%] px-1 py-2.5 font-normal">{{ __('Current milestone') }}</th>
                <th scope="col" class="w-[22%] px-1 py-2.5 font-normal">{{ __('Milestones') }}</th>
                <th scope="col" class="px-1 py-2.5 font-normal">{{ __('Target') }}</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($subjects as $subject)
                @php
                    $current = $subject->current_milestone;
                @endphp

                <tr wire:key="subject-row-{{ $subject->id }}" class="border-b border-rule">
                    <td class="h-13 px-1">
                        <a href="{{ route('subjects.show', $subject) }}" wire:navigate class="flex items-center gap-2.5 font-medium text-ink hover:text-vermilion focus-visible:outline-2 focus-visible:outline-ink">
                            <x-monogram :subject="$subject" size="md" />
                            <span class="min-w-0 truncate">{{ $subject->title }}</span>
                        </a>
                    </td>
                    <td class="px-1 text-body">
                        @if ($current)
                            {{ sprintf('%02d', $current->number) }} · {{ $current->title }}
                        @else
                            <span class="text-muted">—</span>
                        @endif
                    </td>
                    <td class="px-1">
                        <x-progress-line :value="$subject->done_milestones_count" :max="$subject->milestones_count" />
                    </td>
                    <td class="px-1 font-mono text-xs">
                        @if ($subject->target_date)
                            {{ $subject->target_date->isoFormat('D MMM') }}
                        @else
                            <span class="text-muted">{{ __('No date') }}</span>
                        @endif
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>
</div>
