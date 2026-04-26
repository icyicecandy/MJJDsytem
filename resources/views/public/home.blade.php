
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
        --panel-soft: linear-gradient(180deg, rgba(15, 31, 74, 0.62), rgba(10, 24, 58, 0.54));
        --strip-soft: linear-gradient(180deg, rgba(21, 43, 97, 0.54), rgba(12, 29, 68, 0.46));
        --glass-border: rgba(173, 204, 255, 0.18);
        --glass-fill: linear-gradient(180deg, rgba(255, 255, 255, 0.18), rgba(211, 227, 255, 0.10));
        --glass-shadow: 0 8px 20px rgba(3, 16, 44, 0.12);
        --glass-shadow-hover: 0 14px 30px rgba(5, 24, 64, 0.18);
        display: grid;
        gap: 2.8rem;
        padding: 0 0 4rem;
    }
    .public-shell {
        width: min(1360px, 100%);
        margin-inline: auto;
    }
    .public-hero-shell {
        width: 100%;
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
        color: #001840;
    }
    .public-home-heading p {
        margin: 0;
        color: rgba(16, 42, 113, 0.78);
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
        background: linear-gradient(135deg, rgba(0, 24, 64, 0.96), rgba(16, 42, 113, 0.92) 60%, rgba(245, 196, 0, 0.72) 100%);
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
    @keyframes auroraDrift {
        0% {
            transform: translate3d(0, 0, 0) scale(1);
        }
        50% {
            transform: translate3d(3%, -2%, 0) scale(1.08);
        }
        100% {
            transform: translate3d(-4%, 3%, 0) scale(0.96);
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
    .public-hero-actions {
        display: flex;
        flex-wrap: wrap;
        gap: 0.7rem;
        margin-top: 0.35rem;
    }
    .public-hero-btn {
        padding: 0.62rem 0.95rem;
        font-size: 0.92rem;
        border-radius: 12px;
        box-shadow: 0 14px 28px rgba(16, 42, 113, 0.24);
    }
    .public-hero .btn.primary,
    .public-hero .public-hero-btn:not(.secondary) {
        background: linear-gradient(135deg, #102A71, #F5C400);
        color: #fff9df;
        border: none;
    }
    .public-hero .btn.primary:hover,
    .public-hero .public-hero-btn:not(.secondary):hover {
        background: linear-gradient(135deg, #001840, #F5C400);
    }
    .public-hero .btn.secondary {
        background: rgba(255, 249, 223, 0.92);
        color: #001840;
        border: 1px solid rgba(245, 196, 0, 0.52);
    }
    .public-hero .btn.secondary:hover {
        background: linear-gradient(135deg, rgba(255, 220, 95, 0.94), rgba(255, 249, 223, 0.98));
        color: #001840;
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
        background: var(--glass-fill);
        border: 1px solid var(--glass-border);
        box-shadow: var(--glass-shadow);
        backdrop-filter: blur(8px);
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
        border: 1px solid var(--glass-border);
        background: var(--glass-fill);
        box-shadow: var(--glass-shadow);
        backdrop-filter: blur(8px);
        transition: transform 0.28s ease, box-shadow 0.28s ease, border-color 0.28s ease;
    }
    .public-plan-card:nth-child(1) {
        margin-top: 0.92rem;
    }
    .public-plan-card:hover {
        transform: translateY(-7px) scale(1.01);
        box-shadow: 0 22px 42px rgba(6, 24, 64, 0.22);
        border-color: rgba(191, 214, 255, 0.30);
    }
    .public-plan-card.featured {
        transform: scale(1.025);
        border-color: rgba(191, 214, 255, 0.28);
        background: var(--glass-fill);
        box-shadow: var(--glass-shadow-hover);
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
        color: #001840;
        font-size: 1.22rem;
    }
    .public-plan-speed {
        margin-top: 0.35rem;
        color: #001840;
        font-size: clamp(2rem, 3.8vw, 3.1rem);
        font-weight: 800;
        letter-spacing: -0.045em;
    }
    .public-plan-badge {
        padding: 0.45rem 0.75rem;
        border-radius: 999px;
        background: rgba(245, 196, 0, 0.18);
        color: #001840;
        font-size: 0.8rem;
        font-weight: 700;
        white-space: nowrap;
    }
    .public-plan-price {
        color: #001840;
        font-size: clamp(2rem, 4vw, 3.15rem);
        font-weight: 800;
        letter-spacing: -0.05em;
    }
    .public-plan-price small {
        font-size: 0.52em;
        font-weight: 700;
        color: rgba(16, 42, 113, 0.72);
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
        color: rgba(16, 42, 113, 0.76);
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
        background: var(--glass-fill);
        border: 1px solid var(--glass-border);
        border-radius: 28px;
        box-shadow: var(--glass-shadow);
        backdrop-filter: blur(8px);
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
        display: none;
    }
    .public-step-card {
        position: relative;
        z-index: 1;
        padding: 1.4rem 1.08rem;
        border-radius: 18px;
        border: 1px solid var(--glass-border);
        background: var(--glass-fill);
        box-shadow: var(--glass-shadow);
        backdrop-filter: blur(8px);
        display: grid;
        gap: 0.8rem;
        text-align: center;
        transition: transform 0.24s ease, box-shadow 0.24s ease, border-color 0.24s ease;
    }
    .public-step-card:nth-child(even) {
        margin-top: 0;
    }
    .public-step-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 16px 34px rgba(5, 24, 64, 0.18);
        border-color: rgba(191, 214, 255, 0.28);
    }
    .public-step-number {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 26px;
        height: 26px;
        border-radius: 999px;
        margin: 0 auto;
        background: rgba(245, 196, 0, 0.18);
        color: #102A71;
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
        color: #001840;
        font-size: 1.04rem;
        line-height: 1.4;
    }
    .public-step-card p {
        margin: 0;
        color: rgba(16, 42, 113, 0.76);
        font-size: 0.94rem;
        line-height: 1.62;
    }
    .public-step-note {
        margin: 1.1rem 0 0;
        color: rgba(16, 42, 113, 0.72);
        text-align: center;
    }

    .public-faq-section {
        padding-top: 0.4rem;
    }
    .public-faq-layout {
        display: grid;
        gap: 0.82rem;
        padding: 1rem;
        border-radius: 24px;
        border: 1px solid var(--glass-border);
        background: var(--glass-fill);
        box-shadow: var(--glass-shadow);
        backdrop-filter: blur(8px);
    }
    .public-faq-wrap {
        display: grid;
        grid-template-columns: 1fr;
        gap: 0;
    }
    .public-faq-item {
        border: 0;
        border-bottom: 1px solid rgba(173, 204, 255, 0.18);
        border-radius: 0;
        background: transparent;
        box-shadow: none;
        transition: background 0.2s ease;
    }
    .public-faq-item[open],
    .public-faq-item.is-open {
        background: rgba(255, 255, 255, 0.10);
    }
    .public-faq-item summary {
        list-style: none;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 0.8rem;
        padding: 0.92rem 0.25rem;
        color: #001840;
        font-weight: 700;
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
        color: rgba(16, 42, 113, 0.76);
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
        color: #001840;
        font-size: clamp(1.8rem, 3.2vw, 2.65rem);
        line-height: 1.06;
        letter-spacing: -0.03em;
    }
    .public-contact-intro p {
        margin: 0;
        color: rgba(16, 42, 113, 0.76);
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
        border: 1px solid var(--glass-border);
        background: var(--glass-fill);
        box-shadow: var(--glass-shadow);
        backdrop-filter: blur(8px);
        transition: transform 0.2s ease, box-shadow 0.2s ease, border-color 0.2s ease;
    }
    .public-contact-item:hover {
        transform: translateY(-3px);
        box-shadow: var(--glass-shadow-hover);
        border-color: rgba(191, 214, 255, 0.28);
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
        color: #102A71;
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
        color: #001840;
        font-size: 1.02rem;
    }
    .public-contact-item span {
        color: rgba(16, 42, 113, 0.82);
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
        color: #001840;
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
        color: rgba(16, 42, 113, 0.82);
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
        color: #001840;
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
        display: none;
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
        transform: scale(1.025);
        border-color: rgba(191, 214, 255, 0.28);
        background: var(--glass-fill);
        box-shadow: var(--glass-shadow-hover);
        overflow: hidden;
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
        --panel-soft: linear-gradient(180deg, rgba(15, 31, 74, 0.62), rgba(10, 24, 58, 0.54));
        --strip-soft: linear-gradient(180deg, rgba(21, 43, 97, 0.54), rgba(12, 29, 68, 0.46));
        --glass-border: rgba(173, 204, 255, 0.18);
        --glass-fill: linear-gradient(180deg, rgba(255, 255, 255, 0.18), rgba(211, 227, 255, 0.10));
        --glass-shadow: 0 8px 20px rgba(3, 16, 44, 0.12);
        --glass-shadow-hover: 0 14px 30px rgba(5, 24, 64, 0.18);
        display: grid;
        gap: 2.8rem;
        padding: 0 0 4rem;
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
        margin: 0;
        font-size: clamp(2.25rem, 5vw, 4.9rem);
        line-height: 0.96;
        letter-spacing: -0.05em;
        text-wrap: balance;
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
        padding: 2.85rem 2.4rem;
        border-radius: 28px;
        background: var(--glass-fill);
        border: 1px solid var(--glass-border);
        box-shadow: var(--glass-shadow);
        backdrop-filter: blur(8px);
    }
        .public-steps-strip {
        position: relative;
        width: min(1360px, 100%);
        margin-inline: auto;
        background: var(--glass-fill);
        border: 1px solid var(--glass-border);
        border-radius: 28px;
        box-shadow: var(--glass-shadow);
        backdrop-filter: blur(8px);
        padding: 3rem 2.4rem;
    }
        .public-steps-grid {
            grid-template-columns: 1fr;
        }
    }
    @media (max-width: 640px) {
        .public-home {
            gap: 1.5rem;
            padding: 0 0 2.25rem;
        }
        .public-shell,
        .public-steps-strip {
            width: 100%;
            padding-left: 1rem;
            padding-right: 1rem;
        }
        .public-hero {
            min-height: auto;
            padding: 1.1rem 1rem 4rem;
            border-radius: 18px;
        }
        .public-kicker {
            font-size: 0.68rem;
            letter-spacing: 0.08em;
            padding: 0.42rem 0.72rem;
        }
        .public-hero h1 {
            font-size: clamp(1.9rem, 10vw, 2.6rem);
            line-height: 1;
        }
        .public-hero p {
            font-size: 0.92rem;
            line-height: 1.55;
        }
        .public-hero-actions {
            gap: 0.6rem;
        }
        .public-hero-actions .btn {
            width: 100%;
            justify-content: center;
            min-height: 3rem;
        }
        .public-hero-dots {
            left: 1rem;
            bottom: 0.9rem;
        }
        .public-home-heading {
            gap: 0.6rem;
        }
        .public-home-heading h2,
        .public-contact-intro h2 {
            font-size: clamp(1.55rem, 8vw, 2.1rem);
            line-height: 1.08;
        }
        .public-home-heading p,
        .public-contact-intro p {
            font-size: 0.92rem;
            line-height: 1.6;
        }
        .public-plans,
        .public-steps-strip,
        .public-faq-layout {
            padding: 1.35rem 1rem;
            border-radius: 20px;
        }
        .public-plan-grid,
        .public-contact-grid,
        .public-steps-grid {
            grid-template-columns: 1fr;
            gap: 0.9rem;
        }
        .public-plan-card,
        .public-step-card,
        .public-contact-item {
            padding: 1rem;
            border-radius: 18px;
        }
        .public-plan-card.featured {
            transform: none;
        }
        .public-plan-speed,
        .public-plan-price {
            font-size: clamp(1.8rem, 9vw, 2.4rem);
        }
        .public-step-card h3,
        .public-contact-item strong {
            font-size: 0.98rem;
        }
        .public-step-card p,
        .public-contact-item span,
        .public-step-note,
        .public-faq-answer p {
            font-size: 0.9rem;
            line-height: 1.55;
        }
        .public-faq-item summary {
            padding: 0.85rem 0.15rem;
            font-size: 0.92rem;
        }
        .public-contact-icon {
            width: 44px;
            height: 44px;
            flex-basis: 44px;
        }
        .public-footer {
            margin-top: 1rem;
            padding-top: 1.2rem;
            gap: 0.8rem;
            align-items: flex-start;
        }
        .public-footer-links {
            width: 100%;
            gap: 0.75rem 1rem;
            flex-direction: column;
            align-items: flex-start;
        }
    }

    @media (max-width: 420px) {
        .public-shell,
        .public-steps-strip {
            padding-left: 0.75rem;
            padding-right: 0.75rem;
        }
        .public-hero {
            padding: 1rem 0.88rem 3.8rem;
        }
        .public-plans,
        .public-steps-strip,
        .public-faq-layout {
            padding: 1.15rem 0.85rem;
        }
        .public-plan-card,
        .public-step-card,
        .public-contact-item {
            padding: 0.9rem;
        }
        .public-plan-top {
            gap: 0.7rem;
        }
        .public-plan-badge {
            font-size: 0.72rem;
            padding: 0.35rem 0.6rem;
        }
    }
</style>
@endpush

@section('content')
<div class="public-home" id="home">
    <div class="public-shell public-hero-shell" data-reveal>
        <section class="public-hero">
            <div class="public-hero-slider" data-hero-slider>
                @foreach($heroSlides as $index => $slide)
                    <div class="public-hero-slide {{ $index === 0 ? 'is-active' : '' }}" style="background-image: url('{{ $slide }}');"></div>
                @endforeach
            </div>

            <div class="public-hero-main" data-hero-copy>
                <span class="public-kicker">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M4 12h4l3 8 4-16 3 8h2"></path></svg>
                    Fiber Service Platform
                </span>
                <h1>Manage your MJJD Telecom Service Online.</h1>
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
            <p>Simple service options for households that need dependable internet installation and support. Reload via authorized retailers after installation.</p>
        </div>

        <div class="public-plan-grid">
            <article class="public-plan-card">
                <div class="public-plan-top">
                    <div>
                        <strong>Superfiber</strong>
                        <div class="public-plan-speed">50 Mbps</div>
                    </div>
                </div>
                <div class="public-plan-price">&#8369;700<small>/month</small></div>
                <ul class="public-plan-list">
                    <li>Ideal for browsing and streaming</li>
                    <li>Up to 6 devices</li>
                    <li>Application review before installation</li>
                </ul>
                <a href="{{ url('/') }}" class="btn" data-open-register-modal>Select Plan</a>
            </article>

            <article class="public-plan-card featured">
                <div class="public-plan-top">
                    <div>
                        <strong>Extraboost</strong>
                        <div class="public-plan-speed">100 Mbps</div>
                    </div>
                    <span class="public-plan-badge">Most Popular</span>
                </div>
                <div class="public-plan-price">&#8369;999<small>/month</small></div>
                <ul class="public-plan-list">
                    <li>Perfect for gaming and HD streaming</li>
                    <li>Up to 10 devices</li>
                    <li>Application review before installation</li>
                </ul>
                <a href="{{ url('/') }}" class="btn" data-open-register-modal>Select Plan</a>
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
                    <h3>Installation Completion</h3>
                    <p>Our technician will complete the installation and record the service visit in the system.</p>
                </article>
            </div>

            <p class="public-step-note">Availability depends on manual serviceability assessment and slot availability in your area.</p>
        </div>
    </section>

    <section class="public-shell public-faq-section" data-reveal>
        <div class="public-home-heading">
            <h2>Frequently Asked Questions</h2>
            <p>Find answers to common questions about applications, installation scheduling, and customer support.</p>
        </div>

        <div class="public-faq-layout">
            <div class="public-faq-wrap" data-faq-root>
                <details class="public-faq-item">
                    <summary>How does the application process work?</summary>
                    <div class="public-faq-answer">
                        <p>Create an account, submit your installation application, upload your valid ID, pin your location, and wait for admin review and scheduling updates.</p>
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
                    <summary>How do I track my request?</summary>
                    <div class="public-faq-answer">
                        <p>Use the application or ticket tracking page and enter your tracking number or reference number together with your registered contact details.</p>
                    </div>
                </details>
                <details class="public-faq-item">
                    <summary>Is there a data cap?</summary>
                    <div class="public-faq-answer">
                        <p>Service details depend on the plan you selected during application and the availability confirmed by MJJD Telecom.</p>
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
                    <span>support&#64;mjjdtelecom.com</span>
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
            <p>&copy; {{ date('Y') }} MJJD TELECOM. All rights reserved.</p>
        </div>

        <div class="public-footer-links">
            <a href="{{ route('track.form') }}">Application Tracking</a>
            <a href="{{ route('track.ticket.form') }}">Ticket Tracking</a>
            <a href="{{ url('/') }}" data-open-login-modal>Support</a>
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






































