@props([
    'breadcrumbs' => null,
    'actions' => null,
])

{{-- The top bar of a page. Pass flux:breadcrumbs in the breadcrumbs slot, or a plain title as the default slot. --}}
<header {{ $attributes->class('flex min-h-16 flex-wrap items-center justify-between gap-3 border-b border-rule px-4 py-3 sm:px-10') }}>
    @if ($breadcrumbs)
        <nav aria-label="{{ __('Breadcrumb') }}" class="min-w-0">{{ $breadcrumbs }}</nav>
    @else
        <span class="min-w-0 text-sm text-body">{{ $slot }}</span>
    @endif

    @if ($actions?->isNotEmpty())
        <div class="flex flex-wrap gap-2">{{ $actions }}</div>
    @endif
</header>
