<x-layouts::app.sidebar :title="$title ?? null">
    {{-- No padding here: pages draw full-bleed headers and cover bands, and pad their own content --}}
    <flux:main class="p-0!">
        {{ $slot }}
    </flux:main>
</x-layouts::app.sidebar>
