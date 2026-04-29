@props([
    'path' => null,
    'label' => 'Uploaded file',
    'empty' => 'No file uploaded.',
    'buttonText' => 'View attachment',
    'imageAlt' => null,
    'showEmpty' => true,
])

@php
    $uploadPath = \App\Support\PublicUpload::normalizePath($path);
    $uploadExists = \App\Support\PublicUpload::exists($uploadPath);
    $uploadUrl = $uploadExists ? \App\Support\PublicUpload::url($uploadPath) : null;
    $isImage = $uploadExists && \App\Support\PublicUpload::isImage($uploadPath);
@endphp

@once
    @push('styles')
        <style>
            .uploaded-file-card {
                display: grid;
                gap: 0.7rem;
                padding: 0.85rem;
                border: 1px solid #e5e7eb;
                border-radius: 12px;
                background: #f9fafb;
            }

            .uploaded-file-card__preview {
                display: block;
                overflow: hidden;
                border: 1px solid #dbe2ea;
                border-radius: 10px;
                background: #fff;
            }

            .uploaded-file-card__preview img {
                display: block;
                width: 100%;
                max-height: 260px;
                object-fit: cover;
            }

            .uploaded-file-card__body {
                display: flex;
                flex-wrap: wrap;
                align-items: center;
                justify-content: space-between;
                gap: 0.7rem;
            }

            .uploaded-file-card__label {
                display: grid;
                gap: 0.18rem;
                color: #111827;
                font-size: 0.92rem;
                font-weight: 700;
            }

            .uploaded-file-card__label span {
                color: #6b7280;
                font-size: 0.78rem;
                font-weight: 650;
                word-break: break-word;
            }

            .uploaded-file-empty {
                padding: 0.85rem;
                border: 1px dashed #cbd5e1;
                border-radius: 12px;
                background: #f8fafc;
                color: #64748b;
                font-size: 0.92rem;
            }
        </style>
    @endpush
@endonce

@if($uploadUrl)
    <div {{ $attributes->merge(['class' => 'uploaded-file-card']) }}>
        @if($isImage)
            <a href="{{ $uploadUrl }}" target="_blank" rel="noopener noreferrer" class="uploaded-file-card__preview">
                <img src="{{ $uploadUrl }}" alt="{{ $imageAlt ?: $label }}">
            </a>
        @endif

        <div class="uploaded-file-card__body">
            <div class="uploaded-file-card__label">
                {{ $label }}
                <span>{{ $uploadPath }}</span>
            </div>
            <a href="{{ $uploadUrl }}" target="_blank" rel="noopener noreferrer" class="btn secondary">{{ $buttonText }}</a>
        </div>
    </div>
@elseif($uploadPath)
    <div {{ $attributes->merge(['class' => 'uploaded-file-empty']) }}>Uploaded file not found.</div>
@elseif($showEmpty)
    <div {{ $attributes->merge(['class' => 'uploaded-file-empty']) }}>{{ $empty }}</div>
@endif
