@props(['value'])

<label {{ $attributes->merge(['class' => 'mb-1 block text-sm font-semibold uppercase tracking-[0.08em] text-slate-600']) }}>
    {{ $value ?? $slot }}
</label>
