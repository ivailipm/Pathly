<section class="w-full space-y-8">
    <flux:link :href="route('subjects.index')" wire:navigate class="text-sm">
        &larr; {{ __('All subjects') }}
    </flux:link>

    @if ($editing)
        <form wire:submit="update" class="max-w-lg space-y-4">
            <flux:input wire:model="form.title" :label="__('Title')" required />
            <flux:textarea wire:model="form.description" :label="__('Description')" rows="3" />

            <div class="flex gap-2">
                <flux:button type="submit" variant="primary">{{ __('Save') }}</flux:button>
                <flux:button type="button" wire:click="cancelEdit">{{ __('Cancel') }}</flux:button>
            </div>
        </form>
    @else
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <flux:heading size="xl" level="1">{{ $subject->title }}</flux:heading>
                @if ($subject->description)
                    <flux:text class="mt-2">{{ $subject->description }}</flux:text>
                @endif
            </div>

            <div class="flex gap-2">
                <flux:button size="sm" icon="pencil" wire:click="edit">{{ __('Edit') }}</flux:button>
                <flux:button
                    size="sm"
                    variant="danger"
                    icon="trash"
                    wire:click="delete"
                    wire:confirm="{{ __('Delete this subject and all its milestones and tasks?') }}"
                >
                    {{ __('Delete') }}
                </flux:button>
            </div>
        </div>
    @endif

    <div class="space-y-3">
        <flux:heading size="lg" level="2">{{ __('Milestones') }}</flux:heading>

        @forelse ($this->milestones as $milestone)
            <a href="{{ route('milestones.show', [$subject, $milestone]) }}" wire:navigate wire:key="milestone-{{ $milestone->id }}" class="block">
                <flux:card class="hover:bg-zinc-50 dark:hover:bg-zinc-700/50">
                    <flux:heading>{{ $loop->iteration }}. {{ $milestone->title }}</flux:heading>
                    @if ($milestone->description)
                        <flux:text class="mt-1">{{ $milestone->description }}</flux:text>
                    @endif
                </flux:card>
            </a>
        @empty
            <flux:text>{{ __('No milestones yet. Add the first step below.') }}</flux:text>
        @endforelse
    </div>

    <form wire:submit="addMilestone" class="max-w-lg space-y-4">
        <flux:heading size="lg" level="2">{{ __('Add a milestone') }}</flux:heading>
        <flux:input wire:model="milestoneForm.title" :label="__('Title')" required />
        <flux:textarea wire:model="milestoneForm.description" :label="__('Description')" rows="2" />
        <flux:button type="submit" variant="primary" icon="plus">{{ __('Add milestone') }}</flux:button>
    </form>
</section>
