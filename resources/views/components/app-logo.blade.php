@props([
    'sidebar' => false,
])

{{-- The Pathly mark: an ink tile with an italic serif "P" --}}
@if($sidebar)
    <flux:sidebar.brand :name="config('app.name', 'Pathly')" {{ $attributes }}>
        <x-slot name="logo" class="flex aspect-square size-7 items-center justify-center rounded-md bg-ink font-serif text-lg italic text-paper">
            <span aria-hidden="true">P</span>
        </x-slot>
    </flux:sidebar.brand>
@else
    <flux:brand :name="config('app.name', 'Pathly')" {{ $attributes }}>
        <x-slot name="logo" class="flex aspect-square size-7 items-center justify-center rounded-md bg-ink font-serif text-lg italic text-paper">
            <span aria-hidden="true">P</span>
        </x-slot>
    </flux:brand>
@endif
