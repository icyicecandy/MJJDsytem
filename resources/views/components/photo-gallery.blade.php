@props([
    'photos' => [],
    'title' => 'Photos',
    'empty' => 'No photo uploaded.',
    'buttonText' => 'View photo',
])

@php
    $items = collect($photos)
        ->map(function ($path, $label) {
            $normalized = \App\Support\PublicUpload::normalizePath($path);
            $url = \App\Support\PublicUpload::urlIfExists($normalized);

            if (! $url) {
                return null;
            }

            return [
                'label' => is_string($label) ? $label : 'Photo',
                'path' => $normalized,
                'url' => $url,
            ];
        })
        ->filter()
        ->values();
    $missingItems = collect($photos)
        ->map(function ($path, $label) {
            $normalized = \App\Support\PublicUpload::normalizePath($path);

            if (! $normalized || \App\Support\PublicUpload::exists($normalized)) {
                return null;
            }

            return is_string($label) ? $label : 'Photo';
        })
        ->filter()
        ->values();
    $galleryId = 'photoGallery' . substr(md5($title . $items->pluck('path')->implode('|')), 0, 10);
@endphp

@once
    @push('styles')
        <style>
            .photo-gallery {
                display: grid;
                gap: 0.85rem;
            }

            .photo-gallery__grid {
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
                gap: 0.75rem;
            }

            .photo-gallery__thumb {
                display: block;
                width: 100%;
                padding: 0;
                overflow: hidden;
                border: 1px solid #dbe2ea;
                border-radius: 12px;
                background: #fff;
                cursor: pointer;
            }

            .photo-gallery__thumb img {
                display: block;
                width: 100%;
                aspect-ratio: 4 / 3;
                object-fit: cover;
            }

            .photo-gallery__actions {
                display: flex;
                flex-wrap: wrap;
                gap: 0.65rem;
            }

            .photo-gallery + .uploaded-file-empty,
            .uploaded-file-empty {
                padding: 0.85rem;
                border: 1px dashed #cbd5e1;
                border-radius: 12px;
                background: #f8fafc;
                color: #64748b;
                font-size: 0.92rem;
            }

            .photo-gallery-modal {
                position: fixed;
                inset: 0;
                z-index: 140;
                display: none;
                align-items: center;
                justify-content: center;
                padding: 1rem;
            }

            .photo-gallery-modal.is-open {
                display: flex;
            }

            .photo-gallery-modal__backdrop {
                position: absolute;
                inset: 0;
                background: rgba(15, 23, 42, 0.72);
                backdrop-filter: blur(3px);
            }

            .photo-gallery-modal__dialog {
                position: relative;
                z-index: 1;
                display: grid;
                gap: 0.9rem;
                width: min(960px, calc(100vw - 2rem));
                max-height: calc(100vh - 2rem);
                padding: 1rem;
                border: 1px solid rgba(226, 232, 240, 0.9);
                border-radius: 14px;
                background: #fff;
                box-shadow: 0 24px 80px rgba(15, 23, 42, 0.28);
            }

            .photo-gallery-modal__head,
            .photo-gallery-modal__footer {
                display: flex;
                align-items: center;
                justify-content: space-between;
                gap: 0.75rem;
            }

            .photo-gallery-modal__head h3 {
                margin: 0;
                color: #0f172a;
                font-size: 1.05rem;
                line-height: 1.3;
            }

            .photo-gallery-modal__close,
            .photo-gallery-modal__nav {
                display: inline-flex;
                align-items: center;
                justify-content: center;
                border: 1px solid #cbd5e1;
                background: #fff;
                color: #0f172a;
                cursor: pointer;
            }

            .photo-gallery-modal__close {
                width: 2.35rem;
                height: 2.35rem;
                border-radius: 999px;
                font-size: 1.4rem;
            }

            .photo-gallery-modal__stage {
                position: relative;
                display: flex;
                align-items: center;
                justify-content: center;
                min-height: 260px;
                overflow: hidden;
                border-radius: 12px;
                background: #0f172a;
            }

            .photo-gallery-modal__slide {
                display: none;
                width: 100%;
                max-height: min(70vh, 680px);
                object-fit: contain;
            }

            .photo-gallery-modal__slide.is-active {
                display: block;
            }

            .photo-gallery-modal__nav {
                position: absolute;
                top: 50%;
                width: 2.5rem;
                height: 2.5rem;
                border-radius: 999px;
                transform: translateY(-50%);
                box-shadow: 0 12px 30px rgba(15, 23, 42, 0.18);
            }

            .photo-gallery-modal__nav--prev {
                left: 0.8rem;
            }

            .photo-gallery-modal__nav--next {
                right: 0.8rem;
            }

            .photo-gallery-modal__caption {
                display: grid;
                gap: 0.16rem;
                color: #111827;
                font-size: 0.92rem;
                font-weight: 700;
            }

            .photo-gallery-modal__caption span {
                color: #64748b;
                font-size: 0.78rem;
                font-weight: 650;
            }

            body.photo-gallery-open {
                overflow: hidden;
            }

            @media (max-width: 760px) {
                .photo-gallery-modal__dialog {
                    width: calc(100vw - 1rem);
                    padding: 0.75rem;
                }

                .photo-gallery-modal__footer {
                    display: grid;
                    align-items: stretch;
                }
            }
        </style>
    @endpush

    @push('scripts')
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                document.querySelectorAll('[data-photo-gallery]').forEach(function (modal) {
                    const slides = Array.from(modal.querySelectorAll('[data-photo-gallery-slide]'));
                    const caption = modal.querySelector('[data-photo-gallery-caption]');
                    const counter = modal.querySelector('[data-photo-gallery-counter]');
                    const originalLink = modal.querySelector('[data-photo-gallery-original]');
                    const closers = modal.querySelectorAll('[data-photo-gallery-close]');
                    const prev = modal.querySelector('[data-photo-gallery-prev]');
                    const next = modal.querySelector('[data-photo-gallery-next]');
                    let activeIndex = 0;
                    let touchStartX = null;

                    const showSlide = function (index) {
                        if (slides.length === 0) {
                            return;
                        }

                        activeIndex = (index + slides.length) % slides.length;

                        slides.forEach(function (slide, slideIndex) {
                            slide.classList.toggle('is-active', slideIndex === activeIndex);
                        });

                        const activeSlide = slides[activeIndex];
                        if (caption) {
                            caption.textContent = activeSlide.dataset.photoLabel || 'Photo';
                        }
                        if (counter) {
                            counter.textContent = (activeIndex + 1) + ' of ' + slides.length;
                        }
                        if (originalLink) {
                            originalLink.href = activeSlide.dataset.photoUrl || activeSlide.src;
                        }
                    };

                    const openModal = function (index) {
                        showSlide(index);
                        modal.classList.add('is-open');
                        modal.setAttribute('aria-hidden', 'false');
                        document.body.classList.add('photo-gallery-open');
                    };

                    const closeModal = function () {
                        modal.classList.remove('is-open');
                        modal.setAttribute('aria-hidden', 'true');
                        document.body.classList.remove('photo-gallery-open');
                    };

                    document.querySelectorAll('[data-photo-gallery-open="' + modal.id + '"]').forEach(function (button) {
                        button.addEventListener('click', function () {
                            openModal(parseInt(button.dataset.photoGalleryIndex || '0', 10));
                        });
                    });

                    closers.forEach(function (button) {
                        button.addEventListener('click', closeModal);
                    });

                    if (prev) {
                        prev.addEventListener('click', function () {
                            showSlide(activeIndex - 1);
                        });
                    }

                    if (next) {
                        next.addEventListener('click', function () {
                            showSlide(activeIndex + 1);
                        });
                    }

                    modal.addEventListener('touchstart', function (event) {
                        touchStartX = event.changedTouches[0]?.clientX ?? null;
                    }, { passive: true });

                    modal.addEventListener('touchend', function (event) {
                        if (touchStartX === null || slides.length < 2) {
                            return;
                        }

                        const touchEndX = event.changedTouches[0]?.clientX ?? touchStartX;
                        const delta = touchEndX - touchStartX;
                        touchStartX = null;

                        if (Math.abs(delta) < 40) {
                            return;
                        }

                        showSlide(delta > 0 ? activeIndex - 1 : activeIndex + 1);
                    }, { passive: true });

                    document.addEventListener('keydown', function (event) {
                        if (!modal.classList.contains('is-open')) {
                            return;
                        }

                        if (event.key === 'Escape') {
                            closeModal();
                        } else if (event.key === 'ArrowLeft') {
                            showSlide(activeIndex - 1);
                        } else if (event.key === 'ArrowRight') {
                            showSlide(activeIndex + 1);
                        }
                    });

                    showSlide(0);
                });
            });
        </script>
    @endpush
@endonce

@if($items->isNotEmpty())
    <div {{ $attributes->merge(['class' => 'photo-gallery']) }}>
        <div class="photo-gallery__grid">
            @foreach($items as $item)
                <button type="button" class="photo-gallery__thumb" data-photo-gallery-open="{{ $galleryId }}" data-photo-gallery-index="{{ $loop->index }}">
                    <img src="{{ $item['url'] }}" alt="{{ $item['label'] }}">
                </button>
            @endforeach
        </div>
        <div class="photo-gallery__actions">
            <button type="button" class="btn secondary" data-photo-gallery-open="{{ $galleryId }}" data-photo-gallery-index="0">{{ $buttonText }}</button>
        </div>
    </div>

    <div class="photo-gallery-modal" id="{{ $galleryId }}" data-photo-gallery aria-hidden="true">
        <div class="photo-gallery-modal__backdrop" data-photo-gallery-close></div>
        <div class="photo-gallery-modal__dialog" role="dialog" aria-modal="true" aria-labelledby="{{ $galleryId }}Title">
            <div class="photo-gallery-modal__head">
                <h3 id="{{ $galleryId }}Title">{{ $title }}</h3>
                <button type="button" class="photo-gallery-modal__close" data-photo-gallery-close aria-label="Close photo viewer">&times;</button>
            </div>

            <div class="photo-gallery-modal__stage">
                @foreach($items as $item)
                    <img
                        src="{{ $item['url'] }}"
                        alt="{{ $item['label'] }}"
                        class="photo-gallery-modal__slide {{ $loop->first ? 'is-active' : '' }}"
                        data-photo-gallery-slide
                        data-photo-label="{{ $item['label'] }}"
                        data-photo-url="{{ $item['url'] }}"
                    >
                @endforeach

                @if($items->count() > 1)
                    <button type="button" class="photo-gallery-modal__nav photo-gallery-modal__nav--prev" data-photo-gallery-prev aria-label="Previous photo">&lsaquo;</button>
                    <button type="button" class="photo-gallery-modal__nav photo-gallery-modal__nav--next" data-photo-gallery-next aria-label="Next photo">&rsaquo;</button>
                @endif
            </div>

            <div class="photo-gallery-modal__footer">
                <div class="photo-gallery-modal__caption">
                    <span data-photo-gallery-counter>1 of {{ $items->count() }}</span>
                    <strong data-photo-gallery-caption>{{ $items->first()['label'] }}</strong>
                </div>
                <a href="{{ $items->first()['url'] }}" target="_blank" rel="noopener noreferrer" class="btn secondary" data-photo-gallery-original>Open original</a>
            </div>
        </div>
    </div>
@endif

@if($missingItems->isNotEmpty())
    <div {{ $attributes->merge(['class' => 'uploaded-file-empty']) }}>Uploaded file not found.</div>
@elseif($items->isEmpty())
    <div {{ $attributes->merge(['class' => 'uploaded-file-empty']) }}>{{ $empty }}</div>
@endif
