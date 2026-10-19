@use('App\Enums\TaskStatus')

<section class="w-full space-y-8">
    <flux:link :href="route('subjects.show', $subject)" wire:navigate class="text-sm">
        &larr; {{ $subject->title }}
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
                <flux:heading size="xl" level="1">{{ $milestone->title }}</flux:heading>
                @if ($milestone->description)
                    <flux:text class="mt-2">{{ $milestone->description }}</flux:text>
                @endif
            </div>

            <div class="flex gap-2">
                <flux:button size="sm" icon="pencil" wire:click="edit">{{ __('Edit') }}</flux:button>
                <flux:button
                    size="sm"
                    variant="danger"
                    icon="trash"
                    wire:click="delete"
                    wire:confirm="{{ __('Delete this milestone and all its tasks?') }}"
                >
                    {{ __('Delete') }}
                </flux:button>
            </div>
        </div>
    @endif

    <div class="space-y-2">
        <flux:heading size="lg" level="2">{{ __('Tasks') }}</flux:heading>

        @forelse ($this->tasks as $task)
            <div wire:key="task-{{ $task->id }}" class="flex items-center gap-3 rounded-lg border border-zinc-200 px-4 py-3 dark:border-zinc-700">
                @if ($editingTaskId === $task->id)
                    <form wire:submit="updateTask" class="flex w-full flex-wrap items-end gap-2">
                        <div class="min-w-48 flex-1">
                            <flux:input wire:model="editTaskForm.title" :label="__('Title')" required />
                        </div>
                        <div class="min-w-48 flex-1">
                            <flux:input wire:model="editTaskForm.url" :label="__('Link')" type="url" placeholder="https://" />
                        </div>
                        <flux:button type="submit" size="sm" variant="primary">{{ __('Save') }}</flux:button>
                        <flux:button type="button" size="sm" wire:click="cancelTaskEdit">{{ __('Cancel') }}</flux:button>
                    </form>
                @else
                    <flux:checkbox
                        :checked="$task->status === TaskStatus::Done"
                        wire:click="toggle({{ $task->id }})"
                        :aria-label="$task->title"
                    />

                    <div class="min-w-0 flex-1">
                        <span @class(['text-zinc-500 line-through' => $task->status === TaskStatus::Done])>{{ $task->title }}</span>
                        @if ($task->url)
                            <flux:link :href="$task->url" target="_blank" rel="noopener noreferrer" class="block truncate text-sm">
                                {{ $task->url }}
                            </flux:link>
                        @endif
                    </div>

                    <flux:button size="sm" variant="ghost" icon="pencil" wire:click="editTask({{ $task->id }})" :aria-label="__('Edit task')" />
                    <flux:button
                        size="sm"
                        variant="ghost"
                        icon="trash"
                        wire:click="deleteTask({{ $task->id }})"
                        wire:confirm="{{ __('Delete this task?') }}"
                        :aria-label="__('Delete task')"
                    />
                @endif
            </div>
        @empty
            <flux:text>{{ __('No tasks yet. Add a resource or exercise below.') }}</flux:text>
        @endforelse
    </div>

    <form wire:submit="addTask" class="max-w-lg space-y-4">
        <flux:heading size="lg" level="2">{{ __('Add a task') }}</flux:heading>
        <flux:input wire:model="taskForm.title" :label="__('Title')" required />
        <flux:input wire:model="taskForm.url" :label="__('Link (optional)')" type="url" placeholder="https://" />
        <flux:button type="submit" variant="primary" icon="plus">{{ __('Add task') }}</flux:button>
    </form>
</section>
