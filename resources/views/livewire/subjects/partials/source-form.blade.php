{{-- The add/edit form of the Sources list. Expects $submitLabel. --}}
<form wire:submit="save" class="flex flex-col gap-3 rounded-task border border-rule bg-white p-3.5">
    <flux:input wire:model="form.url" :label="__('Link')" type="url" placeholder="https://" autofocus />
    <flux:input
        wire:model="form.title"
        :label="__('Title')"
        description:trailing="{{ __('Optional with a link: it takes the site\'s name. Required without one, e.g. for a book.') }}"
    />

    <div class="flex gap-2">
        <flux:button type="submit" variant="primary" size="sm">{{ $submitLabel }}</flux:button>
        <flux:button type="button" variant="ghost" size="sm" wire:click="cancel">{{ __('Cancel') }}</flux:button>
    </div>
</form>
