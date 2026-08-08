@props(['value'])

<label {{ $attributes->merge(['class' => 'label-text font-medium']) }}>
    {{ $value ?? $slot }}
</label>
