<section class="w-full space-y-8">
    <flux:heading size="xl" level="1">{{ __('Subjects') }}</flux:heading>

    <form wire:submit="save" class="max-w-lg space-y-4">
        <flux:input wire:model="form.title" :label="__('Title')" required />
        <flux:textarea wire:model="form.description" :label="__('Description')" rows="3" />
        <flux:button type="submit" variant="primary">{{ __('Add subject') }}</flux:button>
    </form>

    <div class="space-y-3">
        @forelse ($this->subjects as $subject)
            <a href="{{ route('subjects.show', $subject) }}" wire:navigate wire:key="subject-{{ $subject->id }}" class="block">
                <flux:card class="hover:bg-zinc-50">
                    <flux:heading>{{ $subject->title }}</flux:heading>
                    @if ($subject->description)
                        <flux:text class="mt-1">{{ $subject->description }}</flux:text>
                    @endif
                    @if ($subject->tasks_count > 0)
                        <x-progress-segments :done="$subject->done_tasks_count" :total="$subject->tasks_count" class="mt-3 max-w-[360px]" />
                    @else
                        <flux:text size="sm" class="mt-3">{{ __('No tasks yet') }}</flux:text>
                    @endif
                </flux:card>
            </a>
        @empty
            <flux:text>{{ __('No subjects yet. Add your first one above.') }}</flux:text>
        @endforelse
    </div>
</section>
