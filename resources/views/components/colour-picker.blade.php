@use('App\Support\Tint')

@props([
    'label' => __('Colour'),
    // The current "#rrggbb", so a custom colour's swatch can show its tint
    'value' => null,
])

@php
    $model = $attributes->wire('model')->value();
    $current = Tint::isColour($value) ? strtolower($value) : null;
    $isCustom = $current !== null && Tint::nameOf($current) === null;
    $custom = Tint::of($current ?? Tint::DEFAULT);
@endphp

{{--
    The preset colours as swatches, plus any colour from the browser's own picker. Pass wire:model
    (wire:model="form.colour") and :value. Choices are sent at once, so the custom swatch shows its tint.
--}}
<fieldset {{ $attributes->whereDoesntStartWith('wire:model')->class('grid gap-2') }}>
    <legend class="mb-2 text-sm font-medium text-ink">{{ $label }}</legend>

    <div class="flex flex-wrap items-center gap-2.5">
        @foreach (Tint::PRESETS as $name => $hex)
            @php
                $tint = Tint::of($hex);
            @endphp

            <label
                title="{{ __($name) }}"
                style="background-color: {{ $tint->background }}; color: {{ $tint->ink }}"
                class="flex size-10 cursor-pointer items-center justify-center rounded-button font-serif text-lg italic ring-offset-2 ring-offset-white has-checked:ring-2 has-checked:ring-ink has-focus-visible:outline-2 has-focus-visible:outline-offset-4 has-focus-visible:outline-ink"
            >
                <input type="radio" value="{{ $hex }}" wire:model.live="{{ $model }}" class="sr-only" />
                <span aria-hidden="true">A</span>
                <span class="sr-only">{{ __($name) }}</span>
            </label>
        @endforeach

        <label
            title="{{ __('Any colour') }}"
            @if ($isCustom) style="background-color: {{ $custom->background }}; color: {{ $custom->ink }}" @endif
            @class([
                'relative flex h-10 cursor-pointer items-center gap-2 rounded-button px-3 text-sm focus-within:outline-2 focus-within:outline-offset-4 focus-within:outline-ink',
                'font-medium ring-2 ring-ink ring-offset-2 ring-offset-white' => $isCustom,
                'border border-dashed border-control bg-paper text-body hover:text-ink' => ! $isCustom,
            ])
        >
            {{-- Invisible over the whole swatch, so a click anywhere opens the browser's colour picker --}}
            <input
                type="color"
                wire:model.live.debounce.300ms="{{ $model }}"
                class="absolute inset-0 size-full cursor-pointer opacity-0"
                aria-label="{{ __('Any colour') }}"
            />
            <flux:icon.swatch variant="micro" class="size-4" />
            {{ $isCustom ? __('Custom') : __('Any colour') }}
        </label>
    </div>

    @error($model)
        <p class="text-sm text-red-700">{{ $message }}</p>
    @enderror
</fieldset>
