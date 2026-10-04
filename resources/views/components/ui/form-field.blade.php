@props(['name', 'label', 'help' => null, 'required' => false])
<div {{ $attributes->class(['apply-field', 'ui-form-field']) }}>
    <label for="{{ $name }}">{{ $label }}@if($required)<span aria-hidden="true"> *</span>@endif</label>
    {{ $slot }}
    @if($help)<p class="field-help" id="{{ $name }}-help">{{ $help }}</p>@endif
    @error($name)<span class="error" id="{{ $name }}-error" role="alert">{{ $message }}</span>@enderror
</div>
