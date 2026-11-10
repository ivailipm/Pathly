@use('App\Support\LocalTime')

<div>
    <flux:modal name="log-session" class="w-full max-w-md">
        <form wire:submit="save" class="space-y-6">
            <div>
                <flux:heading size="lg">{{ __('Log a session') }}</flux:heading>
                <flux:text class="mt-1">{{ __('Time you studied without the timer.') }}</flux:text>
            </div>

            @if ($milestone === null)
                <flux:select wire:model.live="form.subject_id" :label="__('Subject')" :placeholder="__('Choose a subject')">
                    @foreach ($this->subjectOptions as $subject)
                        <flux:select.option :value="$subject->id" wire:key="subject-option-{{ $subject->id }}">{{ $subject->title }}</flux:select.option>
                    @endforeach
                </flux:select>
            @endif

            <flux:select wire:model="form.task_id" :label="__('Task')">
                <flux:select.option value="">{{ __('No particular task') }}</flux:select.option>
                @foreach ($this->taskOptions as $task)
                    <flux:select.option :value="$task->id" wire:key="task-option-{{ $task->id }}">{{ $task->title }}</flux:select.option>
                @endforeach
            </flux:select>

            <div class="grid gap-6 sm:grid-cols-2">
                <flux:input wire:model="form.minutes" :label="__('Minutes')" type="number" min="1" max="600" required />
                <flux:input wire:model="form.date" :label="__('Date')" type="date" :max="LocalTime::today()->toDateString()" required />
            </div>

            <div class="flex justify-end gap-2">
                <flux:modal.close>
                    <flux:button variant="ghost">{{ __('Cancel') }}</flux:button>
                </flux:modal.close>
                <flux:button type="submit" variant="primary">{{ __('Save session') }}</flux:button>
            </div>
        </form>
    </flux:modal>
</div>
