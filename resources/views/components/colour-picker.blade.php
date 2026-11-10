@use('App\Enums\SubjectColour')

@props([
    'label' => __('Colour'),
])

{{-- Four tint swatches as radios. Pass wire:model (e.g. wire:model="form.colour"). --}}
<fieldset {{ $attributes->whereDoesntStartWith('wire:model')->class('grid gap-2') }}>
    <legend class="mb-2 text-sm font-medium text-ink">{{ $label }}</legend>

    <div class="flex flex-wrap gap-2">
        @foreach (SubjectColour::cases() as $colour)
            <label class="flex min-h-10 cursor-pointer items-center gap-2 rounded-button border border-control bg-paper px-3 text-sm text-body has-checked:border-ink has-checked:bg-white has-checked:text-ink has-focus-visible:outline-2 has-focus-visible:outline-offset-2 has-focus-visible:outline-ink">
                <input type="radio" value="{{ $colour->value }}" {{ $attributes->whereStartsWith('wire:model') }} class="sr-only" />
                <span aria-hidden="true" @class([
                    'size-4 rounded-[4px]',
                    'bg-clay' => $colour === SubjectColour::Clay,
                    'bg-slate' => $colour === SubjectColour::Slate,
                    'bg-moss' => $colour === SubjectColour::Moss,
                    'bg-plum' => $colour === SubjectColour::Plum,
                ])></span>
                {{ __($colour->label()) }}
            </label>
        @endforeach
    </div>

    @error($attributes->whereStartsWith('wire:model')->first())
        <p class="text-sm text-red-700">{{ $message }}</p>
    @enderror
</fieldset>
