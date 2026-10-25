<x-layouts::app :title="__('Today')">
    <div class="flex h-full w-full flex-1 flex-col gap-4 rounded-xl">
        <div class="grid auto-rows-min gap-4 md:grid-cols-3">
            <div class="relative aspect-video overflow-hidden rounded-card border border-rule">
                <x-placeholder-pattern class="absolute inset-0 size-full stroke-ink/20" />
            </div>
            <div class="relative aspect-video overflow-hidden rounded-card border border-rule">
                <x-placeholder-pattern class="absolute inset-0 size-full stroke-ink/20" />
            </div>
            <div class="relative aspect-video overflow-hidden rounded-card border border-rule">
                <x-placeholder-pattern class="absolute inset-0 size-full stroke-ink/20" />
            </div>
        </div>
        <div class="relative h-full flex-1 overflow-hidden rounded-card border border-rule">
            <x-placeholder-pattern class="absolute inset-0 size-full stroke-ink/20" />
        </div>
    </div>
</x-layouts::app>
