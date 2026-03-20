
@extends('layouts.app')

@section('title','MJJD Telecom')
@section('hide_global_footer', '1')

@php
    $heroSlides = [
        asset('images/home/slide1.jpg'),
        asset('images/home/slide 2.jpg'),
        asset('images/home/slide3.jpg'),
        asset('images/home/slide4.jpg'),
        asset('images/home/slide5.jpg'),
        asset('images/home/slide6.jpg'),
    ];
@endphp

@push('styles')
<style>
    .public-home {
        --panel-soft: linear-gradient(180deg, rgba(255, 255, 255, 0.98), rgba(243, 247, 255, 0.95));
        --strip-soft: linear-gradient(180deg, rgba(233, 242, 255, 0.92), rgba(247, 250, 255, 0.94));
        display: grid;
        gap: 2.8rem;
        padding: 0 0.9rem 4rem;
    }
    .public-shell {
        width: min(1360px, 100%);
        margin-inline: auto;
    }
        .public-home-heading {
        display: grid;
        gap: 0.8rem;
        max-width: 680px;
    }
    .public-home-heading h2 {
        margin: 0;
        font-size: clamp(2rem, 4vw, 3.15rem);
        line-height: 1.05;
        letter-spacing: -0.04em;
        color: var(--color-primary);
    }
    .public-home-heading p {
        margin: 0;
        color: var(--color-text-muted);
        font-size: 1.02rem;
        line-height: 1.72;
    }

    .public-hero {
        position: relative;
        min-height: 640px;
        display: grid;
        align-items: center;
        border-radius: 30px;
        overflow: hidden;
        padding: 2.1rem 2.5rem 5.7rem;
        color: #fff;
        box-shadow: 0 30px 60px rgba(15, 23, 42, 0.18);
        isolation: isolate;
        background: linear-gradient(135deg, rgba(15, 23, 42, 0.96), rgba(30, 64, 175, 0.9));
    }
    .public-hero-slider {
        position: absolute;
        inset: 0;
        z-index: 0;
    }
    .public-hero-slide {
        position: absolute;
        inset: 0;
        background-size: cover;
        background-position: center;
        opacity: 0;
        transform: scale(1.06);
        transition: opacity 0.8s ease, transform 5.8s ease;
    }
    .public-hero-slide.is-active {
        opacity: 1;
        transform: scale(1);
    }
    .public-hero-overlay {
        position: absolute;
        inset: 0;
        background:
            radial-gradient(circle at 85% 18%, rgba(59, 130, 246, 0.3), transparent 28%),
            linear-gradient(100deg, rgba(15, 23, 42, 0.88) 0%, rgba(15, 23, 42, 0.6) 44%, rgba(15, 23, 42, 0.75) 100%);
    }
    .public-hero-main {
        position: relative;
        z-index: 1;
        display: grid;
        gap: 0.82rem;
        max-width: 640px;
    }
    .public-hero-main.is-refresh {
        animation: heroCopyRefresh 0.5s ease;
    }
    @keyframes heroCopyRefresh {
        from {
            opacity: 0.72;
            transform: translateY(10px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }
    .public-kicker {
        display: inline-flex;
        align-items: center;
        gap: 0.52rem;
        width: fit-content;
        padding: 0.48rem 0.88rem;
        border-radius: 999px;
        background: rgba(255, 255, 255, 0.1);
        border: 1px solid rgba(255, 255, 255, 0.14);
        color: rgba(255, 255, 255, 0.95);
        font-size: 0.81rem;
        letter-spacing: 0.1em;
        text-transform: uppercase;
    }
    .public-kicker svg {
        width: 15px;
        height: 15px;
    }
    .public-hero h1 {
        margin: 0;
        font-size: clamp(2.25rem, 5vw, 4.9rem);
        line-height: 0.96;
        letter-spacing: -0.05em;
        text-wrap: balance;
    }
    .public-hero p {
        margin: 0;
        max-width: 600px;
        color: rgba(255, 255, 255, 0.86);
        font-size: 1.03rem;
        line-height: 1.65;
    }
    .public-hero-actions {
        display: flex;
        flex-wrap: wrap;
        gap: 0.7rem;
        margin-top: 0.35rem;
    }
    .public-hero-btn {
        padding: 0.62rem 0.95rem;
        font-size: 0.92rem;
        box-shadow: 0 10px 22px rgba(37, 99, 235, 0.2);
    }
    .public-hero-dots {
        position: absolute;
        left: 2.5rem;
        bottom: 1.35rem;
        z-index: 2;
        display: flex;
        align-items: center;
        gap: 0.62rem;
    }
    .public-hero-dot {
        width: 9px;
        height: 9px;
        border: 0;
        border-radius: 999px;
        padding: 0;
        background: rgba(255, 255, 255, 0.38);
        cursor: pointer;
        transition: width 0.2s ease, transform 0.2s ease, background 0.2s ease;
    }
    .public-hero-dot.is-active {
        width: 28px;
        background: #fff;
    }

    .public-plans {
        padding: 2.85rem 2.4rem;
        border-radius: 28px;
        background: var(--panel-soft);
        border: 1px solid rgba(202, 216, 238, 0.68);
        box-shadow: 0 24px 52px rgba(15, 23, 42, 0.08);
    }
    .public-plans .public-home-heading {
        margin-inline: auto;
        text-align: center;
    }
    .public-plan-grid {
        margin-top: 1.35rem;
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 1.35rem;
    }
    .public-plan-card {
        position: relative;
        display: grid;
        gap: 1.08rem;
        padding: 1.6rem;
        border-radius: 24px;
        border: 1px solid rgba(219, 228, 239, 0.98);
        background: linear-gradient(180deg, rgba(255, 255, 255, 0.98), rgba(248, 250, 252, 0.94));
        box-shadow: 0 16px 35px rgba(15, 23, 42, 0.08);
        transition: transform 0.28s ease, box-shadow 0.28s ease, border-color 0.28s ease;
    }
    .public-plan-card:nth-child(1) {
        margin-top: 0.92rem;
    }
    .public-plan-card:hover {
        transform: translateY(-7px) scale(1.01);
        box-shadow: 0 22px 42px rgba(30, 64, 175, 0.16);
        border-color: rgba(37, 99, 235, 0.34);
    }
    .public-plan-card.featured {
        transform: scale(1.025);
        border-color: rgba(37, 99, 235, 0.44);
        box-shadow: 0 24px 52px rgba(37, 99, 235, 0.23);
        overflow: hidden;
    }
    .public-plan-card.featured::before {
        content: "";
        position: absolute;
        inset: -32% -38% auto auto;
        width: 240px;
        height: 240px;
        border-radius: 50%;
        background: radial-gradient(circle, rgba(59, 130, 246, 0.24), rgba(59, 130, 246, 0));
        pointer-events: none;
    }
    .public-plan-top {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 1rem;
    }
    .public-plan-top strong {
        display: block;
        color: var(--color-primary);
        font-size: 1.22rem;
    }
    .public-plan-speed {
        margin-top: 0.35rem;
        color: var(--color-primary);
        font-size: clamp(2rem, 3.8vw, 3.1rem);
        font-weight: 800;
        letter-spacing: -0.045em;
    }
    .public-plan-badge {
        padding: 0.45rem 0.75rem;
        border-radius: 999px;
        background: rgba(37, 99, 235, 0.1);
        color: var(--color-secondary);
        font-size: 0.8rem;
        font-weight: 700;
        white-space: nowrap;
    }
    .public-plan-price {
        color: var(--color-primary);
        font-size: clamp(2rem, 4vw, 3.15rem);
        font-weight: 800;
        letter-spacing: -0.05em;
    }
    .public-plan-price small {
        font-size: 0.52em;
        font-weight: 700;
        color: var(--color-text-muted);
    }
    .public-plan-list {
        margin: 0;
        padding: 0;
        list-style: none;
        display: grid;
        gap: 0.78rem;
    }
    .public-plan-list li {
        display: flex;
        align-items: center;
        gap: 0.62rem;
        color: var(--color-text-muted);
    }
    .public-plan-list li::before {
        content: "\2713";
        color: #22c55e;
        font-weight: 800;
    }

    .public-steps-strip {
        position: relative;
        width: min(1360px, 100%);
        margin-inline: auto;
        background: var(--panel-soft);
        border: 1px solid rgba(202, 216, 238, 0.65);
        border-radius: 28px;
        box-shadow: 0 20px 42px rgba(15, 23, 42, 0.06);
        padding: 3rem 2.4rem;
    }
    .public-steps-strip .public-home-heading {
        margin-bottom: 1.4rem;
        max-width: 640px;
        margin-inline: auto;
        text-align: center;
        justify-items: center;
    }
    .public-steps-grid {
        position: relative;
        display: grid;
        grid-template-columns: repeat(4, minmax(0, 1fr));
        gap: 1rem;
        isolation: isolate;
    }
    .public-steps-grid::before {
        content: "";
        position: absolute;
        left: 10%;
        right: 10%;
        top: 62px;
        height: 2px;
        background: linear-gradient(90deg, rgba(37, 99, 235, 0.2), rgba(37, 99, 235, 0.56), rgba(37, 99, 235, 0.2));
        z-index: 0;
    }
    .public-step-card {
        position: relative;
        z-index: 1;
        padding: 1.4rem 1.08rem;
        border-radius: 18px;
        border: 1px solid rgba(219, 228, 239, 0.82);
        background: rgba(255, 255, 255, 0.88);
        box-shadow: 0 10px 26px rgba(15, 23, 42, 0.06);
        display: grid;
        gap: 0.8rem;
        text-align: center;
        transition: transform 0.24s ease, box-shadow 0.24s ease;
    }
    .public-step-card:nth-child(even) {
        margin-top: 0;
    }
    .public-step-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 16px 34px rgba(30, 64, 175, 0.13);
    }
    .public-step-number {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 26px;
        height: 26px;
        border-radius: 999px;
        margin: 0 auto;
        background: rgba(37, 99, 235, 0.1);
        color: var(--color-secondary);
        font-size: 0.82rem;
        font-weight: 700;
    }
    .public-step-icon {
        width: 56px;
        height: 56px;
        margin: 0 auto;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        border-radius: 999px;
        background: linear-gradient(135deg, var(--color-primary) 0%, #1e3a8a 100%);
        color: #fff;
        transition: transform 0.25s ease;
    }
    .public-step-card:hover .public-step-icon {
        transform: translateY(-3px) scale(1.06) rotate(-4deg);
    }
    .public-step-icon svg {
        width: 24px;
        height: 24px;
    }
    .public-step-card h3 {
        margin: 0;
        color: var(--color-primary);
        font-size: 1.04rem;
        line-height: 1.4;
    }
    .public-step-card p {
        margin: 0;
        color: var(--color-text-muted);
        font-size: 0.94rem;
        line-height: 1.62;
    }
    .public-step-note {
        margin: 1.1rem 0 0;
        color: var(--color-text-muted);
        text-align: center;
    }

    .public-faq-section {
        padding-top: 0.4rem;
    }
    .public-faq-layout {
        display: grid;
        gap: 0.82rem;
    }
    .public-faq-wrap {
        display: grid;
        grid-template-columns: 1fr;
        gap: 0;
    }
    .public-faq-item {
        border: 0;
        border-bottom: 1px solid rgba(219, 228, 239, 0.95);
        border-radius: 0;
        background: transparent;
        box-shadow: none;
        transition: background 0.2s ease;
    }
    .public-faq-item[open],
    .public-faq-item.is-open {
        background: rgba(248, 251, 255, 0.65);
    }
    .public-faq-item summary {
        list-style: none;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 0.8rem;
        padding: 0.92rem 0.25rem;
        color: var(--color-primary);
        font-weight: 600;
        font-size: 0.98rem;
    }
    .public-faq-item summary::-webkit-details-marker {
        display: none;
    }
    .public-faq-item summary::after {
        content: "\2304";
        font-size: 1.2rem;
        line-height: 1;
        transform-origin: center;
        transition: transform 0.2s ease;
    }
    .public-faq-item[open] summary::after,
    .public-faq-item.is-open summary::after {
        transform: rotate(180deg);
    }
    .public-faq-answer {
        overflow: hidden;
        max-height: 0;
        transition: max-height 0.26s ease;
    }
    .public-faq-answer p {
        margin: 0;
        padding: 0 0.25rem 0.92rem;
        color: var(--color-text-muted);
        line-height: 1.62;
        font-size: 0.95rem;
    }

    .public-contact-section {
        display: grid;
        grid-template-columns: 1fr;
        align-items: start;
        gap: 1.1rem;
        padding-top: 0.5rem;
    }
    .public-contact-intro {
        display: grid;
        gap: 0.65rem;
    }
    .public-contact-intro h2 {
        margin: 0;
        color: var(--color-primary);
        font-size: clamp(1.8rem, 3.2vw, 2.65rem);
        line-height: 1.06;
        letter-spacing: -0.03em;
    }
    .public-contact-intro p {
        margin: 0;
        color: var(--color-text-muted);
        line-height: 1.7;
    }
    .public-contact-grid {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 0.95rem;
    }
    .public-contact-item {
        display: flex;
        align-items: flex-start;
        gap: 0.9rem;
        padding: 0.95rem;
        border-radius: 16px;
        border: 1px solid rgba(219, 228, 239, 0.88);
        background: rgba(255, 255, 255, 0.78);
        box-shadow: 0 8px 20px rgba(15, 23, 42, 0.04);
        transition: transform 0.2s ease, box-shadow 0.2s ease;
    }
    .public-contact-item:hover {
        transform: translateY(-3px);
        box-shadow: 0 14px 30px rgba(30, 64, 175, 0.11);
    }
    .public-contact-icon {
        width: 50px;
        height: 50px;
        flex: 0 0 50px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        border-radius: 999px;
        background: rgba(37, 99, 235, 0.1);
        color: var(--color-primary);
        transition: transform 0.2s ease;
    }
    .public-contact-item:hover .public-contact-icon {
        transform: scale(1.07);
    }
    .public-contact-icon svg {
        width: 24px;
        height: 24px;
    }
    .public-contact-item strong {
        display: block;
        margin-bottom: 0.2rem;
        color: var(--color-primary);
        font-size: 1.02rem;
    }
    .public-contact-item span {
        color: var(--color-text-muted);
        line-height: 1.6;
    }

    .public-footer {
        margin-top: 1.7rem;
        padding-top: 1.95rem;
        border-top: 1px solid rgba(219, 228, 239, 0.95);
        display: flex;
        align-items: flex-end;
        justify-content: space-between;
        gap: 1rem;
    }
    .public-footer-copy {
        display: grid;
        gap: 0.55rem;
    }
    .public-footer-brand {
        display: inline-flex;
        align-items: center;
        gap: 0.78rem;
        margin-bottom: 0;
        color: var(--color-primary);
    }
    .public-footer-brand .logo-mark {
        width: 40px;
        height: 40px;
        border-radius: 12px;
        box-shadow: none;
    }
    .public-footer-brand strong {
        font-size: 1.7rem;
    }
    .public-footer-copy p,
    .public-footer-links a {
        color: var(--color-text-muted);
    }
    .public-footer-copy p {
        margin: 0;
    }
    .public-footer-links {
        display: flex;
        flex-wrap: wrap;
        gap: 1.35rem;
        align-self: end;
    }
    .public-footer-links a {
        text-decoration: none;
    }
    .public-footer-links a:hover {
        color: var(--color-primary);
    }

    .btn,
    .public-hero-btn {
        transition: transform 0.2s ease, box-shadow 0.2s ease, filter 0.2s ease;
    }
    .btn:hover,
    .public-hero-btn:hover {
        transform: translateY(-2px) scale(1.01);
        filter: brightness(1.02);
    }

    [data-reveal] {
        opacity: 0;
        transform: translateY(24px);
        transition: opacity 0.5s ease, transform 0.5s ease;
    }
    [data-reveal].is-visible {
        opacity: 1;
        transform: translateY(0);
    }
    @media (max-width: 1200px) {
        .public-hero {
            min-height: 590px;
        }
        .public-steps-grid::before {
            left: 8%;
            right: 8%;
        }
    }
    @media (max-width: 1100px) {
        .public-plan-grid {
            grid-template-columns: 1fr;
        }
        .public-plan-card:nth-child(1) {
            margin-top: 0;
        }
        .public-plan-card.featured {
            transform: none;
        }
        .public-steps-grid {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }
        .public-steps-grid::before {
            display: none;
        }
        .public-step-card:nth-child(even) {
            margin-top: 0;
        }
        .public-contact-section {
            grid-template-columns: 1fr;
            gap: 1.4rem;
        }
        .public-contact-intro {
            position: static;
            max-width: 620px;
        }
    }
    @media (max-width: 900px) {
        .public-contact-grid {
            grid-template-columns: 1fr;
        }
        .public-footer {
            flex-direction: column;
            align-items: flex-start;
        }
    }
    @media (max-width: 768px) {
        .public-home {
            gap: 2rem;
            padding: 0 0.75rem 3rem;
        }
        .public-hero {
            min-height: auto;
            padding: 1.35rem 1.35rem 4.9rem;
            border-radius: 22px;
        }
        .public-hero-main {
            max-width: none;
        }
        .public-hero h1 {
            font-size: clamp(2rem, 10vw, 3.3rem);
        }
        .public-hero-actions {
            flex-direction: column;
        }
        .public-hero-actions .btn {
            width: 100%;
            justify-content: center;
        }
        .public-hero-dots {
            left: 1.35rem;
            bottom: 1.1rem;
        }
        .public-plans {
            padding: 2rem 1.2rem;
            border-radius: 22px;
        }
        .public-steps-strip {
            padding: 2.25rem 0 2.35rem;
        }
        .public-steps-grid {
            grid-template-columns: 1fr;
        }
    }
</style>
@endpush

@section('content')
<div class="public-home" id="home">
    <div class="public-shell" data-reveal>
        <section class="public-hero">
            <div class="public-hero-slider" data-hero-slider>
                @foreach($heroSlides as $index => $slide)
                    <div class="public-hero-slide {{ $index === 0 ? 'is-active' : '' }}" style="background-image: url('{{ $slide }}');"></div>
                @endforeach
                <div class="public-hero-overlay"></div>
            </div>

            <div class="public-hero-main" data-hero-copy>
                <span class="public-kicker">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M4 12h4l3 8 4-16 3 8h2"></path></svg>
                    Fiber Service Platform
                </span>
                <h1>Fast, reliable fiber internet for Pangasinan.</h1>
                <p>Apply for service, track your application, and check support updates in one simple MJJD Telecom portal.</p>
                <div class="public-hero-actions">
                    <a href="{{ route('customer.apply') }}" class="btn public-hero-btn">Apply Now</a>
                    <a href="{{ route('track.form') }}" class="btn secondary public-hero-btn">Track Application</a>
                    <a href="{{ route('track.ticket.form') }}" class="btn secondary public-hero-btn">Track Ticket</a>
                </div>
            </div>

            <div class="public-hero-dots" data-hero-dots>
                @foreach($heroSlides as $index => $slide)
                    <button type="button" class="public-hero-dot {{ $index === 0 ? 'is-active' : '' }}" data-slide-index="{{ $index }}" aria-label="Go to slide {{ $index + 1 }}"></button>
                @endforeach
            </div>
        </section>
    </div>

    <section class="public-shell public-plans" id="plans" data-reveal>
        <div class="public-home-heading">
            <h2>Choose Your Plan</h2>
            <p>Simple, transparent prepaid fiber options built around everyday browsing, streaming, and connected households.</p>
        </div>

        <div class="public-plan-grid">
            <article class="public-plan-card">
                <div class="public-plan-top">
                    <div>
                        <strong>Surf2Sawa 50</strong>
                        <div class="public-plan-speed">50 Mbps</div>
                    </div>
                </div>
                <div class="public-plan-price">&#8369;700<small>/month</small></div>
                <ul class="public-plan-list">
                    <li>Ideal for browsing and streaming</li>
                    <li>Up to 6 devices</li>
                    <li>Prepaid with no lock-in</li>
                </ul>
                <a href="{{ route('customer.apply') }}" class="btn">Select Plan</a>
            </article>

            <article class="public-plan-card featured">
                <div class="public-plan-top">
                    <div>
                        <strong>Surf2Sawa 100</strong>
                        <div class="public-plan-speed">100 Mbps</div>
                    </div>
                    <span class="public-plan-badge">Most Popular</span>
                </div>
                <div class="public-plan-price">&#8369;999<small>/month</small></div>
                <ul class="public-plan-list">
                    <li>Perfect for gaming and HD streaming</li>
                    <li>Up to 10 devices</li>
                    <li>Prepaid with no lock-in</li>
                </ul>
                <a href="{{ route('customer.apply') }}" class="btn">Select Plan</a>
            </article>
        </div>
    </section>

    <section class="public-steps-strip" id="steps" data-reveal>
        <div class="public-shell">
            <div class="public-home-heading">
                <h2>Get Connected in 4 Easy Steps</h2>
                <p>Our process is designed to get you online as quickly and smoothly as possible.</p>
            </div>

            <div class="public-steps-grid">
                <article class="public-step-card">
                    <span class="public-step-number">1</span>
                    <span class="public-step-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M16 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path><circle cx="10" cy="7" r="4"></circle><path d="M19 8v6"></path><path d="M22 11h-6"></path></svg></span>
                    <h3>Create an Account</h3>
                    <p>Sign up quickly to start your application for a new connection.</p>
                </article>
                <article class="public-step-card">
                    <span class="public-step-number">2</span>
                    <span class="public-step-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M21 10c0 7-9 13-9 13S3 17 3 10a9 9 0 1 1 18 0z"></path><circle cx="12" cy="10" r="3"></circle></svg></span>
                    <h3>Submit Application &amp; Pin Location</h3>
                    <p>Fill in your details and pin your exact location on the map for our team.</p>
                </article>
                <article class="public-step-card">
                    <span class="public-step-number">3</span>
                    <span class="public-step-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="3" y="4" width="18" height="18" rx="2"></rect><path d="M16 2v4"></path><path d="M8 2v4"></path><path d="M3 10h18"></path><path d="m9 16 2 2 4-4"></path></svg></span>
                    <h3>Wait for Approval &amp; Scheduling</h3>
                    <p>We will review your application and schedule an installation date.</p>
                </article>
                <article class="public-step-card">
                    <span class="public-step-number">4</span>
                    <span class="public-step-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M5 12.55a11 11 0 0 1 14.08 0"></path><path d="M8.53 16.11a6 6 0 0 1 6.95 0"></path><path d="M12 20h.01"></path><path d="M2 8.82a16 16 0 0 1 20 0"></path></svg></span>
                    <h3>Installation &amp; Activation</h3>
                    <p>Our technician will install your connection and activate your service.</p>
                </article>
            </div>

            <p class="public-step-note">Availability depends on your area and nearest NAP box coverage.</p>
        </div>
    </section>

    <section class="public-shell public-faq-section" data-reveal>
        <div class="public-home-heading">
            <h2>Frequently Asked Questions</h2>
            <p>Find answers to common questions about our prepaid fiber services.</p>
        </div>

        <div class="public-faq-layout">
            <div class="public-faq-wrap" data-faq-root>
                <details class="public-faq-item">
                    <summary>What is prepaid fiber internet?</summary>
                    <div class="public-faq-answer">
                        <p>Prepaid fiber gives you high-speed internet without a long-term contract. You apply for installation first, then manage usage and reloads based on your service setup.</p>
                    </div>
                </details>
                <details class="public-faq-item">
                    <summary>How do I apply for a new connection?</summary>
                    <div class="public-faq-answer">
                        <p>You can apply by clicking the "Apply Now" button on our website. You will need to create an account, fill out an application form, and pin your location on the map.</p>
                    </div>
                </details>
                <details class="public-faq-item">
                    <summary>What are the requirements for application?</summary>
                    <div class="public-faq-answer">
                        <p>You will typically need your customer details, contact information, service address, and any requested supporting information shown in the application form.</p>
                    </div>
                </details>
                <details class="public-faq-item">
                    <summary>How long does the installation process take?</summary>
                    <div class="public-faq-answer">
                        <p>Timing depends on approval, area availability, and technician scheduling, but the portal helps you track progress once your request is in the system.</p>
                    </div>
                </details>
                <details class="public-faq-item">
                    <summary>How do I reload my account?</summary>
                    <div class="public-faq-answer">
                        <p>Customers can buy prepaid load from authorized retailers or load outlets in their area. The system will not handle billing or loading directly.</p>
                    </div>
                </details>
                <details class="public-faq-item">
                    <summary>Is there a data cap?</summary>
                    <div class="public-faq-answer">
                        <p>Plan behavior depends on the service offering connected to your account. Choose the option that best fits your household usage and review plan details during application.</p>
                    </div>
                </details>
            </div>
        </div>
    </section>

    <section class="public-shell public-contact-section" id="support" data-reveal>
        <div class="public-home-heading">
            <h2>Contact Us</h2>
            <p>Have questions? We're here to help.</p>
        </div>

        <div class="public-contact-grid">
            <article class="public-contact-item">
                <span class="public-contact-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.86 19.86 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6A19.86 19.86 0 0 1 2.12 4.18 2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72c.12.9.34 1.78.65 2.62a2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.46-1.22a2 2 0 0 1 2.11-.45c.84.31 1.72.53 2.62.65A2 2 0 0 1 22 16.92z"></path></svg></span>
                <div>
                    <strong>Phone</strong>
                    <span>0985 075 7176</span>
                </div>
            </article>
            <article class="public-contact-item">
                <span class="public-contact-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="3" y="5" width="18" height="14" rx="2"></rect><path d="m3 7 9 6 9-6"></path></svg></span>
                <div>
                    <strong>Email</strong>
                    <span>support@mjjdtelecom.com</span>
                </div>
            </article>
            <article class="public-contact-item">
                <span class="public-contact-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="12" cy="12" r="10"></circle><path d="M12 6v6l4 2"></path></svg></span>
                <div>
                    <strong>Business Hours</strong>
                    <span>Mon - Fri: 9am - 5pm</span><br>
                    <span>Sat: 9am - 12pm</span>
                </div>
            </article>
            <article class="public-contact-item">
                <span class="public-contact-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M21 10c0 7-9 13-9 13S3 17 3 10a9 9 0 1 1 18 0z"></path><circle cx="12" cy="10" r="3"></circle></svg></span>
                <div>
                    <strong>Office Location</strong>
                    <span>Dumlao St. Dusoc, Bayambang, Pangasinan</span>
                </div>
            </article>
        </div>
    </section>

    <section class="public-shell public-footer" data-reveal>
        <div class="public-footer-copy">
            <div class="public-footer-brand">
                <span class="logo-mark">MJ</span>
                <strong>MJJD TELECOM</strong>
            </div>
            <p>&copy; {{ date('Y') }} MJJD TELECOM. All rights reserved.</p>
        </div>

        <div class="public-footer-links">
            <a href="{{ route('track.form') }}">Application Tracking</a>
            <a href="{{ route('track.ticket.form') }}">Ticket Tracking</a>
            <a href="{{ route('login') }}">Support</a>
        </div>
    </section>
</div>
@endsection

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const slides = Array.from(document.querySelectorAll('[data-hero-slider] .public-hero-slide'));
        const dots = Array.from(document.querySelectorAll('[data-hero-dots] .public-hero-dot'));
        const heroCopy = document.querySelector('[data-hero-copy]');

        if (slides.length && dots.length) {
            let activeIndex = 0;
            let intervalId = null;

            const refreshHeroCopy = () => {
                if (!heroCopy) {
                    return;
                }

                heroCopy.classList.remove('is-refresh');
                window.requestAnimationFrame(() => {
                    heroCopy.classList.add('is-refresh');
                });
            };

            const showSlide = (index) => {
                activeIndex = index;
                slides.forEach((slide, i) => slide.classList.toggle('is-active', i === index));
                dots.forEach((dot, i) => dot.classList.toggle('is-active', i === index));
                refreshHeroCopy();
            };

            const startAutoPlay = () => {
                clearInterval(intervalId);
                intervalId = setInterval(() => {
                    showSlide((activeIndex + 1) % slides.length);
                }, 4500);
            };

            dots.forEach((dot, index) => {
                dot.addEventListener('click', () => {
                    showSlide(index);
                    startAutoPlay();
                });
            });

            showSlide(0);
            startAutoPlay();
        }

        const faqItems = Array.from(document.querySelectorAll('.public-faq-item'));
        faqItems.forEach((item) => {
            const summary = item.querySelector('summary');
            const content = item.querySelector('.public-faq-answer');

            if (!summary || !content) {
                return;
            }

            const closeItem = () => {
                item.classList.remove('is-open');
                content.style.maxHeight = '0px';
                window.setTimeout(() => {
                    item.open = false;
                }, 170);
            };

            const openItem = () => {
                item.open = true;
                item.classList.add('is-open');
                content.style.maxHeight = content.scrollHeight + 'px';
            };

            if (item.open) {
                item.classList.add('is-open');
                content.style.maxHeight = content.scrollHeight + 'px';
            }

            summary.addEventListener('click', (event) => {
                event.preventDefault();

                if (item.open) {
                    closeItem();
                    return;
                }

                faqItems.forEach((otherItem) => {
                    if (otherItem === item || !otherItem.open) {
                        return;
                    }

                    const otherContent = otherItem.querySelector('.public-faq-answer');
                    otherItem.classList.remove('is-open');
                    if (otherContent) {
                        otherContent.style.maxHeight = '0px';
                    }
                    window.setTimeout(() => {
                        otherItem.open = false;
                    }, 170);
                });

                openItem();
            });
        });

        const revealTargets = Array.from(document.querySelectorAll('[data-reveal]'));
        if ('IntersectionObserver' in window && revealTargets.length) {
            const observer = new IntersectionObserver((entries, obs) => {
                entries.forEach((entry) => {
                    if (entry.isIntersecting) {
                        entry.target.classList.add('is-visible');
                        obs.unobserve(entry.target);
                    }
                });
            }, { threshold: 0.16, rootMargin: '0px 0px -40px 0px' });

            revealTargets.forEach((target) => observer.observe(target));
        } else {
            revealTargets.forEach((target) => target.classList.add('is-visible'));
        }
    });
</script>
@endpush
