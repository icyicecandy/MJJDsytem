@props(['status', 'label' => null])
@php($normalized = strtolower(str_replace(' ', '_', $status)))
<span {{ $attributes->class(['badge', $normalized, 'ui-status-badge']) }}><span class="ui-status-badge__dot" aria-hidden="true"></span>{{ $label ?? ucfirst(str_replace('_', ' ', $status)) }}</span>
