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

    @php($taskCount = collect($this->columns)->sum(fn ($column) => $column->count()))

    @if ($taskCount > 0)
        <x-progress-summary :done="$this->columns[TaskStatus::Done->value]->count()" :total="$taskCount" class="max-w-md" />
    @else
        <flux:text>{{ __('No tasks yet. Add a resource or exercise below.') }}</flux:text>
    @endif

    <div class="grid gap-4 md:grid-cols-3">
        @foreach (TaskStatus::cases() as $status)
            <div wire:key="column-{{ $status->value }}" class="space-y-3 rounded-xl bg-zinc-50 p-3 dark:bg-zinc-800/50">
                <div class="flex items-center gap-2">
                    <flux:heading level="2">{{ __($status->label()) }}</flux:heading>
                    <flux:badge size="sm">{{ $this->columns[$status->value]->count() }}</flux:badge>
                </div>

                @if ($this->columns[$status->value]->isEmpty())
                    <flux:text size="sm">{{ __('Nothing here yet') }}</flux:text>
                @endif

                <div class="min-h-24 space-y-2">
                    @foreach ($this->columns[$status->value] as $task)
                        <div
                            wire:key="task-{{ $task->id }}"
                            class="flex items-start gap-2 rounded-lg border border-zinc-200 bg-white p-3 dark:border-zinc-700 dark:bg-zinc-900"
                        >
                            @if ($editingTaskId === $task->id)
                                <form wire:submit="updateTask" class="min-w-0 flex-1 space-y-3">
                                    <flux:input wire:model="editTaskForm.title" :label="__('Title')" required />
                                    <flux:input wire:model="editTaskForm.url" :label="__('Link')" type="url" placeholder="https://" />

                                    <div class="flex gap-2">
                                        <flux:button type="submit" size="sm" variant="primary">{{ __('Save') }}</flux:button>
                                        <flux:button type="button" size="sm" wire:click="cancelTaskEdit">{{ __('Cancel') }}</flux:button>
                                    </div>
                                </form>
                            @else
                                <div class="min-w-0 flex-1">
                                    <span @class(['text-zinc-500 line-through' => $task->status === TaskStatus::Done])>{{ $task->title }}</span>
                                    @if ($task->url)
                                        <flux:link :href="$task->url" target="_blank" rel="noopener noreferrer" class="block truncate text-sm">
                                            {{ $task->url }}
                                        </flux:link>
                                    @endif
                                </div>

                                <flux:dropdown align="end">
                                    <flux:button size="sm" variant="ghost" icon="ellipsis-horizontal" :aria-label="__('Task actions')" />

                                    <flux:menu>
                                        @foreach (TaskStatus::cases() as $other)
                                            @continue($other === $status)

                                            <flux:menu.item wire:click="moveTask({{ $task->id }}, {{ $this->columns[$other->value]->count() }}, '{{ $other->value }}')">
                                                {{ __('Move to :status', ['status' => __($other->label())]) }}
                                            </flux:menu.item>
                                        @endforeach

                                        <flux:menu.separator />

                                        <flux:menu.item icon="pencil" wire:click="editTask({{ $task->id }})">{{ __('Edit') }}</flux:menu.item>
                                        <flux:menu.item
                                            icon="trash"
                                            variant="danger"
                                            wire:click="deleteTask({{ $task->id }})"
                                            wire:confirm="{{ __('Delete this task?') }}"
                                        >
                                            {{ __('Delete') }}
                                        </flux:menu.item>
                                    </flux:menu>
                                </flux:dropdown>
                            @endif
                        </div>
                    @endforeach
                </div>
            </div>
        @endforeach
    </div>

    <form wire:submit="addTask" class="max-w-lg space-y-4">
        <flux:heading size="lg" level="2">{{ __('Add a task') }}</flux:heading>
        <flux:input wire:model="taskForm.title" :label="__('Title')" required />
        <flux:input wire:model="taskForm.url" :label="__('Link (optional)')" type="url" placeholder="https://" />
        <flux:button type="submit" variant="primary" icon="plus">{{ __('Add task') }}</flux:button>
    </form>
</section>
