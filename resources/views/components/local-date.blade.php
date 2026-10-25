@use('App\Support\LocalTime')

@props([
    'value',
    'format' => 'D MMM',
])

{{-- For timestamps (stored in UTC). Date-only values such as target_date are formatted as stored (R1). --}}
<time datetime="{{ $value->toIso8601String() }}" {{ $attributes }}>{{ LocalTime::of($value)->isoFormat($format) }}</time>
