@push('styles')
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin="" />
    <link rel="stylesheet" href="{{ asset('css/admin-id-verification.css') }}?v={{ filemtime(public_path('css/admin-id-verification.css')) }}">
    <style>
        .admin-app-review {
            display: grid;
            gap: 1.5rem;
            padding: 1.5rem;
        }
        .admin-app-header,
        .admin-app-card {
            border: 1px solid rgba(226, 232, 240, 0.95);
            border-radius: 1.25rem;
            background: #fff;
            box-shadow: 0 10px 30px rgba(15, 23, 42, 0.05);
        }
        .admin-app-header {
            display: grid;
            gap: 1.25rem;
            padding: 1.5rem;
        }
        .admin-app-header-top {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 1.5rem;
        }
        .admin-app-kicker,
        .admin-app-label,
        .admin-app-field label,
        .admin-app-inline-label,
        .admin-app-progress-step span {
            font-size: 0.75rem;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            color: #64748b;
            font-weight: 700;
        }
        .admin-app-header h1 {
            margin: 0.35rem 0 0.5rem;
            font-size: 1.5rem;
            line-height: 1.2;
            font-weight: 700;
            color: #0f172a;
        }
        .admin-app-subtitle,
        .admin-app-card p,
        .admin-app-helper,
        .admin-app-muted,
        .admin-app-progress-copy p {
            margin: 0;
            color: #64748b;
            font-size: 0.95rem;
            line-height: 1.5;
        }
        .admin-app-header-meta {
            display: grid;
            justify-items: end;
            gap: 0.5rem;
        }
        .admin-app-ref {
            font-size: 0.95rem;
            color: #0f172a;
            font-weight: 600;
        }
        .admin-app-status {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 0.45rem 0.8rem;
            border-radius: 999px;
            font-size: 0.78rem;
            font-weight: 700;
            letter-spacing: 0.04em;
            text-transform: uppercase;
        }
        .admin-app-status--submitted { background: #dbeafe; color: #1d4ed8; }
        .admin-app-status--under_review { background: #fef3c7; color: #b45309; }
        .admin-app-status--approved,
        .admin-app-status--installed,
        .admin-app-status--completed { background: #dcfce7; color: #15803d; }
        .admin-app-status--activated { background: #ccfbf1; color: #0f766e; }
        .admin-app-status--rejected { background: #fee2e2; color: #b91c1c; }
        .admin-app-status--cancelled { background: #fee2e2; color: #b91c1c; }
        .admin-app-status--cancellation_requested { background: #ffedd5; color: #c2410c; }
        .admin-app-status--scheduled { background: #e0e7ff; color: #4338ca; }
        .admin-app-status--pending { background: #e2e8f0; color: #475569; }
        .admin-app-progress {
            display: grid;
            grid-template-columns: repeat(5, minmax(0, 1fr));
            gap: 0.75rem;
        }
        .admin-app-progress-step {
            display: grid;
            gap: 0.55rem;
        }
        .admin-app-progress-line {
            position: relative;
            height: 0.5rem;
            border-radius: 999px;
            background: #e2e8f0;
            overflow: hidden;
        }
        .admin-app-progress-line::after {
            content: "";
            position: absolute;
            inset: 0;
            transform: scaleX(0);
            transform-origin: left center;
            background: linear-gradient(90deg, #0f766e, #22c55e);
        }
        .admin-app-progress-step.is-complete .admin-app-progress-line::after,
        .admin-app-progress-step.is-active .admin-app-progress-line::after {
            transform: scaleX(1);
        }
        .admin-app-progress-step.is-active .admin-app-progress-line::after {
            background: linear-gradient(90deg, #2563eb, #60a5fa);
        }
        .admin-app-progress-copy strong {
            display: block;
            margin-bottom: 0.25rem;
            color: #0f172a;
            font-size: 0.95rem;
        }
        .admin-app-layout {
            display: grid;
            grid-template-columns: minmax(0, 1.08fr) minmax(340px, 0.92fr);
            gap: 1.5rem;
            align-items: start;
        }
        .admin-app-column {
            display: grid;
            gap: 1.25rem;
        }
        .admin-app-card {
            display: grid;
            gap: 1rem;
            padding: 1.25rem;
        }
        .admin-app-card-head {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 1rem;
        }
        .admin-app-card-head h2,
        .admin-app-card-head h3 {
            margin: 0;
            font-size: 1.125rem;
            line-height: 1.3;
            font-weight: 600;
            color: #0f172a;
        }
        .admin-app-detail-grid,
        .admin-app-stat-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 0.85rem 1rem;
        }
        .admin-app-detail,
        .admin-app-stat {
            display: grid;
            gap: 0.3rem;
        }
        .admin-app-detail--full,
        .admin-app-stat--full {
            grid-column: 1 / -1;
        }
        .admin-app-value {
            color: #0f172a;
            font-size: 0.97rem;
            line-height: 1.5;
            word-break: break-word;
        }
        .admin-app-stat {
            padding: 0.9rem 1rem;
            border: 1px solid #e2e8f0;
            border-radius: 1rem;
            background: #f8fafc;
        }
        .admin-app-location-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 0.75rem 1rem;
            font-size: 0.85rem;
            color: #64748b;
        }
        .admin-app-form {
            display: grid;
            gap: 1rem;
        }
        .admin-app-form-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 1rem;
        }
        .admin-app-field {
            display: grid;
            min-width: 0;
            align-content: start;
            gap: 0.45rem;
        }
        .admin-app-scheduling-form .admin-app-form-grid {
            grid-template-columns: minmax(0, 1fr);
        }
        .admin-app-scheduling-form .admin-app-field--full {
            grid-column: auto;
        }
        .admin-app-field--full {
            grid-column: 1 / -1;
        }
        .admin-app-field input,
        .admin-app-field select,
        .admin-app-field textarea {
            width: 100%;
        }
        .admin-app-field textarea {
            min-height: 112px;
        }
        .admin-app-field textarea#rejection_reason {
            min-height: 88px;
        }
        .admin-app-field.is-hidden {
            display: none;
        }
        .admin-app-summary-box,
        .admin-app-empty {
            display: grid;
            gap: 0.85rem;
            padding: 1rem;
            border: 1px solid #e2e8f0;
            border-radius: 1rem;
            background: #f8fafc;
        }
        .admin-app-empty {
            justify-items: start;
        }
        .admin-app-reject-box {
            padding: 1rem;
            border: 1px solid #fecaca;
            border-radius: 1rem;
            background: #fff1f2;
        }
        .admin-app-reject-box strong {
            display: block;
            margin-bottom: 0.35rem;
            color: #991b1b;
        }
        .admin-app-actions {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 0.75rem;
        }
        .admin-app-actions--decision {
            grid-template-columns: repeat(3, max-content);
            justify-content: start;
            gap: 0.5rem;
        }
        .admin-app-actions .btn {
            width: 100%;
            justify-content: center;
        }
        .admin-app-actions--decision .btn {
            width: auto;
            min-height: 2.1rem;
            min-width: 0;
            padding: 0.48rem 0.72rem;
            border-radius: 8px;
            font-size: 0.78rem;
            line-height: 1.15;
        }
        .admin-app-timeline {
            position: relative;
            display: grid;
            gap: 1rem;
            padding-left: 1rem;
        }
        .admin-app-timeline::before {
            content: "";
            position: absolute;
            top: 0.25rem;
            bottom: 0.25rem;
            left: 0.2rem;
            width: 1px;
            background: #cbd5e1;
        }
        .admin-app-timeline-item {
            position: relative;
            display: grid;
            gap: 0.3rem;
            padding-left: 1rem;
        }
        .admin-app-timeline-item::before {
            content: "";
            position: absolute;
            top: 0.3rem;
            left: -0.02rem;
            width: 0.5rem;
            height: 0.5rem;
            border-radius: 999px;
            background: #2563eb;
            box-shadow: 0 0 0 4px #eff6ff;
        }
        .admin-app-timeline-meta {
            font-size: 0.82rem;
            color: #64748b;
        }
        .admin-app-photo {
            display: grid;
            gap: 0.75rem;
        }
        .admin-app-photo img {
            display: block;
            width: 100%;
            max-height: 300px;
            object-fit: cover;
            border-radius: 1rem;
            border: 1px solid #dbe2ea;
        }
        .admin-app-map-trigger {
            align-self: start;
        }
        .admin-app-map-link {
            white-space: nowrap;
        }
        .admin-app-map {
            position: relative;
            isolation: isolate;
            z-index: 0;
            height: 360px;
            border-radius: 1rem;
            border: 1px solid #dbe2ea;
            overflow: hidden;
        }
        .admin-app-map .leaflet-top,
        .admin-app-map .leaflet-bottom {
            z-index: 5;
        }
        .admin-app-map.is-empty {
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1rem;
            background: #f8fafc;
            color: #64748b;
            text-align: center;
        }
        .admin-app-map-modal {
            position: fixed;
            inset: 0;
            z-index: 120;
            display: none;
        }
        .admin-app-map-modal.is-open {
            display: block;
        }
        .admin-app-map-modal__backdrop {
            position: absolute;
            inset: 0;
            background: rgba(15, 23, 42, 0.58);
            backdrop-filter: blur(3px);
        }
        .admin-app-map-modal__dialog {
            position: relative;
            z-index: 1;
            width: min(820px, calc(100vw - 2rem));
            margin: 4vh auto;
            background: #fff;
            border-radius: 1.25rem;
            border: 1px solid rgba(226, 232, 240, 0.95);
            box-shadow: 0 24px 80px rgba(15, 23, 42, 0.22);
            overflow: hidden;
        }
        .admin-app-map-modal__shell {
            display: grid;
            gap: 1rem;
            padding: 1.25rem;
        }
        .admin-app-map-modal__head {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 1rem;
        }
        .admin-app-map-modal__head h3 {
            margin: 0;
            font-size: 1.125rem;
            line-height: 1.3;
            font-weight: 600;
            color: #0f172a;
        }
        .admin-app-map-modal__close {
            width: 2.25rem;
            height: 2.25rem;
            border: 0;
            border-radius: 999px;
            background: #eff6ff;
            color: #0f172a;
            font-size: 1.35rem;
            cursor: pointer;
        }
        .admin-app-map-modal__meta {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 0.85rem;
        }
        .admin-app-footer {
            display: flex;
            justify-content: flex-start;
        }
        body.admin-app-map-open {
            overflow: hidden;
        }
        @media (max-width: 1080px) {
            .admin-app-layout,
            .admin-app-progress {
                grid-template-columns: 1fr;
            }
        }
        @media (max-width: 760px) {
            .admin-app-review {
                padding: 1rem;
            }
            .admin-app-header,
            .admin-app-card {
                padding: 1rem;
            }
            .admin-app-header-top,
            .admin-app-card-head {
                display: grid;
            }
            .admin-app-header-meta {
                justify-items: start;
            }
            .admin-app-detail-grid,
            .admin-app-stat-grid,
            .admin-app-form-grid,
            .admin-app-actions,
            .admin-app-map-modal__meta {
                grid-template-columns: 1fr;
            }
            .admin-app-map-modal__dialog {
                width: calc(100vw - 1rem);
                margin: 2vh auto;
            }
        }

        .admin-app-review {
            gap: 2rem;
            padding: 1.5rem;
            background: #f6f8fb;
            color: #111827;
        }
        .admin-app-header,
        .admin-app-card {
            border: 1px solid #e5e7eb;
            border-radius: 12px;
            background: #fff;
            box-shadow: 0 10px 24px rgba(11, 31, 58, 0.06);
        }
        .admin-app-header {
            padding: 1.5rem;
        }
        .admin-app-header h1 {
            margin: 0.25rem 0 0.45rem;
            color: #0b1f3a;
            font-size: 2rem;
            line-height: 1.12;
            font-weight: 800;
            letter-spacing: 0;
        }
        .admin-app-kicker,
        .admin-app-label,
        .admin-app-field label,
        .admin-app-inline-label,
        .admin-app-progress-label {
            color: #6b7280;
            font-size: 0.74rem;
            font-weight: 700;
            letter-spacing: 0;
            text-transform: uppercase;
        }
        .admin-app-subtitle,
        .admin-app-card p,
        .admin-app-helper,
        .admin-app-muted,
        .admin-app-progress-copy p,
        .admin-app-summary-note {
            color: #6b7280;
            font-size: 0.92rem;
            line-height: 1.5;
        }
        .admin-app-status {
            min-height: 2rem;
            padding: 0.42rem 0.8rem;
            border-radius: 999px;
            font-size: 0.76rem;
            letter-spacing: 0;
        }
        .admin-app-layout {
            grid-template-columns: minmax(0, 65fr) minmax(320px, 35fr);
            gap: 2rem;
            align-items: start;
        }
        .admin-app-column {
            gap: 1.5rem;
            align-content: start;
        }
        .admin-app-card {
            gap: 1.25rem;
            padding: 1.5rem;
        }
        .admin-app-card-head {
            align-items: center;
            padding-bottom: 0.9rem;
            border-bottom: 1px solid #edf0f4;
        }
        .admin-app-card-head h2,
        .admin-app-card-head h3 {
            color: #0b1f3a;
            font-size: 1.05rem;
            font-weight: 700;
            letter-spacing: 0;
        }
        .admin-app-detail-grid,
        .admin-app-stat-grid,
        .admin-app-summary-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 1rem;
        }
        .admin-app-detail {
            min-width: 0;
            gap: 0.35rem;
        }
        .admin-app-value {
            color: #111827;
            font-size: 0.96rem;
            font-weight: 650;
            line-height: 1.45;
        }
        .admin-app-progress {
            grid-template-columns: repeat(5, minmax(0, 1fr));
            gap: 0;
            overflow: hidden;
        }
        .admin-app-progress-step {
            min-width: 0;
            gap: 0.7rem;
        }
        .admin-app-progress-marker {
            display: grid;
            grid-template-columns: 2rem minmax(0, 1fr);
            align-items: center;
            gap: 0.6rem;
        }
        .admin-app-progress-dot {
            display: inline-flex;
            width: 2rem;
            height: 2rem;
            align-items: center;
            justify-content: center;
            border: 2px solid #d1d5db;
            border-radius: 999px;
            background: #fff;
            color: #6b7280;
            font-size: 0.72rem;
            font-weight: 800;
            letter-spacing: 0;
            box-shadow: 0 0 0 5px #f3f4f6;
        }
        .admin-app-progress-line {
            height: 2px;
            border-radius: 999px;
            background: #d1d5db;
            overflow: visible;
        }
        .admin-app-progress-line::after {
            content: none;
        }
        .admin-app-progress-step:last-child .admin-app-progress-line {
            visibility: hidden;
        }
        .admin-app-progress-step.is-complete .admin-app-progress-dot {
            border-color: #16a34a;
            background: #16a34a;
            color: #fff;
            box-shadow: 0 0 0 5px #dcfce7;
        }
        .admin-app-progress-step.is-active .admin-app-progress-dot {
            border-color: #2563eb;
            background: #2563eb;
            color: #fff;
            box-shadow: 0 0 0 5px #dbeafe;
        }
        .admin-app-progress-step.is-complete .admin-app-progress-line {
            background: #16a34a;
        }
        .admin-app-progress-copy strong {
            margin-bottom: 0.2rem;
            color: #111827;
            font-size: 0.95rem;
            font-weight: 750;
        }
        .admin-app-progress-copy p {
            font-size: 0.84rem;
        }
        .admin-app-stat,
        .admin-app-mini-card {
            display: grid;
            gap: 0.4rem;
            min-width: 0;
            padding: 1rem;
            border: 1px solid #e5e7eb;
            border-radius: 12px;
            background: #f9fafb;
        }
        .admin-app-mini-card strong {
            color: #111827;
            font-size: 1rem;
            line-height: 1.35;
        }
        .admin-app-mini-card--status {
            grid-column: 1 / -1;
            border-color: rgba(11, 31, 58, 0.2);
            background: linear-gradient(135deg, #0b1f3a, #123a68);
            box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.08);
        }
        .admin-app-mini-card--status .admin-app-label,
        .admin-app-mini-card--status .admin-app-summary-note {
            color: #dbeafe;
        }
        .admin-app-mini-card--status strong {
            color: #fff;
            font-size: 1.35rem;
        }
        .admin-app-mini-card--full {
            grid-column: 1 / -1;
        }
        .admin-app-location-shell {
            display: grid;
            gap: 1rem;
        }
        .admin-app-map {
            height: 250px;
            border: 1px solid #dbe2ea;
            border-radius: 12px;
            overflow: hidden;
            background: #f8fafc;
        }
        .admin-app-map--modal {
            height: 420px;
        }
        .admin-app-location-bar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
        }
        .admin-app-location-meta {
            color: #6b7280;
            font-size: 0.86rem;
            line-height: 1.5;
        }
        .admin-app-summary-box,
        .admin-app-empty {
            border-radius: 12px;
            background: #f9fafb;
        }
        .admin-app-timeline {
            gap: 1rem;
            padding-left: 1.15rem;
        }
        .admin-app-timeline::before {
            left: 0.28rem;
            background: #d1d5db;
        }
        .admin-app-timeline-item {
            gap: 0.25rem;
            padding-left: 1rem;
        }
        .admin-app-timeline-item::before {
            top: 0.28rem;
            left: -0.02rem;
            width: 0.62rem;
            height: 0.62rem;
            background: #2563eb;
            box-shadow: 0 0 0 4px #dbeafe;
        }
        .admin-app-timeline-meta {
            color: #6b7280;
            font-size: 0.82rem;
            font-weight: 650;
        }
        .admin-app-photo img {
            border-radius: 12px;
        }
        .admin-app-review .btn {
            display: inline-flex;
            min-height: 2.6rem;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            padding: 0.65rem 1rem;
            border: 1px solid transparent;
            border-radius: 10px;
            background: #0b1f3a;
            color: #fff;
            font-weight: 750;
            line-height: 1.2;
            text-decoration: none;
            cursor: pointer;
            transition: background-color 0.18s ease, border-color 0.18s ease, box-shadow 0.18s ease, transform 0.18s ease;
        }
        .admin-app-review .btn:hover {
            background: #123a68;
            box-shadow: 0 10px 18px rgba(11, 31, 58, 0.14);
            transform: translateY(-1px);
        }
        .admin-app-review .btn.secondary {
            border-color: #cbd5e1;
            background: #fff;
            color: #0b1f3a;
        }
        .admin-app-review .btn.secondary:hover {
            border-color: #0b1f3a;
            background: #f8fafc;
        }
        .admin-app-review .btn.success {
            background: #15803d;
        }
        .admin-app-review .btn.success:hover {
            background: #166534;
        }
        .admin-app-review .btn.danger {
            background: #b91c1c;
        }
        .admin-app-review .btn.danger:hover {
            background: #991b1b;
        }
        @media (max-width: 1080px) {
            .admin-app-layout {
                grid-template-columns: 1fr;
            }
            .admin-app-progress {
                grid-template-columns: repeat(5, minmax(130px, 1fr));
                overflow-x: auto;
                padding-bottom: 0.25rem;
            }
        }
        @media (max-width: 760px) {
            .admin-app-review {
                padding: 1rem;
            }
            .admin-app-header,
            .admin-app-card {
                padding: 1rem;
            }
            .admin-app-header-top,
            .admin-app-location-bar {
                display: grid;
                justify-items: start;
            }
            .admin-app-detail-grid,
            .admin-app-stat-grid,
            .admin-app-summary-grid,
            .admin-app-form-grid,
            .admin-app-actions {
                grid-template-columns: 1fr;
            }
            .admin-app-mini-card--status,
            .admin-app-mini-card--full {
                grid-column: auto;
            }
            .admin-app-map {
                height: 240px;
            }
            .admin-app-map--modal {
                height: 360px;
            }
            .admin-app-header h1 {
                font-size: 1.65rem;
            }
        }

        /* Compact application workspace */
        .admin-app-review[data-admin-app-workspace] { --workspace-accent: #1d4ed8; --workspace-surface: rgba(255, 255, 255, 0.97); }
        .admin-app-header-main { min-width: 0; }
        .admin-app-header-meta .btn { min-height: 40px; padding: 0.55rem 0.8rem; }
        .admin-app-header-facts { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 0.65rem; margin-top: 1rem; padding-top: 1rem; border-top: 1px solid #e2e8f0; }
        .admin-app-header-fact { min-width: 0; padding: 0.7rem 0.8rem; border: 1px solid #e2e8f0; border-radius: 12px; background: #f8fafc; }
        .admin-app-header-fact span, .admin-app-header-fact strong { display: block; min-width: 0; overflow-wrap: anywhere; }
        .admin-app-header-fact span { margin-bottom: 0.2rem; color: #64748b; font-size: 0.7rem; font-weight: 800; letter-spacing: 0.05em; text-transform: uppercase; }
        .admin-app-header-fact strong { color: #0f2747; font-size: 0.9rem; }
        .admin-app-workspace-nav { position: sticky; z-index: 24; top: 86px; display: flex; align-items: center; gap: 0.45rem; margin: 0; padding: 0.55rem; border: 1px solid #dbe4f0; border-radius: 14px; background: var(--workspace-surface); box-shadow: 0 10px 24px rgba(15, 39, 71, 0.09); backdrop-filter: blur(12px); }
        .admin-app-workspace-tabs { display: flex; align-items: center; gap: 0.45rem; min-width: 0; }
        .admin-app-workspace-tab { display: inline-flex; align-items: center; justify-content: center; gap: 0.45rem; min-height: 44px; padding: 0.65rem 0.95rem; border: 0; border-radius: 10px; background: transparent; color: #52647d; font: inherit; font-size: 0.86rem; font-weight: 800; cursor: pointer; }
        .admin-app-workspace-tab:hover { background: #eff6ff; color: #1d4ed8; }
        .admin-app-workspace-tab[aria-pressed="true"] { background: #0f2747; color: #ffffff; box-shadow: 0 7px 16px rgba(15, 39, 71, 0.18); }
        .admin-app-workspace-tab-count { display: inline-grid; min-width: 1.35rem; height: 1.35rem; place-items: center; padding-inline: 0.3rem; border-radius: 999px; background: rgba(255,255,255,0.18); font-size: 0.7rem; }
        .admin-app-workspace-select { display: none; width: 100%; }
        .admin-app-workspace-select span { display: block; margin-bottom: 0.35rem; color: #52647d; font-size: 0.75rem; font-weight: 800; text-transform: uppercase; }
        .admin-app-workspace-select select { width: 100%; min-height: 46px; border: 1px solid #cbd5e1; border-radius: 10px; background: #fff; color: #0f2747; font: inherit; font-weight: 800; }
        [data-app-workspace-panel][hidden] { display: none !important; }
        .admin-app-layout.is-single-column { grid-template-columns: minmax(0, 1fr); }
        .admin-app-layout.is-single-column > .admin-app-column { display: contents; }
        .admin-app-layout.is-single-column [data-app-workspace-panel] { width: 100%; max-width: none; }
        .admin-app-panel--actions { max-width: 980px !important; }
        .admin-app-panel--overview .admin-app-summary-grid { gap: 0.65rem; }
        .admin-app-document-gallery .photo-gallery__grid, .admin-app-photo .photo-gallery__grid { grid-template-columns: repeat(auto-fit, minmax(130px, 190px)); justify-content: start; }
        .admin-app-document-gallery .photo-gallery__thumb, .admin-app-photo .photo-gallery__thumb { width: min(100%, 190px); aspect-ratio: 4 / 3; min-height: 0; overflow: hidden; border-radius: 12px; }
        .admin-app-document-gallery .photo-gallery__thumb img, .admin-app-photo .photo-gallery__thumb img { width: 100%; height: 100%; max-height: none; object-fit: cover; }
        .admin-app-location-overview { display: grid; grid-template-columns: minmax(0, 1fr) auto; align-items: center; gap: 1rem; padding: 0.9rem; border: 1px solid #dbe4f0; border-radius: 12px; background: #f8fafc; }
        .admin-app-location-overview strong, .admin-app-location-overview span { display: block; overflow-wrap: anywhere; }
        .admin-app-location-overview span { margin-top: 0.2rem; color: #64748b; }
        .admin-app-map { height: 280px; }
        .admin-app-coordinate-disclosure { margin-top: 0.7rem; }
        .admin-app-coordinate-disclosure summary { min-height: 44px; display: flex; align-items: center; color: #1d4ed8; font-weight: 800; cursor: pointer; }
        .admin-app-coordinate-disclosure .admin-app-location-meta { padding: 0.75rem; border-radius: 10px; background: #f8fafc; }
        .admin-app-history-scroll { max-height: min(58dvh, 38rem); padding: 0.25rem 0.4rem 0.25rem 0; overflow-y: auto; overscroll-behavior: contain; }
        .admin-app-workspace-live { position: absolute; width: 1px; height: 1px; padding: 0; margin: -1px; overflow: hidden; clip: rect(0, 0, 0, 0); white-space: nowrap; border: 0; }
        @media (max-width: 1080px) {
            .admin-app-header-facts { grid-template-columns: repeat(2, minmax(0, 1fr)); }
            .admin-app-workspace-nav { top: 82px; overflow-x: auto; scrollbar-width: thin; }
            .admin-app-workspace-tab { flex: 0 0 auto; }
        }
        @media (max-width: 760px) {
            .admin-app-header-facts { grid-template-columns: minmax(0, 1fr); }
            .admin-app-workspace-nav { top: 78px; display: block; padding: 0.7rem; }
            .admin-app-workspace-tabs { display: none; }
            .admin-app-workspace-select { display: block; }
            .admin-app-location-overview { grid-template-columns: minmax(0, 1fr); }
            .admin-app-location-overview .btn { width: 100%; }
            .admin-app-map { height: 220px; }
            .admin-app-document-gallery .photo-gallery__grid, .admin-app-photo .photo-gallery__grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
            .admin-app-document-gallery .photo-gallery__thumb, .admin-app-photo .photo-gallery__thumb { width: 100%; }
        }

        /* Route-aware installation scheduling */
        .admin-app-scheduling-form {
            position: relative;
        }
        .admin-app-scheduling-form.is-route-enhanced [data-route-technician-field] {
            position: absolute !important;
            width: 1px !important;
            height: 1px !important;
            margin: -1px !important;
            padding: 0 !important;
            overflow: hidden !important;
            clip: rect(0 0 0 0) !important;
            clip-path: inset(50%) !important;
            white-space: nowrap !important;
            border: 0 !important;
        }
        .admin-route-planner {
            display: grid;
            gap: 0.9rem;
            margin-top: 1rem;
            padding: 1rem;
            border: 1px solid #d8e3f2;
            border-radius: 16px;
            background: linear-gradient(145deg, #ffffff 0%, #f8fbff 100%);
            box-shadow: 0 12px 30px rgba(15, 39, 71, 0.07);
        }
        .admin-route-planner__header,
        .admin-route-planner__section-heading,
        .admin-route-planner__location,
        .admin-route-planner__selection {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
        }
        .admin-route-planner__header h4 {
            margin: 0.16rem 0 0.2rem;
            color: #0f2747;
            font-size: 1.05rem;
            line-height: 1.3;
        }
        .admin-route-planner__header p,
        .admin-route-planner__section-heading span {
            margin: 0;
            color: #64748b;
            font-size: 0.82rem;
            line-height: 1.45;
        }
        .admin-route-planner__eyebrow {
            color: #1d4ed8;
            font-size: 0.7rem;
            font-weight: 850;
            letter-spacing: 0.08em;
            text-transform: uppercase;
        }
        .admin-route-planner__count,
        .admin-route-card__badge,
        .admin-route-planner__estimate {
            flex: 0 0 auto;
            border-radius: 999px;
            font-size: 0.72rem;
            font-weight: 850;
        }
        .admin-route-planner__count {
            padding: 0.42rem 0.7rem;
            background: #e8f0ff;
            color: #1d4ed8;
        }
        .admin-route-planner__location {
            justify-content: flex-start;
            padding: 0.75rem 0.85rem;
            border: 1px solid #dce6f3;
            border-radius: 12px;
            background: #fff;
        }
        .admin-route-planner__location-icon {
            display: grid;
            flex: 0 0 2rem;
            width: 2rem;
            height: 2rem;
            place-items: center;
            border-radius: 50%;
            background: #e8f0ff;
            color: #1d4ed8;
            font-size: 1.15rem;
            font-weight: 900;
        }
        .admin-route-planner__location div {
            min-width: 0;
            flex: 1;
        }
        .admin-route-planner__location strong,
        .admin-route-planner__location span {
            display: block;
            overflow-wrap: anywhere;
        }
        .admin-route-planner__location strong {
            color: #0f2747;
            font-size: 0.8rem;
        }
        .admin-route-planner__location div span {
            margin-top: 0.12rem;
            color: #64748b;
            font-size: 0.8rem;
        }
        .admin-route-planner__estimate {
            padding: 0.35rem 0.58rem;
            background: #f1f5f9;
            color: #52647d;
        }
        .admin-route-planner__state {
            padding: 1rem;
            border: 1px dashed #b9c9dc;
            border-radius: 12px;
            background: #f8fafc;
            color: #64748b;
            text-align: center;
        }
        .admin-route-planner__state.is-warning {
            border-style: solid;
            border-color: #f4c46c;
            background: #fff8e8;
            color: #8a4b08;
        }
        .admin-route-planner__section-heading {
            align-items: end;
            margin-bottom: 0.65rem;
        }
        .admin-route-planner__section-heading strong {
            color: #0f2747;
            font-size: 0.88rem;
        }
        .admin-route-planner__cards {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 0.75rem;
        }
        .admin-route-card {
            display: grid;
            gap: 0.65rem;
            min-width: 0;
            padding: 0.85rem;
            border: 1px solid #dce5f1;
            border-radius: 14px;
            background: #fff;
            color: #0f2747;
            font: inherit;
            text-align: left;
            cursor: pointer;
            transition: border-color 0.18s ease, box-shadow 0.18s ease, transform 0.18s ease;
        }
        .admin-route-card:hover {
            border-color: #8ab0ed;
            box-shadow: 0 10px 24px rgba(29, 78, 216, 0.1);
            transform: translateY(-1px);
        }
        .admin-route-card.is-recommended {
            border-color: #78a5ec;
            background: linear-gradient(145deg, #f7faff 0%, #edf4ff 100%);
        }
        .admin-route-card.is-selected {
            border-color: #1d4ed8;
            box-shadow: 0 0 0 3px rgba(29, 78, 216, 0.13);
        }
        .admin-route-card__top,
        .admin-route-card__person,
        .admin-route-card__facts,
        .admin-route-card__job {
            display: flex;
            align-items: center;
        }
        .admin-route-card__top {
            justify-content: space-between;
            gap: 0.7rem;
        }
        .admin-route-card__person {
            min-width: 0;
            gap: 0.6rem;
        }
        .admin-route-card__avatar {
            display: grid;
            flex: 0 0 2.25rem;
            width: 2.25rem;
            height: 2.25rem;
            place-items: center;
            border-radius: 50%;
            background: #0f2747;
            color: #fff;
            font-size: 0.76rem;
            font-weight: 850;
        }
        .admin-route-card__person strong,
        .admin-route-card__person small {
            display: block;
            min-width: 0;
            overflow-wrap: anywhere;
        }
        .admin-route-card__person strong {
            font-size: 0.88rem;
            line-height: 1.25;
        }
        .admin-route-card__person small {
            margin-top: 0.12rem;
            color: #64748b;
            font-size: 0.72rem;
        }
        .admin-route-card__badge {
            padding: 0.32rem 0.55rem;
            background: #e8f0ff;
            color: #1d4ed8;
        }
        .admin-route-card.is-recommended .admin-route-card__badge {
            background: #1d4ed8;
            color: #fff;
        }
        .admin-route-card__reason {
            margin: 0;
            color: #405574;
            font-size: 0.78rem;
            line-height: 1.45;
        }
        .admin-route-card__facts {
            flex-wrap: wrap;
            gap: 0.4rem;
        }
        .admin-route-card__fact {
            padding: 0.28rem 0.45rem;
            border-radius: 7px;
            background: #f1f5f9;
            color: #52647d;
            font-size: 0.7rem;
            font-weight: 750;
        }
        .admin-route-card__schedule {
            display: grid;
            gap: 0.38rem;
            padding-top: 0.55rem;
            border-top: 1px solid #e4ebf4;
        }
        .admin-route-card__job {
            align-items: flex-start;
            justify-content: space-between;
            gap: 0.65rem;
            color: #52647d;
            font-size: 0.72rem;
            line-height: 1.35;
        }
        .admin-route-card__job strong {
            color: #0f2747;
            white-space: nowrap;
        }
        .admin-route-card__job span {
            min-width: 0;
            text-align: right;
            overflow-wrap: anywhere;
        }
        .admin-route-card__empty {
            color: #64748b;
            font-size: 0.75rem;
        }
        .admin-route-planner__unavailable {
            overflow: hidden;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            background: #fff;
        }
        .admin-route-planner__unavailable summary {
            display: flex;
            min-height: 44px;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
            padding: 0.65rem 0.8rem;
            color: #52647d;
            font-size: 0.8rem;
            font-weight: 800;
            cursor: pointer;
            list-style: none;
        }
        .admin-route-planner__unavailable summary::-webkit-details-marker {
            display: none;
        }
        .admin-route-planner__unavailable summary span:last-child {
            display: grid;
            min-width: 1.6rem;
            height: 1.6rem;
            place-items: center;
            border-radius: 999px;
            background: #eef2f7;
        }
        .admin-route-planner__unavailable-list {
            display: grid;
            border-top: 1px solid #e2e8f0;
        }
        .admin-route-unavailable {
            display: grid;
            grid-template-columns: minmax(130px, 0.7fr) minmax(0, 1.3fr);
            gap: 0.75rem;
            padding: 0.65rem 0.8rem;
            border-bottom: 1px solid #edf1f5;
            font-size: 0.76rem;
        }
        .admin-route-unavailable:last-child {
            border-bottom: 0;
        }
        .admin-route-unavailable strong {
            color: #0f2747;
        }
        .admin-route-unavailable span {
            color: #8a4b08;
            overflow-wrap: anywhere;
        }
        .admin-route-planner__selection {
            padding: 0.75rem 0.85rem;
            border: 1px solid #b8d2fb;
            border-radius: 12px;
            background: #edf5ff;
        }
        .admin-route-planner__selection div span,
        .admin-route-planner__selection div strong,
        .admin-route-planner__selection div small {
            display: block;
        }
        .admin-route-planner__selection div span {
            color: #52647d;
            font-size: 0.68rem;
            font-weight: 850;
            letter-spacing: 0.05em;
            text-transform: uppercase;
        }
        .admin-route-planner__selection div strong {
            margin-top: 0.12rem;
            color: #0f2747;
        }
        .admin-route-planner__selection div small {
            margin-top: 0.12rem;
            color: #52647d;
        }
        .admin-route-planner__selection-check {
            display: grid;
            flex: 0 0 2rem;
            width: 2rem;
            height: 2rem;
            place-items: center;
            border-radius: 50%;
            background: #15803d;
            color: #fff;
            font-weight: 900;
        }
        .admin-route-planner__hint {
            margin-top: 0.65rem;
        }
        @media (max-width: 900px) {
            .admin-route-planner__cards {
                grid-template-columns: minmax(0, 1fr);
            }
        }
        @media (max-width: 640px) {
            .admin-route-planner {
                padding: 0.8rem;
                border-radius: 13px;
            }
            .admin-route-planner__header,
            .admin-route-planner__section-heading,
            .admin-route-planner__location,
            .admin-route-planner__selection {
                align-items: flex-start;
            }
            .admin-route-planner__header,
            .admin-route-planner__section-heading {
                display: grid;
            }
            .admin-route-planner__location {
                flex-wrap: wrap;
            }
            .admin-route-planner__estimate {
                margin-left: 2.6rem;
            }
            .admin-route-card__top {
                align-items: flex-start;
            }
            .admin-route-card__job {
                display: grid;
                gap: 0.15rem;
            }
            .admin-route-card__job span {
                text-align: left;
            }
            .admin-route-unavailable {
                grid-template-columns: minmax(0, 1fr);
                gap: 0.22rem;
            }
        }

    </style>
@endpush

<x-app-layout>
    @php
        $installation = $application->installation;
        $proofPhotos = $installation ? collect($installation->proofPhotos())->filter() : collect();
        $installationLogs = $installation ? $installation->logs : collect();
        $technicalDetails = $installation ? $installation->technicalDetails() : [];
        $hasTechnicalDetails = $installation ? $installation->hasTechnicalDetails() : false;
        $serviceabilityStatus = old('serviceability_status', $application->serviceability_status ?? 'pending');
        $slotAvailabilityStatus = old('slot_availability_status', $application->slot_availability_status ?? 'pending');
        $assessmentNotes = old('assessment_notes', $application->assessment_notes ?? '');
        $rejectionReason = old('rejection_reason', $application->rejection_reason ?? '');
        $cancellationReason = $application->cancellation_reason ?? '';
        $identityDuplicates = $identityDuplicates ?? collect();
        $idVerificationStatus = $application->id_verification_status;
        $idVerificationLabel = $idVerificationStatus ? ucfirst($idVerificationStatus) : 'Legacy record';
        $idVerificationClass = $idVerificationStatus ? 'admin-id-status--' . $idVerificationStatus : 'admin-id-status--legacy';
        $selectedInstallationTime = old('installation_time', optional($installation)->installation_time ? \Carbon\Carbon::parse($installation->installation_time)->format('H:i') : '');
        $selectedTechnicianId = old('technician_id', optional($installation)->technician_id);
        $selectedInstallationDate = old('installation_date', optional($installation)->installation_date ? optional($installation->installation_date)->format('Y-m-d') : '');
        $availableTimeSlots = \App\Models\Installation::AVAILABLE_TIME_SLOTS;
        $statusKey = $application->status;
        $fullName = trim($application->first_name . ' ' . $application->last_name);
        $fullAddress = $application->fullServiceAddress() ?: 'Not provided';
        $locationLat = is_numeric($application->lat) ? (float) $application->lat : null;
        $locationLng = is_numeric($application->lng) ? (float) $application->lng : null;
        $hasLocation = $locationLat !== null && $locationLng !== null;
        $osmUrl = $hasLocation
            ? 'https://www.openstreetmap.org/?mlat=' . $locationLat . '&mlon=' . $locationLng . '#map=18/' . $locationLat . '/' . $locationLng
            : null;
        $showRejectionReason = old('status', $application->status) === 'rejected' || filled($rejectionReason);
        $statusLabel = ucfirst(str_replace('_', ' ', $statusKey));
        $statusClass = match ($statusKey) {
            'submitted' => 'admin-app-status--submitted',
            'under_review' => 'admin-app-status--under_review',
            'approved' => 'admin-app-status--approved',
            'scheduled' => 'admin-app-status--scheduled',
            'cancellation_requested' => 'admin-app-status--cancellation_requested',
            'installed' => 'admin-app-status--installed',
            'completed' => 'admin-app-status--completed',
            'activated' => 'admin-app-status--activated',
            'rejected' => 'admin-app-status--rejected',
            'cancelled' => 'admin-app-status--cancelled',
            default => 'admin-app-status--pending',
        };
        $isReviewState = in_array($statusKey, ['submitted', 'under_review'], true);
        $isApprovedState = $statusKey === 'approved';
        $isScheduledState = $statusKey === 'scheduled';
        $isCancellationRequestedState = $statusKey === 'cancellation_requested';
        $isCompletedState = in_array($statusKey, ['installed', 'completed', 'activated'], true);
        $isRejectedState = $statusKey === 'rejected';
        $isCancelledState = $statusKey === 'cancelled';
        $identityIsVerified = $idVerificationStatus === \App\Support\ApplicationIdentity::STATUS_VERIFIED;
        $identityIsRejected = $idVerificationStatus === \App\Support\ApplicationIdentity::STATUS_REJECTED;
        $hasIdentityDocuments = (bool) ($application->idImageUrl() || $application->idSelfieImageUrl());
        $identityNeedsApprovedRecordCorrection = in_array($statusKey, ['approved', 'scheduled', 'cancellation_requested', 'installed', 'completed', 'activated'], true)
            && $idVerificationStatus !== null
            && ! $identityIsVerified;
        $identityAllowsScheduling = $identityIsVerified || $idVerificationStatus === null;
        $isInstallationScheduled = $installation?->status === \App\Models\Installation::STATUS_SCHEDULED;
        $isRescheduleRequiredState = $installation?->canBeRescheduledByAdmin() ?? false;
        $hasPendingCustomerRescheduleRequest = $installation?->hasPendingCustomerRescheduleRequest() ?? false;
        $showReviewSummary = $application->assessed_at || filled($application->assessment_notes) || filled($application->serviceability_status) || filled($application->slot_availability_status) || filled($application->rejection_reason);
        $showAdminActionCard = $showReviewSummary || $isReviewState || $isApprovedState || $isRejectedState || $isInstallationScheduled || $isRescheduleRequiredState || $isCancellationRequestedState || $isCancelledState || $hasPendingCustomerRescheduleRequest;
        $submittedSummary = match ($statusKey) {
            'submitted' => 'This application is ready for manual review and decision.',
            'under_review' => 'Assessment is in progress. Review details can still be updated.',
            'approved' => 'The review is complete. The next step is assigning a technician and installation slot.',
            'scheduled' => $hasPendingCustomerRescheduleRequest
                ? 'The customer requested a new installation schedule. Review the preferred date and time before approving or rejecting.'
                : 'The application is approved and already scheduled for installation.',
            'cancellation_requested' => 'The customer requested cancellation. Admin review is required before changing the schedule.',
            'installed', 'completed' => 'Installation is complete and waiting for manual service activation.',
            'activated' => 'The customer service has been activated.',
            'rejected' => 'The review is complete and the application will not proceed to scheduling.',
            'cancelled' => 'This application was cancelled and the record is preserved for history.',
            default => 'Review the application details and current processing state.',
        };
        $stepIndexMap = [
            'submitted' => 1,
            'under_review' => 1,
            'approved' => 2,
            'scheduled' => 3,
            'cancellation_requested' => 3,
            'installed' => 4,
            'completed' => 4,
            'activated' => 5,
        ];
        $currentStepIndex = $stepIndexMap[$statusKey] ?? 0;
        $progressSteps = [
            'submitted' => ['label' => 'Submitted', 'description' => 'Application received'],
            'approved' => ['label' => 'Approved', 'description' => 'Review complete'],
            'scheduled' => ['label' => 'Scheduled', 'description' => 'Visit assigned'],
            'installed' => ['label' => 'Installed', 'description' => 'Job completed'],
            'activated' => ['label' => 'Activated', 'description' => 'Service active'],
        ];
        $reviewSummaryRows = [
            'Serviceability' => ucfirst(str_replace('_', ' ', $application->serviceability_status ?? 'pending')),
            'Slot availability' => ucfirst(str_replace('_', ' ', $application->slot_availability_status ?? 'pending')),
            'Assessed at' => $application->assessed_at?->displayDateTime('F d, Y h:i A') ?? 'Not recorded',
            'Assessed by' => $application->assessor?->name ?? 'Not recorded',
        ];
        $installationStatusLabel = $installation ? $installation->displayStatusLabel() : 'Not scheduled';
        $technicianName = $installation?->technician?->name ?? 'Not assigned';
        $technicianEmail = $installation?->technician?->email ?? 'Not recorded';
        $scheduleDateLabel = $installation?->installation_date?->format('F d, Y') ?? 'Not scheduled';
        $scheduleTimeLabel = $installation?->installation_time ? \App\Models\Installation::slotLabel(\Carbon\Carbon::parse($installation->installation_time)->format('H:i')) : 'Time not set';
        $scheduleLabel = $installation && ($installation->installation_date || $installation->installation_time)
            ? $scheduleDateLabel . ' - ' . $scheduleTimeLabel
            : 'Not scheduled';
        $completedAtLabel = $installation?->completed_at?->displayDateTime('F d, Y h:i A') ?? 'Not completed yet';
        $activationDateLabel = $installation?->activation_date?->format('F d, Y') ?? 'Not activated yet';
        $selectedRescheduleDate = old('installation_date', $installation?->reschedule_preferred_date?->format('Y-m-d') ?: $selectedInstallationDate);
        $selectedRescheduleTime = old('installation_time', $installation?->reschedule_preferred_time ? \Carbon\Carbon::parse($installation->reschedule_preferred_time)->format('H:i') : $selectedInstallationTime);
        $reschedulePreferredLabel = $installation?->reschedule_preferred_date
            ? $installation->reschedule_preferred_date->format('F d, Y') . ' - ' . ($installation->reschedule_preferred_time ? \App\Models\Installation::slotLabel(\Carbon\Carbon::parse($installation->reschedule_preferred_time)->format('H:i')) : 'Time not set')
            : 'No preferred schedule recorded';
        $documentCount = collect([
            $application->idImageUrl(),
            $application->idSelfieImageUrl(),
            ...collect($proofPhotos)->pluck('url')->all(),
        ])->filter()->count();
    @endphp

    <div class="admin-app-review" data-admin-app-workspace data-initial-tab="{{ $errors->has('id_verification_status') || $errors->has('id_verification_reason') || $errors->has('review_document_readable') || $errors->has('review_name_matches') || $errors->has('review_photo_matches') ? 'documents' : ($errors->any() && $showAdminActionCard ? 'actions' : 'overview') }}">
        <section class="admin-app-header">
            <div class="admin-app-header-top">
                <div class="admin-app-header-main">
                    <div class="admin-app-kicker">Application Review</div>
                    <h1>Application Details</h1>
                    <p class="admin-app-subtitle">{{ $submittedSummary }}</p>
                </div>
                <div class="admin-app-header-meta">
                    <span class="admin-app-status {{ $statusClass }}">{{ $statusLabel }}</span>
                    <div class="admin-app-ref">{{ $application->reference_no }}</div>
                    <div class="admin-app-header-actions">
                        <a href="{{ route('admin.reports.print.application', $application) }}" class="btn">Print Summary</a>
                        @if($installation)
                            <a href="{{ route('admin.reports.print.installation', $installation) }}" class="btn secondary">Print Installation</a>
                        @endif
                        <a href="{{ route('admin.applications.index') }}" class="btn secondary">Back to Applications</a>
                    </div>
                </div>
            </div>
            <div class="admin-app-header-facts" aria-label="Application highlights">
                <div class="admin-app-header-fact"><span>Customer</span><strong>{{ $fullName ?: 'Not provided' }}</strong></div>
                <div class="admin-app-header-fact"><span>Plan</span><strong>{{ $application->planDisplayName() ?: 'Not provided' }}</strong></div>
                <div class="admin-app-header-fact"><span>Technician</span><strong>{{ $technicianName }}</strong></div>
                <div class="admin-app-header-fact"><span>Schedule</span><strong>{{ $scheduleLabel }}</strong></div>
            </div>
        </section>

        <x-next-action :message="$application->nextActionGuidance()" :tone="$application->nextActionTone()" />

        <nav class="admin-app-workspace-nav" aria-label="Application detail sections">
            <div class="admin-app-workspace-tabs">
                <button type="button" class="admin-app-workspace-tab" data-workspace-tab="overview" aria-pressed="false">Overview</button>
                <button type="button" class="admin-app-workspace-tab" data-workspace-tab="documents" aria-pressed="false">Documents <span class="admin-app-workspace-tab-count">{{ $documentCount }}</span></button>
                <button type="button" class="admin-app-workspace-tab" data-workspace-tab="location" aria-pressed="false">Location</button>
                @if($showAdminActionCard)
                    <button type="button" class="admin-app-workspace-tab" data-workspace-tab="actions" aria-pressed="false">Actions</button>
                @endif
                <button type="button" class="admin-app-workspace-tab" data-workspace-tab="history" aria-pressed="false">History <span class="admin-app-workspace-tab-count">{{ $applicationTimeline->count() }}</span></button>
            </div>
            <label class="admin-app-workspace-select">
                <span>View section</span>
                <select data-workspace-select>
                    <option value="overview">Overview</option>
                    <option value="documents">Documents ({{ $documentCount }})</option>
                    <option value="location">Location</option>
                    @if($showAdminActionCard)<option value="actions">Actions</option>@endif
                    <option value="history">History ({{ $applicationTimeline->count() }})</option>
                </select>
            </label>
            <span class="admin-app-workspace-live" data-workspace-live aria-live="polite"></span>
        </nav>

        <div class="admin-app-layout" data-workspace-layout>
            <div class="admin-app-column">
                <section class="admin-app-card admin-app-panel--overview" data-app-workspace-panel="overview">
                    <div class="admin-app-card-head">
                        <h2>Progress Tracker</h2>
                    </div>

                    <div class="admin-app-progress" aria-label="Application progress">
                        @foreach($progressSteps as $stepKey => $step)
                            @php
                                $stepNumber = $loop->iteration;
                                $isComplete = ! $isRejectedState && $stepNumber < $currentStepIndex;
                                $isActive = ! $isRejectedState && $stepNumber === $currentStepIndex;
                                $stepStateLabel = $isComplete ? 'Completed' : ($isActive ? 'Current' : 'Pending');
                            @endphp
                            <div class="admin-app-progress-step {{ $isComplete ? 'is-complete' : '' }} {{ $isActive ? 'is-active' : '' }}">
                                <div class="admin-app-progress-marker">
                                    <span class="admin-app-progress-dot" aria-hidden="true">{{ $stepNumber }}</span>
                                    <span class="admin-app-progress-line" aria-hidden="true"></span>
                                </div>
                                <div class="admin-app-progress-copy">
                                    <span class="admin-app-progress-label">{{ $stepStateLabel }}</span>
                                    <strong>{{ $step['label'] }}</strong>
                                    <p>{{ $step['description'] }}</p>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </section>

                <section class="admin-app-card admin-app-panel--overview" data-app-workspace-panel="overview">
                    <div class="admin-app-card-head">
                        <h2>Customer Information</h2>
                    </div>

                    <div class="admin-app-detail-grid">
                        <div class="admin-app-detail">
                            <span class="admin-app-label">Full name</span>
                            <div class="admin-app-value">{{ $fullName ?: 'Not provided' }}</div>
                        </div>
                        <div class="admin-app-detail">
                            <span class="admin-app-label">Selected plan</span>
                            <div class="admin-app-value">{{ $application->planDisplayName() ?: 'Not provided' }}</div>
                        </div>
                        <div class="admin-app-detail">
                            <span class="admin-app-label">Email</span>
                            <div class="admin-app-value">{{ $application->email ?: 'Not provided' }}</div>
                        </div>
                        <div class="admin-app-detail">
                            <span class="admin-app-label">Submitted date</span>
                            <div class="admin-app-value">{{ $application->created_at->displayDateTime('F d, Y h:i A') }}</div>
                        </div>
                        <div class="admin-app-detail admin-app-detail--full">
                            <span class="admin-app-label">Mobile number</span>
                            <div class="admin-app-value">{{ $application->mobile_number ?: 'Not provided' }}</div>
                        </div>
                    </div>
                </section>

                <section class="admin-app-card admin-app-panel--documents" data-app-workspace-panel="documents">
                    <div class="admin-id-head">
                        <div class="admin-app-card-head">
                            <div><h2>Identity Verification</h2><p class="admin-app-helper">Review the secured photos and confirm the short checklist. No ID-number encoding is required.</p></div>
                        </div>
                        <span class="admin-id-status {{ $idVerificationClass }}">{{ $idVerificationLabel }}</span>
                    </div>

                    <div class="admin-id-summary">
                        <div><span>Declared ID type</span><strong>{{ $application->validIdTypeLabel() }}</strong></div>
                        <div>
                            <span>Exact image reuse check</span>
                            <strong>{{ $application->duplicate_id_image_detected ? 'Possible match — review required' : 'No duplicate detected' }}</strong>
                        </div>
                        <div><span>Last reviewed</span><strong>{{ $application->id_verified_at?->displayDateTime('M d, Y h:i A') ?? 'Waiting for review' }}</strong></div>
                    </div>

                    @if($application->id_verification_status !== null)
                        <div class="admin-id-comparison">
                            <div class="admin-id-comparison__head">
                                <strong>Details to compare with the ID</strong>
                                <span>Manual review</span>
                            </div>
                            <div class="admin-id-comparison__grid">
                                <div><span>Application name</span><strong>{{ $fullName ?: 'Not provided' }}</strong></div>
                                <div><span>Service address</span><strong>{{ $application->fullServiceAddress() ?: 'Not provided' }}</strong></div>
                            </div>
                            <p>Name variations, abbreviations, or an older address require administrator judgment. A difference is a warning and does not reject the application automatically.</p>
                        </div>
                    @endif

                    @if($application->id_verification_status === null)
                        <div class="admin-id-legacy">
                            <strong>Legacy application</strong>
                            @if($isReviewState)
                                This application was submitted before structured ID review was recorded. It remains viewable, but it must now pass identity verification before it can be approved.
                            @else
                                This application progressed before structured ID review was recorded. Its historical workflow remains available, but all new approval decisions require verified identity documents.
                            @endif
                        </div>
                    @endif

                    @if($application->duplicate_id_image_detected)
                        <div class="admin-id-warning" role="alert">
                            <strong>Possible identity reuse detected</strong>
                            The exact close-up ID image previously appeared under another account. Compare the submissions before verifying.
                            @if($identityDuplicates->isNotEmpty())
                                <div class="admin-id-matches" aria-label="Potential duplicate applications">
                                    @foreach($identityDuplicates as $duplicate)
                                        <a href="{{ route('admin.applications.show', $duplicate) }}">{{ $duplicate->reference_no }}</a>
                                    @endforeach
                                </div>
                            @endif
                        </div>
                    @endif

                    <x-photo-gallery
                        :photo-urls="[
                            'Close-up of valid ID' => $application->idImageUrl(),
                            'Customer holding the same ID' => $application->idSelfieImageUrl(),
                        ]"
                        title="Identity photos"
                        empty="No valid ID photos uploaded."
                        button-text="View identity photos"
                        class="admin-app-document-gallery"
                    />

                    @if($identityIsVerified)
                        <div class="admin-id-result admin-id-result--verified" role="status">
                            <span class="admin-id-result__icon" aria-hidden="true">&#10003;</span>
                            <div>
                                <strong>Identity verified</strong>
                                <p>
                                    Confirmed by {{ $application->idVerifier?->name ?? 'an administrator' }}
                                    {{ $application->id_verified_at ? 'on '.$application->id_verified_at->displayDateTime('M d, Y h:i A') : '' }}.
                                    {{ $isReviewState ? 'This application can now proceed to the approval checks.' : 'This decision is locked because the application has already progressed.' }}
                                </p>
                            </div>
                        </div>
                    @elseif($identityIsRejected && $isReviewState)
                        <div class="admin-id-result admin-id-result--rejected" role="status">
                            <span class="admin-id-result__icon" aria-hidden="true">!</span>
                            <div>
                                <strong>ID resubmission requested</strong>
                                <p>The application cannot be approved until the customer submits replacement photos and an administrator verifies them.</p>
                            </div>
                        </div>
                    @elseif($identityNeedsApprovedRecordCorrection)
                        <div class="admin-id-result admin-id-result--blocked" role="alert">
                            <span class="admin-id-result__icon" aria-hidden="true">!</span>
                            <div>
                                <strong>Identity verification required</strong>
                                <p>
                                    This application already progressed without a verified ID.
                                    {{ $isApprovedState ? 'Technician scheduling is locked.' : 'The identity state is inconsistent with the application workflow.' }}
                                    Review the documents below and verify the ID to correct the record.
                                </p>
                            </div>
                        </div>
                    @elseif($identityIsRejected)
                        <div class="admin-id-result admin-id-result--rejected" role="status">
                            <span class="admin-id-result__icon" aria-hidden="true">!</span>
                            <div>
                                <strong>Identity submission rejected</strong>
                                <p>The recorded ID decision is retained with this closed application.</p>
                            </div>
                        </div>
                    @elseif($idVerificationStatus === null && ! $isReviewState)
                        <div class="admin-id-result admin-id-result--pending" role="status">
                            <span class="admin-id-result__icon" aria-hidden="true">i</span>
                            <div>
                                <strong>Legacy review state</strong>
                                <p>No structured identity decision was recorded when this application progressed. The record is preserved under the earlier workflow.</p>
                            </div>
                        </div>
                    @else
                        <div class="admin-id-result admin-id-result--pending" role="status">
                            <span class="admin-id-result__icon" aria-hidden="true">i</span>
                            <div>
                                <strong>Waiting for administrator review</strong>
                                <p>Complete all three checks below. Approval stays locked until the ID is verified.</p>
                            </div>
                        </div>
                    @endif

                    @if(filled($application->id_verification_reason))
                        <div class="admin-id-note">
                            <strong>Latest review note</strong>
                            {{ $application->id_verification_reason }}
                        </div>
                    @endif

                    @if(! $identityIsVerified && $hasIdentityDocuments && ($isReviewState || $identityNeedsApprovedRecordCorrection))
                        <form method="POST" action="{{ route('admin.applications.identity-verification', $application) }}" class="admin-id-review-form" data-protect-form data-warn-unsaved>
                            @csrf
                            @method('PATCH')
                            <fieldset class="admin-id-checklist">
                                <legend>Administrator review checklist</legend>
                                <p>Complete these checks before selecting Verify ID. They are not required when rejecting an unusable submission.</p>
                                <label class="admin-id-check">
                                    <input type="checkbox" name="review_document_readable" value="1" @checked(old('review_document_readable'))>
                                    <span><strong>ID is readable</strong><small>The ID type, name, portrait, and expiry date where applicable can be reviewed.</small></span>
                                </label>
                                <label class="admin-id-check">
                                    <input type="checkbox" name="review_name_matches" value="1" @checked(old('review_name_matches'))>
                                    <span><strong>Name is consistent</strong><small>The name on the ID reasonably matches the submitted application.</small></span>
                                </label>
                                <label class="admin-id-check">
                                    <input type="checkbox" name="review_photo_matches" value="1" @checked(old('review_photo_matches'))>
                                    <span><strong>Photos are consistent</strong><small>The ID portrait reasonably matches the customer holding the same ID.</small></span>
                                </label>
                                @error('review_document_readable')<span class="error">{{ $message }}</span>@enderror
                                @error('review_name_matches')<span class="error">{{ $message }}</span>@enderror
                                @error('review_photo_matches')<span class="error">{{ $message }}</span>@enderror
                            </fieldset>
                            <label for="id_verification_reason">Review reason or note</label>
                            <textarea id="id_verification_reason" name="id_verification_reason" maxlength="1000" placeholder="Required when rejecting an ID or approving a possible duplicate.">{{ old('id_verification_reason') }}</textarea>
                            <p class="admin-app-helper">Record a reason when rejecting or approving an exact-image duplicate. Address differences may be noted but do not automatically reject the application.</p>
                            @error('id_verification_status')<span class="error">{{ $message }}</span>@enderror
                            @error('id_verification_reason')<span class="error">{{ $message }}</span>@enderror
                            <div class="admin-id-actions">
                                <button type="submit" name="id_verification_status" value="verified" class="btn" data-confirm="Confirm that you reviewed the submitted ID and want to mark it as verified?" data-confirm-title="Verify identity documents?" data-confirm-label="Verify ID">
                                    {{ $identityNeedsApprovedRecordCorrection ? 'Verify ID and Unlock Scheduling' : ($identityIsRejected ? 'Correct Decision and Verify ID' : 'Verify ID') }}
                                </button>
                                @if($isReviewState && ! $identityIsRejected)
                                    <button type="submit" name="id_verification_status" value="rejected" class="btn danger" data-confirm="The customer will be asked to upload new identity documents before this application can proceed." data-confirm-title="Request replacement documents?" data-confirm-label="Request Resubmission">Request ID Resubmission</button>
                                @endif
                            </div>
                        </form>
                    @elseif($identityIsVerified && $isReviewState)
                        <details class="admin-id-correction">
                            <summary>Correct this verification decision</summary>
                            <form method="POST" action="{{ route('admin.applications.identity-verification', $application) }}" class="admin-id-review-form" data-protect-form data-warn-unsaved>
                                @csrf
                                @method('PATCH')
                                <label for="id_verification_correction_reason">Reason for requesting replacement documents</label>
                                <textarea id="id_verification_correction_reason" name="id_verification_reason" maxlength="1000" required placeholder="Explain why the verified submission needs to be replaced.">{{ old('id_verification_reason') }}</textarea>
                                <button type="submit" name="id_verification_status" value="rejected" class="btn danger" data-confirm="This will reverse the verified decision and require the customer to upload new identity documents." data-confirm-title="Change verification decision?" data-confirm-label="Require Resubmission">Change to ID Resubmission Required</button>
                            </form>
                        </details>
                    @elseif(! $hasIdentityDocuments && ! $identityIsVerified && ($isReviewState || $identityNeedsApprovedRecordCorrection))
                        <div class="admin-id-result admin-id-result--blocked" role="alert">
                            <span class="admin-id-result__icon" aria-hidden="true">!</span>
                            <div>
                                <strong>Identity photos are missing</strong>
                                <p>This record cannot be approved. Keep the legacy record for history and have the customer submit a current application with the required identity photos.</p>
                            </div>
                        </div>
                    @endif

                    @if($application->idVerificationEvents->isNotEmpty())
                        <details class="admin-id-history">
                            <summary>Verification history ({{ $application->idVerificationEvents->count() }})</summary>
                            <div class="admin-id-history-list">
                                @foreach($application->idVerificationEvents as $event)
                                    <div class="admin-id-history-item">
                                        <strong>{{ ucfirst($event->from_status ?? 'Not reviewed') }} → {{ ucfirst($event->to_status) }}</strong>
                                        @if(filled($event->reason))<p>{{ $event->reason }}</p>@endif
                                        <small>{{ $event->created_at->displayDateTime('M d, Y h:i A') }} · {{ $event->actor?->name ?? 'System' }}</small>
                                    </div>
                                @endforeach
                            </div>
                        </details>
                    @endif
                </section>

                <section class="admin-app-card admin-app-panel--location" data-app-workspace-panel="location">
                    <div class="admin-app-card-head">
                        <div>
                            <h2>Service Location</h2>
                            <p class="admin-app-helper">Review the service address and pinned installation site together.</p>
                        </div>
                    </div>

                    <div class="admin-app-location-overview">
                        <div>
                            <strong>{{ $fullAddress }}</strong>
                            <span>{{ $application->barangay ?: 'Barangay not provided' }}, {{ $application->municipality ?: 'Municipality not provided' }}</span>
                        </div>
                        @if($hasLocation)
                            <button type="button" class="btn secondary" data-open-map-modal>Open Full Map</button>
                        @endif
                    </div>

                    <div class="admin-app-detail-grid">
                        <div class="admin-app-detail">
                            <span class="admin-app-label">Region</span>
                            <div class="admin-app-value">{{ $application->region ?: 'Not provided' }}</div>
                        </div>
                        <div class="admin-app-detail">
                            <span class="admin-app-label">Province</span>
                            <div class="admin-app-value">{{ $application->province ?: 'Not provided' }}</div>
                        </div>
                        <div class="admin-app-detail">
                            <span class="admin-app-label">Municipality / City</span>
                            <div class="admin-app-value">{{ $application->municipality ?: 'Not provided' }}</div>
                        </div>
                        <div class="admin-app-detail">
                            <span class="admin-app-label">Barangay</span>
                            <div class="admin-app-value">{{ $application->barangay ?: 'Not provided' }}</div>
                        </div>
                    </div>

                    @if($hasLocation)
                        <div class="admin-app-location-shell">
                            <div id="adminApplicationInlineMap" class="js-readonly-location-map admin-app-map" data-lat="{{ $locationLat }}" data-lng="{{ $locationLng }}" data-popup="{{ $fullAddress }}" data-empty-label="No pinned location available."></div>
                            <details class="admin-app-coordinate-disclosure">
                                <summary>Show technical coordinates</summary>
                                <div class="admin-app-location-meta">
                                    <span>Latitude: {{ number_format($locationLat, 6) }}</span>
                                    <span>Longitude: {{ number_format($locationLng, 6) }}</span>
                                    <a href="{{ $osmUrl }}" target="_blank" rel="noopener noreferrer">Open in OpenStreetMap</a>
                                </div>
                            </details>
                        </div>
                    @else
                        <div class="admin-app-empty">
                            <span class="admin-app-inline-label">Location unavailable</span>
                            <p class="admin-app-helper">No pinned location is available for this application.</p>
                        </div>
                    @endif
                </section>
            </div>

            <aside class="admin-app-column">
                <section class="admin-app-card admin-app-panel--overview" data-app-workspace-panel="overview">
                    <div class="admin-app-card-head">
                        <h2>Installation Summary</h2>
                    </div>

                    <div class="admin-app-summary-grid">
                        <div class="admin-app-mini-card admin-app-mini-card--status">
                            <span class="admin-app-label">Status</span>
                            <strong>{{ $installationStatusLabel }}</strong>
                            <span class="admin-app-summary-note">Application {{ $statusLabel }} - {{ $application->reference_no }}</span>
                        </div>

                        <div class="admin-app-mini-card">
                            <span class="admin-app-label">Technician</span>
                            <strong>{{ $technicianName }}</strong>
                            <span class="admin-app-summary-note">{{ $technicianEmail }}</span>
                        </div>

                        <div class="admin-app-mini-card">
                            <span class="admin-app-label">Schedule</span>
                            <strong>{{ $scheduleLabel }}</strong>
                            <span class="admin-app-summary-note">Installation visit</span>
                        </div>

                        @if($hasPendingCustomerRescheduleRequest)
                            <div class="admin-app-mini-card admin-app-mini-card--full">
                                <span class="admin-app-label">Customer requested reschedule</span>
                                <strong>{{ $reschedulePreferredLabel }}</strong>
                                <span class="admin-app-summary-note">{{ $installation->reschedule_request_reason ?: 'No reason recorded.' }}</span>
                            </div>
                        @elseif($installation?->reschedule_reviewed_at && filled($installation->reschedule_rejection_reason))
                            <div class="admin-app-mini-card admin-app-mini-card--full">
                                <span class="admin-app-label">Latest reschedule rejection</span>
                                <strong>{{ $installation->reschedule_reviewed_at->displayDateTime('F d, Y h:i A') }}</strong>
                                <span class="admin-app-summary-note">{{ $installation->reschedule_rejection_reason }}</span>
                            </div>
                        @endif

                        @if($isCancellationRequestedState || $isCancelledState || filled($cancellationReason))
                            <div class="admin-app-mini-card admin-app-mini-card--full">
                                <span class="admin-app-label">Customer cancellation reason</span>
                                <div class="admin-app-value">{{ $cancellationReason ?: 'No reason recorded.' }}</div>
                            </div>
                        @endif

                        @if($installation?->completed_at)
                            <div class="admin-app-mini-card admin-app-mini-card--full">
                                <span class="admin-app-label">Completed at</span>
                                <strong>{{ $completedAtLabel }}</strong>
                            </div>
                        @endif

                        @if($installation?->activation_date)
                            <div class="admin-app-mini-card admin-app-mini-card--full">
                                <span class="admin-app-label">Activation date</span>
                                <strong>{{ $activationDateLabel }}</strong>
                            </div>
                        @endif

                        @if(filled($installation?->technician_remarks))
                            <div class="admin-app-mini-card admin-app-mini-card--full">
                                <span class="admin-app-label">Technician remarks</span>
                                <div class="admin-app-value">{{ $installation->technician_remarks }}</div>
                            </div>
                        @endif

                        @if($hasTechnicalDetails)
                            <div class="admin-app-mini-card admin-app-mini-card--full">
                                <span class="admin-app-label">Technical Installation Details</span>
                                <div class="admin-app-detail-grid">
                                    @foreach($technicalDetails as $label => $value)
                                        <div class="admin-app-detail">
                                            <span class="admin-app-label">{{ $label }}</span>
                                            <div class="admin-app-value">{{ $value ?: '-' }}</div>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        @endif
                    </div>
                </section>

                @if($showAdminActionCard)
                    <section class="admin-app-card admin-app-panel--actions" data-app-workspace-panel="actions">
                        <div class="admin-app-card-head">
                            <h2>
                                @if($isReviewState)
                                    Review Decision
                                @elseif($isApprovedState)
                                    Technician Scheduling
                                @elseif($isCancellationRequestedState)
                                    Cancellation Review
                                @elseif($hasPendingCustomerRescheduleRequest)
                                    Customer Reschedule Review
                                @elseif($isRescheduleRequiredState)
                                    Reschedule Installation
                                @elseif($isInstallationScheduled)
                                    Installation Controls
                                @else
                                    Review Outcome
                                @endif
                            </h2>
                        </div>

                        @if($showReviewSummary)
                            <div class="admin-app-summary-box">
                                <div class="admin-app-detail-grid">
                                    @foreach($reviewSummaryRows as $label => $value)
                                        <div class="admin-app-detail">
                                            <span class="admin-app-label">{{ $label }}</span>
                                            <div class="admin-app-value">{{ $value ?: 'Not recorded' }}</div>
                                        </div>
                                    @endforeach
                                    @if(filled($application->assessment_notes))
                                        <div class="admin-app-detail admin-app-detail--full">
                                            <span class="admin-app-label">Assessment notes</span>
                                            <div class="admin-app-value">{{ $application->assessment_notes }}</div>
                                        </div>
                                    @endif
                                </div>

                                @if($isRejectedState && filled($application->rejection_reason))
                                    <div class="admin-app-reject-box">
                                        <strong>Rejection Reason</strong>
                                        <p class="admin-app-helper">{{ $application->rejection_reason }}</p>
                                    </div>
                                @endif
                            </div>
                        @endif

                        @if($isReviewState)
                            <form method="POST" action="{{ route('admin.applications.status', $application) }}" class="admin-app-form" id="applicationDecisionForm" data-protect-form data-warn-unsaved>
                                @csrf
                                @method('PATCH')

                                <div class="admin-app-form-grid">
                                    <div class="admin-app-field">
                                        <label for="serviceability_status">Serviceability</label>
                                        <select id="serviceability_status" name="serviceability_status" required>
                                            <option value="pending" @selected($serviceabilityStatus === 'pending')>Pending Assessment</option>
                                            <option value="serviceable" @selected($serviceabilityStatus === 'serviceable')>Serviceable</option>
                                            <option value="not_serviceable" @selected($serviceabilityStatus === 'not_serviceable')>Not Serviceable</option>
                                        </select>
                                        @error('serviceability_status')
                                            <span class="error">{{ $message }}</span>
                                        @enderror
                                    </div>

                                    <div class="admin-app-field">
                                        <label for="slot_availability_status">Slot availability</label>
                                        <select id="slot_availability_status" name="slot_availability_status" required>
                                            <option value="pending" @selected($slotAvailabilityStatus === 'pending')>Pending Check</option>
                                            <option value="available" @selected($slotAvailabilityStatus === 'available')>Available</option>
                                            <option value="full" @selected($slotAvailabilityStatus === 'full')>Full</option>
                                            <option value="not_applicable" @selected($slotAvailabilityStatus === 'not_applicable')>Not Applicable</option>
                                        </select>
                                        @error('slot_availability_status')
                                            <span class="error">{{ $message }}</span>
                                        @enderror
                                    </div>

                                    <div class="admin-app-field admin-app-field--full">
                                        <label for="assessment_notes">Assessment notes</label>
                                        <textarea id="assessment_notes" name="assessment_notes" rows="4" maxlength="2000" placeholder="Document serviceability checks, site constraints, or slot observations.">{{ $assessmentNotes }}</textarea>
                                        @error('assessment_notes')
                                            <span class="error">{{ $message }}</span>
                                        @enderror
                                    </div>

                                    <div class="admin-app-field admin-app-field--full {{ $showRejectionReason ? '' : 'is-hidden' }}" id="rejectionReasonField">
                                        <label for="rejection_reason">Rejection reason</label>
                                        <textarea id="rejection_reason" name="rejection_reason" rows="3" placeholder="State clearly why the application is being rejected.">{{ $rejectionReason }}</textarea>
                                        <p class="admin-app-helper">Required when rejecting the application.</p>
                                        @error('rejection_reason')
                                            <span class="error">{{ $message }}</span>
                                        @enderror
                                    </div>
                                </div>

                                @error('status')
                                    <span class="error">{{ $message }}</span>
                                @enderror

                                @unless($identityIsVerified)
                                    <div class="admin-id-approval-lock" role="status">
                                        <div>
                                            <strong>Approval locked: identity verification is incomplete</strong>
                                            <span>Open Documents, review the ID photos, and verify the ID first.</span>
                                        </div>
                                        <a href="#documents" class="btn secondary">Review Identity</a>
                                    </div>
                                @endunless

                                <div class="admin-app-actions admin-app-actions--decision">
                                    <button type="submit" name="status" value="approved" class="btn success" data-status-action="approved" data-confirm="Approve this verified application and move it to technician scheduling?" @disabled(! $identityIsVerified) title="{{ $identityIsVerified ? 'Approve this application' : 'Verify the identity documents before approval' }}">Approve</button>
                                    <button type="submit" name="status" value="under_review" class="btn secondary" data-status-action="under_review">Save as Under Review</button>
                                    <button type="submit" name="status" value="rejected" class="btn danger" data-status-action="rejected" data-confirm="Reject this application with the recorded reason?">Reject</button>
                                </div>
                            </form>
                        @elseif($isApprovedState && $identityAllowsScheduling)
                            <form method="POST" action="{{ route('admin.applications.schedule', $application) }}" class="admin-app-form admin-app-scheduling-form" data-protect-form data-warn-unsaved>
                                @csrf

                                <div class="admin-app-form-grid">
                                    <div class="admin-app-field admin-app-field--full" data-route-technician-field>
                                        <label for="technician_id">Technician</label>
                                        <select id="technician_id" name="technician_id" required>
                                            <option value="">Select Technician</option>
                                            @foreach($technicians as $techUser)
                                                <option value="{{ $techUser->id }}" @selected((string) $selectedTechnicianId === (string) $techUser->id)>
                                                    {{ $techUser->name }} ({{ $techUser->email }})
                                                </option>
                                            @endforeach
                                        </select>
                                        <p class="admin-app-helper">Only technician accounts are available for assignment.</p>
                                        @error('technician_id')
                                            <span class="error">{{ $message }}</span>
                                        @enderror
                                    </div>

                                    <div class="admin-app-field">
                                        <label for="installation_date">Installation date</label>
                                        @include('admin.applications.partials.schedule-date-select', ['selectedDate' => $selectedInstallationDate, 'required' => true])
                                        @error('installation_date')
                                            <span class="error">{{ $message }}</span>
                                        @enderror
                                    </div>

                                    <div class="admin-app-field">
                                        <label for="installation_time">Installation time</label>
                                        <select id="installation_time" name="installation_time" required>
                                            <option value="">Select installation time</option>
                                            @foreach($availableTimeSlots as $timeSlot)
                                                <option value="{{ $timeSlot }}" @selected($selectedInstallationTime === $timeSlot)>{{ \App\Models\Installation::slotLabel($timeSlot) }}</option>
                                            @endforeach
                                        </select>
                                        @error('installation_time')
                                            <span class="error">{{ $message }}</span>
                                        @enderror
                                    </div>
                                </div>

                                @include('admin.applications.partials.route-aware-scheduler')

                                <p class="admin-app-helper admin-route-planner__hint" id="installationTimeHint">Choose a date and time to compare available technicians.</p>

                                @error('schedule')
                                    <span class="error">{{ $message }}</span>
                                @enderror

                                <div>
                                    <button class="btn">Save Schedule</button>
                                </div>
                            </form>
                        @elseif($isApprovedState)
                            <div class="admin-id-approval-lock admin-id-approval-lock--critical" role="alert">
                                <div>
                                    <strong>Technician scheduling is locked</strong>
                                    <span>This approved record does not have a verified ID. Correct the identity review before assigning a technician or installation schedule.</span>
                                </div>
                                <a href="#documents" class="btn secondary">Correct Identity Review</a>
                            </div>
                            @error('schedule')
                                <span class="error">{{ $message }}</span>
                            @enderror
                        @elseif($hasPendingCustomerRescheduleRequest && $installation)
                            <form method="POST" action="{{ route('admin.applications.reschedule', $application) }}" class="admin-app-form admin-app-scheduling-form" data-protect-form data-warn-unsaved>
                                @csrf
                                @method('PATCH')

                                <div class="admin-app-summary-box">
                                    <div class="admin-app-detail-grid">
                                        <div class="admin-app-detail">
                                            <span class="admin-app-label">Customer preferred schedule</span>
                                            <div class="admin-app-value">{{ $reschedulePreferredLabel }}</div>
                                        </div>
                                        <div class="admin-app-detail">
                                            <span class="admin-app-label">Requested at</span>
                                            <div class="admin-app-value">{{ $installation->reschedule_requested_at?->displayDateTime('F d, Y h:i A') ?? 'Not recorded' }}</div>
                                        </div>
                                        <div class="admin-app-detail admin-app-detail--full">
                                            <span class="admin-app-label">Customer reason</span>
                                            <div class="admin-app-value">{{ $installation->reschedule_request_reason ?: 'No reason recorded.' }}</div>
                                        </div>
                                    </div>
                                </div>

                                <div class="admin-app-form-grid">
                                    <div class="admin-app-field admin-app-field--full" data-route-technician-field>
                                        <label for="technician_id">Technician</label>
                                        <select id="technician_id" name="technician_id">
                                            <option value="">Select Technician</option>
                                            @foreach($technicians as $techUser)
                                                <option value="{{ $techUser->id }}" @selected((string) $selectedTechnicianId === (string) $techUser->id)>
                                                    {{ $techUser->name }} ({{ $techUser->email }})
                                                </option>
                                            @endforeach
                                        </select>
                                        <p class="admin-app-helper">Approving the request updates the installation to the selected schedule.</p>
                                        @error('technician_id')
                                            <span class="error">{{ $message }}</span>
                                        @enderror
                                    </div>

                                    <div class="admin-app-field">
                                        <label for="installation_date">Installation date</label>
                                        @include('admin.applications.partials.schedule-date-select', ['selectedDate' => $selectedRescheduleDate, 'required' => false])
                                        @error('installation_date')
                                            <span class="error">{{ $message }}</span>
                                        @enderror
                                    </div>

                                    <div class="admin-app-field">
                                        <label for="installation_time">Installation time</label>
                                        <select id="installation_time" name="installation_time">
                                            <option value="">Select installation time</option>
                                            @foreach($availableTimeSlots as $timeSlot)
                                                <option value="{{ $timeSlot }}" @selected($selectedRescheduleTime === $timeSlot)>{{ \App\Models\Installation::slotLabel($timeSlot) }}</option>
                                            @endforeach
                                        </select>
                                        @error('installation_time')
                                            <span class="error">{{ $message }}</span>
                                        @enderror
                                    </div>

                                    <div class="admin-app-field admin-app-field--full">
                                        <label for="customer_reschedule_reason">Reason shown to customer</label>
                                        <textarea id="customer_reschedule_reason" name="customer_reschedule_reason" rows="3" maxlength="1000" >{{ old('customer_reschedule_reason', $installation->reschedule_request_reason) }}</textarea>
                                        <p class="admin-app-helper">Required when approving. This explanation is sent to the customer. Do not include internal staff notes.</p>
                                        @error('customer_reschedule_reason') <span class="error">{{ $message }}</span> @enderror
                                    </div>
                                    <div class="admin-app-field admin-app-field--full">
                                        <label for="reschedule_rejection_reason">Rejection reason</label>
                                        <textarea id="reschedule_rejection_reason" name="reschedule_rejection_reason" rows="3" placeholder="Required when rejecting the reschedule request.">{{ old('reschedule_rejection_reason') }}</textarea>
                                        @error('reschedule_rejection_reason')
                                            <span class="error">{{ $message }}</span>
                                        @enderror
                                    </div>
                                </div>

                                @include('admin.applications.partials.route-aware-scheduler')

                                <p class="admin-app-helper admin-route-planner__hint" id="installationTimeHint">Choose a date and time to compare available technicians.</p>

                                @error('reschedule')
                                    <span class="error">{{ $message }}</span>
                                @enderror

                                <div class="admin-app-actions">
                                    <button type="submit" name="decision" value="approve" class="btn success">Approve Reschedule</button>
                                    <button type="submit" name="decision" value="reject" class="btn danger">Reject Request</button>
                                </div>
                            </form>
                        @elseif($isCancellationRequestedState)
                            <form method="POST" action="{{ route('admin.applications.cancellation', $application) }}" class="admin-app-form" data-protect-form data-warn-unsaved>
                                @csrf
                                @method('PATCH')

                                <div class="admin-app-summary-box">
                                    <div class="admin-app-detail">
                                        <span class="admin-app-label">Customer cancellation reason</span>
                                        <div class="admin-app-value">{{ $cancellationReason ?: 'No reason recorded.' }}</div>
                                    </div>
                                    @if($installation)
                                        <div class="admin-app-detail">
                                            <span class="admin-app-label">State to restore if rejected</span>
                                            <div class="admin-app-value">Application: {{ ucfirst(str_replace('_', ' ', $application->cancellation_previous_status ?: 'scheduled')) }}; Installation: {{ ucfirst(str_replace('_', ' ', $installation->cancellation_previous_status ?: 'scheduled')) }}</div>
                                        </div>
                                        <div class="admin-app-detail">
                                            <span class="admin-app-label">Technician Stop Work notes</span>
                                            <div class="admin-app-value">{{ $installation->stop_work_technician_notes ?: 'The technician has not added notes yet.' }}</div>
                                        </div>
                                    @endif
                                </div>

                                <div class="admin-app-field">
                                    <label for="cancellation_notes">Admin notes</label>
                                    <textarea id="cancellation_notes" name="cancellation_notes" rows="3" placeholder="Optional notes for the cancellation decision.">{{ old('cancellation_notes') }}</textarea>
                                    @error('cancellation_notes')
                                        <span class="error">{{ $message }}</span>
                                    @enderror
                                </div>

                                @error('cancellation')
                                    <span class="error">{{ $message }}</span>
                                @enderror

                                <div class="admin-app-actions">
                                    <button type="submit" name="decision" value="approve" class="btn danger">Approve Stop Work / Cancellation</button>
                                    <button type="submit" name="decision" value="reject" class="btn secondary">Reject Request</button>
                                </div>
                            </form>
                        @elseif($isRescheduleRequiredState && $installation)
                            <form method="POST" action="{{ route('admin.installations.reschedule', $installation) }}" class="admin-app-form admin-app-scheduling-form" data-protect-form data-warn-unsaved>
                                @csrf
                                @method('PATCH')

                                <div class="admin-app-form-grid">
                                    <div class="admin-app-field admin-app-field--full" data-route-technician-field>
                                        <label for="technician_id">Technician</label>
                                        <select id="technician_id" name="technician_id" required>
                                            <option value="">Select Technician</option>
                                            @foreach($technicians as $techUser)
                                                <option value="{{ $techUser->id }}" @selected((string) $selectedTechnicianId === (string) $techUser->id)>
                                                    {{ $techUser->name }} ({{ $techUser->email }})
                                                </option>
                                            @endforeach
                                        </select>
                                        @error('technician_id')
                                            <span class="error">{{ $message }}</span>
                                        @enderror
                                    </div>

                                    <div class="admin-app-field">
                                        <label for="installation_date">Installation date</label>
                                        @include('admin.applications.partials.schedule-date-select', ['selectedDate' => $selectedInstallationDate, 'required' => true])
                                        @error('installation_date')
                                            <span class="error">{{ $message }}</span>
                                        @enderror
                                    </div>

                                    <div class="admin-app-field">
                                        <label for="installation_time">Installation time</label>
                                        <select id="installation_time" name="installation_time" required>
                                            <option value="">Select installation time</option>
                                            @foreach($availableTimeSlots as $timeSlot)
                                                <option value="{{ $timeSlot }}" @selected($selectedInstallationTime === $timeSlot)>{{ \App\Models\Installation::slotLabel($timeSlot) }}</option>
                                            @endforeach
                                        </select>
                                        @error('installation_time')
                                            <span class="error">{{ $message }}</span>
                                        @enderror
                                    </div>
                                    <div class="admin-app-field admin-app-field--full">
                                        <label for="customer_reschedule_reason">Reason shown to customer</label>
                                        <textarea id="customer_reschedule_reason" name="customer_reschedule_reason" rows="3" maxlength="1000" required>{{ old('customer_reschedule_reason', '') }}</textarea>
                                        <p class="admin-app-helper">Required before rescheduling. This explanation is sent to the customer. Do not include internal staff notes.</p>
                                        @error('customer_reschedule_reason') <span class="error">{{ $message }}</span> @enderror
                                    </div>
                                </div>

                                @include('admin.applications.partials.route-aware-scheduler')

                                <p class="admin-app-helper admin-route-planner__hint" id="installationTimeHint">Choose a date and time to compare available technicians.</p>

                                @error('reschedule')
                                    <span class="error">{{ $message }}</span>
                                @enderror

                                <div>
                                    <button class="btn">Reschedule Installation</button>
                                </div>
                            </form>
                        @elseif($isInstallationScheduled && $installation)
                            <form method="POST" action="{{ route('admin.installations.reassign', $installation) }}" class="admin-app-form" data-protect-form data-warn-unsaved>
                                @csrf
                                @method('PATCH')

                                <div class="admin-app-form-grid">
                                    <div class="admin-app-field admin-app-field--full">
                                        <label for="technician_id">Reassign Technician</label>
                                        <select id="technician_id" name="technician_id" required>
                                            <option value="">Select Technician</option>
                                            @foreach($technicians as $techUser)
                                                <option value="{{ $techUser->id }}" @selected((string) $selectedTechnicianId === (string) $techUser->id)>
                                                    {{ $techUser->name }} ({{ $techUser->email }})
                                                </option>
                                            @endforeach
                                        </select>
                                        <p class="admin-app-helper">The installation remains scheduled; only the assigned technician changes.</p>
                                        @error('technician_id')
                                            <span class="error">{{ $message }}</span>
                                        @enderror
                                    </div>

                                    <div class="admin-app-field admin-app-field--full">
                                        <label for="reassignment_reason">Reassignment reason <span class="field-badge optional">Optional</span></label>
                                        <textarea id="reassignment_reason" name="reassignment_reason" rows="3" placeholder="Example: Technician unavailable for the assigned slot.">{{ old('reassignment_reason', $installation->reassignment_reason) }}</textarea>
                                        @error('reassignment_reason')
                                            <span class="error">{{ $message }}</span>
                                        @enderror
                                    </div>
                                </div>

                                @error('reassignment')
                                    <span class="error">{{ $message }}</span>
                                @enderror

                                <div>
                                    <button class="btn">Reassign Technician</button>
                                </div>
                            </form>
                        @elseif($isRejectedState)
                            <div class="admin-app-empty">
                                <span class="admin-app-inline-label">Review closed</span>
                                <p class="admin-app-helper">This application has been rejected and is no longer available for scheduling.</p>
                            </div>
                        @elseif($isCancelledState)
                            <div class="admin-app-empty">
                                <span class="admin-app-inline-label">Application cancelled</span>
                                <p class="admin-app-helper">This application was cancelled. The saved record remains available for review.</p>
                                <div class="admin-app-detail">
                                    <span class="admin-app-label">Customer cancellation reason</span>
                                    <div class="admin-app-value">{{ $cancellationReason ?: 'No reason recorded.' }}</div>
                                </div>
                            </div>
                        @endif
                    </section>
                @endif

                @if($isCompletedState && $installation)
                    <section class="admin-app-card admin-app-panel--documents" data-app-workspace-panel="documents">
                        <div class="admin-app-card-head">
                            <h2>Installation Photo</h2>
                        </div>

                        <x-photo-gallery
                            :photos="$proofPhotos"
                            title="Installation Photos"
                            empty="No installation photo uploaded."
                            button-text="View photo/photos"
                            class="admin-app-photo"
                        />
                    </section>
                @endif

                <section class="admin-app-card admin-app-panel--history" data-app-workspace-panel="history">
                    <div class="admin-app-card-head">
                        <div>
                            <h2>Application and Installation History</h2>
                            <p class="admin-app-helper">Identity decisions, status changes, assignments, schedule changes, customer requests, and installation activity in one chronological record.</p>
                        </div>
                    </div>

                    @if($applicationTimeline->isNotEmpty())
                        <div class="admin-app-history-scroll">
                            <div class="admin-app-timeline">
                                @foreach($applicationTimeline as $event)
                                    <div class="admin-app-timeline-item">
                                        <div class="admin-app-timeline-meta">
                                            {{ $event['occurred_at']?->displayDateTime('F d, Y h:i A') ?? '-' }} - {{ $event['actor'] }}
                                        </div>
                                        <strong>{{ $event['title'] }}</strong>
                                        <div class="admin-app-value">{{ $event['description'] }}</div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @else
                        <div class="admin-app-empty">
                            <span class="admin-app-inline-label">No history yet</span>
                            <p class="admin-app-helper">Record activity will appear here as the application is reviewed and processed.</p>
                        </div>
                    @endif
                </section>
            </aside>
        </div>

    </div>

    @if($hasLocation)
        <div class="admin-app-map-modal" id="applicationMapModal" aria-hidden="true">
            <div class="admin-app-map-modal__backdrop" data-close-map-modal></div>
            <div class="admin-app-map-modal__dialog" role="dialog" aria-modal="true" aria-labelledby="applicationMapModalTitle">
                <div class="admin-app-map-modal__shell">
                    <div class="admin-app-map-modal__head">
                        <div>
                            <h3 id="applicationMapModalTitle">Pinned Service Location</h3>
                            <p class="admin-app-helper">Review the saved pin and confirm the exact install site when needed.</p>
                        </div>
                        <button type="button" class="admin-app-map-modal__close" data-close-map-modal aria-label="Close pinned location map">&times;</button>
                    </div>

                    <div id="adminApplicationMap" class="js-readonly-location-map admin-app-map admin-app-map--modal" data-lat="{{ $locationLat }}" data-lng="{{ $locationLng }}" data-popup="{{ $fullAddress }}" data-empty-label="No pinned location available."></div>

                    <div class="admin-app-map-modal__meta">
                        <div class="admin-app-detail">
                            <span class="admin-app-label">Latitude</span>
                            <div class="admin-app-value">{{ number_format($locationLat, 6) }}</div>
                        </div>
                        <div class="admin-app-detail">
                            <span class="admin-app-label">Longitude</span>
                            <div class="admin-app-value">{{ number_format($locationLng, 6) }}</div>
                        </div>
                        <div class="admin-app-detail admin-app-detail--full">
                            <span class="admin-app-label">Address</span>
                            <div class="admin-app-value">{{ $fullAddress }}</div>
                        </div>
                    </div>

                    <div>
                        @if($osmUrl)
                            <a href="{{ $osmUrl }}" target="_blank" rel="noopener noreferrer" class="btn secondary admin-app-map-link">Open in OpenStreetMap</a>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    @endif
    @push('scripts')
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const initReadonlyMaps = window.initReadonlyLocationMaps || function () {
                if (typeof window.L === 'undefined') {
                    return;
                }

                document.querySelectorAll('.js-readonly-location-map').forEach(function (element) {
                    const lat = parseFloat(element.dataset.lat);
                    const lng = parseFloat(element.dataset.lng);
                    if (Number.isNaN(lat) || Number.isNaN(lng)) {
                        if (element._readonlyLeafletMap) {
                            element._readonlyLeafletMap.remove();
                            element._readonlyLeafletMap = null;
                        }
                        element.classList.add('is-empty');
                        element.textContent = element.dataset.emptyLabel || 'No pinned location available.';
                        return;
                    }

                    if (element._readonlyLeafletMap) {
                        if (element.offsetWidth && element.offsetHeight) {
                            setTimeout(function () {
                                element._readonlyLeafletMap.invalidateSize();
                            }, 120);
                        }
                        return;
                    }

                    if (!element.offsetWidth || !element.offsetHeight) {
                        return;
                    }

                    element.classList.remove('is-empty');
                    element.textContent = '';

                    const map = L.map(element, {
                        center: [lat, lng],
                        zoom: 16,
                        scrollWheelZoom: false,
                        dragging: true,
                        zoomControl: false,
                        attributionControl: true,
                        doubleClickZoom: false,
                        boxZoom: false,
                        keyboard: false,
                    });

                    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                        maxZoom: 19,
                        attribution: '&copy; OpenStreetMap contributors'
                    }).addTo(map);

                    const popupNode = document.createElement('div');
                    popupNode.textContent = element.dataset.popup || 'Pinned service location';

                    L.marker([lat, lng])
                        .addTo(map)
                        .bindPopup(popupNode)
                        .openPopup();
                    element._readonlyLeafletMap = map;

                    setTimeout(function () {
                        map.invalidateSize();
                    }, 120);
                });
            };

            const decisionForm = document.getElementById('applicationDecisionForm');
            const rejectionReasonField = document.getElementById('rejection_reason');
            const rejectionReasonWrap = document.getElementById('rejectionReasonField');
            const statusButtons = document.querySelectorAll('[data-status-action]');
            const mapModal = document.getElementById('applicationMapModal');
            const mapOpeners = document.querySelectorAll('[data-open-map-modal]');
            const mapClosers = document.querySelectorAll('[data-close-map-modal]');
            const technicianSelect = document.getElementById('technician_id');
            const installationDateInput = document.getElementById('installation_date');
            const installationTimeSelect = document.getElementById('installation_time');
            const installationTimeHint = document.getElementById('installationTimeHint');
            const schedulingForm = technicianSelect?.closest('.admin-app-scheduling-form');
            const routePlanner = schedulingForm?.querySelector('[data-route-scheduler]');
            const routeState = routePlanner?.querySelector('[data-route-state]');
            const routeCount = routePlanner?.querySelector('[data-route-count]');
            const routeAvailable = routePlanner?.querySelector('[data-route-available]');
            const routeCards = routePlanner?.querySelector('[data-route-cards]');
            const routeUnavailable = routePlanner?.querySelector('[data-route-unavailable]');
            const routeUnavailableCount = routePlanner?.querySelector('[data-route-unavailable-count]');
            const routeUnavailableList = routePlanner?.querySelector('[data-route-unavailable-list]');
            const routeSelection = routePlanner?.querySelector('[data-route-selection]');
            const routeSelectionName = routePlanner?.querySelector('[data-route-selection-name]');
            const routeSelectionMeta = routePlanner?.querySelector('[data-route-selection-meta]');
            const schedulingConfig = {
                availability: @json($scheduleAvailability ?? []),
            };

            if (schedulingForm && routePlanner) {
                schedulingForm.classList.add('is-route-enhanced');
            }

            const workspace = document.querySelector('[data-admin-app-workspace]');
            const workspaceLayout = workspace?.querySelector('[data-workspace-layout]');
            const workspaceTabs = Array.from(workspace?.querySelectorAll('[data-workspace-tab]') || []);
            const workspacePanels = Array.from(workspace?.querySelectorAll('[data-app-workspace-panel]') || []);
            const workspaceSelect = workspace?.querySelector('[data-workspace-select]');
            const workspaceLive = workspace?.querySelector('[data-workspace-live]');
            const availableWorkspaceTabs = new Set(workspaceTabs.map(function (button) {
                return button.dataset.workspaceTab;
            }));

            const activateWorkspaceTab = function (requestedTab, updateHash) {
                if (!workspace || !availableWorkspaceTabs.size) {
                    return;
                }

                const tabName = availableWorkspaceTabs.has(requestedTab) ? requestedTab : 'overview';

                workspacePanels.forEach(function (panel) {
                    panel.hidden = panel.dataset.appWorkspacePanel !== tabName;
                });

                workspaceTabs.forEach(function (button) {
                    const isActive = button.dataset.workspaceTab === tabName;
                    button.setAttribute('aria-pressed', isActive ? 'true' : 'false');
                    button.tabIndex = isActive ? 0 : -1;
                });

                if (workspaceSelect) {
                    workspaceSelect.value = tabName;
                }

                workspaceLayout?.classList.toggle('is-single-column', tabName !== 'overview');

                if (workspaceLive) {
                    workspaceLive.textContent = tabName.charAt(0).toUpperCase() + tabName.slice(1) + ' section displayed.';
                }

                if (updateHash && window.history?.replaceState) {
                    window.history.replaceState(null, '', '#' + tabName);
                }

                if (tabName === 'location') {
                    initReadonlyMaps();
                    setTimeout(function () {
                        document.querySelectorAll('.js-readonly-location-map').forEach(function (element) {
                            element._readonlyLeafletMap?.invalidateSize();
                        });
                    }, 120);
                }
            };

            workspaceTabs.forEach(function (button, index) {
                button.addEventListener('click', function () {
                    activateWorkspaceTab(button.dataset.workspaceTab, true);
                });

                button.addEventListener('keydown', function (event) {
                    if (!['ArrowLeft', 'ArrowRight', 'Home', 'End'].includes(event.key)) {
                        return;
                    }

                    event.preventDefault();
                    let nextIndex = index;
                    if (event.key === 'ArrowRight') nextIndex = (index + 1) % workspaceTabs.length;
                    if (event.key === 'ArrowLeft') nextIndex = (index - 1 + workspaceTabs.length) % workspaceTabs.length;
                    if (event.key === 'Home') nextIndex = 0;
                    if (event.key === 'End') nextIndex = workspaceTabs.length - 1;

                    workspaceTabs[nextIndex].focus();
                    activateWorkspaceTab(workspaceTabs[nextIndex].dataset.workspaceTab, true);
                });
            });

            workspaceSelect?.addEventListener('change', function () {
                activateWorkspaceTab(workspaceSelect.value, true);
            });

            window.addEventListener('hashchange', function () {
                activateWorkspaceTab(window.location.hash.slice(1), false);
            });

            const daySlots = function (dateValue) {
                return schedulingConfig.availability?.[dateValue]?.slots || [];
            };

            const slotFor = function (dateValue, timeValue) {
                return daySlots(dateValue).find(function (slot) {
                    return slot.value === timeValue;
                });
            };

            const technicianCanUseSlot = function (slot, technicianId) {
                return !technicianId || (slot?.technician_ids || []).map(String).includes(String(technicianId));
            };

            const matchingDateCount = function (technicianId, timeValue) {
                return Object.entries(schedulingConfig.availability).filter(function ([, dateAvailability]) {
                    return (dateAvailability.slots || []).some(function (slot) {
                        return technicianCanUseSlot(slot, technicianId)
                            && (!timeValue || slot.value === timeValue);
                    });
                }).length;
            };

            const routeElement = function (tagName, className, text) {
                const element = document.createElement(tagName);
                if (className) {
                    element.className = className;
                }
                if (text !== undefined && text !== null) {
                    element.textContent = text;
                }

                return element;
            };

            const setRoutePlannerState = function (message, warning) {
                if (!routeState) {
                    return;
                }

                routeState.textContent = message;
                routeState.hidden = false;
                routeState.classList.toggle('is-warning', Boolean(warning));
                if (routeAvailable) routeAvailable.hidden = true;
                if (routeUnavailable) routeUnavailable.hidden = true;
                if (routeSelection) routeSelection.hidden = true;
                if (routeCount) routeCount.textContent = 'Select a schedule';
            };

            const technicianCard = function (option, selectedTechnicianId) {
                const card = routeElement('button', 'admin-route-card');
                card.type = 'button';
                card.dataset.technicianId = String(option.id);
                card.classList.toggle('is-recommended', Boolean(option.recommended));
                card.classList.toggle('is-selected', String(option.id) === String(selectedTechnicianId || ''));
                card.setAttribute('aria-pressed', String(option.id) === String(selectedTechnicianId || '') ? 'true' : 'false');
                card.setAttribute('aria-label', 'Select ' + option.name + '. ' + option.route_label + '. ' + option.route_reason);

                const top = routeElement('div', 'admin-route-card__top');
                const person = routeElement('div', 'admin-route-card__person');
                const avatar = routeElement('span', 'admin-route-card__avatar', option.initials || 'T');
                avatar.setAttribute('aria-hidden', 'true');
                const identity = routeElement('div');
                identity.append(
                    routeElement('strong', '', option.name),
                    routeElement('small', '', option.shift + ' shift')
                );
                person.append(avatar, identity);
                top.append(person, routeElement('span', 'admin-route-card__badge', option.route_label));
                card.append(top, routeElement('p', 'admin-route-card__reason', option.route_reason));

                const facts = routeElement('div', 'admin-route-card__facts');
                facts.append(routeElement(
                    'span',
                    'admin-route-card__fact',
                    option.daily_count + ' of ' + option.capacity + ' installations'
                ));
                if (option.nearest_distance_km !== null) {
                    facts.append(routeElement(
                        'span',
                        'admin-route-card__fact',
                        option.nearest_distance_km + ' km nearest job'
                    ));
                }
                if (option.additional_distance_km !== null) {
                    facts.append(routeElement(
                        'span',
                        'admin-route-card__fact',
                        '+' + option.additional_distance_km + ' km estimated route'
                    ));
                }
                card.append(facts);

                const schedule = routeElement('div', 'admin-route-card__schedule');
                const jobs = Array.isArray(option.jobs) ? option.jobs : [];
                if (!jobs.length) {
                    schedule.append(routeElement('span', 'admin-route-card__empty', 'No other installation scheduled on this date.'));
                } else {
                    jobs.forEach(function (job) {
                        const row = routeElement('div', 'admin-route-card__job');
                        row.append(
                            routeElement('strong', '', job.time_label),
                            routeElement('span', '', job.position + ' · ' + job.location)
                        );
                        schedule.append(row);
                    });
                }
                card.append(schedule);

                card.addEventListener('click', function () {
                    technicianSelect.value = String(option.id);
                    technicianSelect.dispatchEvent(new Event('change', { bubbles: true }));
                });

                return card;
            };

            const renderRouteOptions = function (dateValue, timeValue, selectedTechnicianId) {
                if (!routePlanner || !routeCards || !routeAvailable) {
                    return;
                }

                routeCards.replaceChildren();
                routeUnavailableList?.replaceChildren();

                if (!dateValue || !timeValue) {
                    setRoutePlannerState('Select an installation date and time to compare technician routes.', false);
                    return;
                }

                const slot = slotFor(dateValue, timeValue);
                const options = Array.isArray(slot?.technicians) ? slot.technicians : [];
                const available = options.filter(function (option) { return option.available; });
                const unavailable = options.filter(function (option) { return !option.available; });

                if (!slot || !available.length) {
                    setRoutePlannerState('No technician is available for this date and time. Choose another installation schedule.', true);
                    return;
                }

                routeState.hidden = true;
                routeState.classList.remove('is-warning');
                routeAvailable.hidden = false;
                if (routeCount) {
                    routeCount.textContent = available.length + ' available';
                }

                available.forEach(function (option) {
                    routeCards.append(technicianCard(option, selectedTechnicianId));
                });

                if (routeUnavailable && routeUnavailableCount && routeUnavailableList) {
                    routeUnavailable.hidden = unavailable.length === 0;
                    routeUnavailableCount.textContent = String(unavailable.length);
                    unavailable.forEach(function (option) {
                        const row = routeElement('div', 'admin-route-unavailable');
                        row.append(
                            routeElement('strong', '', option.name),
                            routeElement('span', '', option.unavailable_reason || 'Unavailable for this schedule.')
                        );
                        routeUnavailableList.append(row);
                    });
                }

                const selected = available.find(function (option) {
                    return String(option.id) === String(selectedTechnicianId || '');
                });

                if (routeSelection && routeSelectionName && routeSelectionMeta) {
                    routeSelection.hidden = !selected;
                    if (selected) {
                        routeSelectionName.textContent = selected.name;
                        routeSelectionMeta.textContent = schedulingConfig.availability?.[dateValue]?.label
                            + ' · ' + slot.label + ' · ' + selected.route_label;
                    }
                }
            };

            const refreshInstallationAvailability = function () {
                if (!technicianSelect || !installationDateInput || !installationTimeSelect) {
                    return;
                }

                let technicianId = technicianSelect.value;
                const dateValue = installationDateInput.value;
                let timeValue = installationTimeSelect.value;
                const dateAvailability = dateValue
                    ? schedulingConfig.availability?.[dateValue]
                    : null;

                let dateError = '';
                if (dateValue && !dateAvailability) {
                    dateError = 'Choose an available Monday-Friday date within the next 90 days.';
                }

                installationDateInput.setCustomValidity(dateError);
                installationDateInput.setAttribute('aria-invalid', dateError ? 'true' : 'false');

                let availableSlotCount = 0;
                Array.from(installationTimeSelect.options).forEach(function (option, index) {
                    if (index === 0) {
                        option.hidden = false;
                        option.disabled = false;
                        option.textContent = 'Select installation time';
                        return;
                    }

                    const slot = dateAvailability ? slotFor(dateValue, option.value) : null;
                    const available = Boolean(slot) && technicianCanUseSlot(slot, technicianId);
                    option.hidden = !available;
                    option.disabled = !available;
                    option.textContent = slot?.label || option.textContent;

                    if (available) {
                        availableSlotCount += 1;
                    }
                });

                const selectedTimeOption = installationTimeSelect.selectedOptions[0];
                if (timeValue && selectedTimeOption?.disabled) {
                    installationTimeSelect.value = '';
                    timeValue = '';
                }

                const availableTechnicianIds = new Set();
                if (dateAvailability) {
                    daySlots(dateValue)
                        .filter(function (slot) {
                            return !timeValue || slot.value === timeValue;
                        })
                        .forEach(function (slot) {
                            (slot.technician_ids || []).forEach(function (id) {
                                availableTechnicianIds.add(String(id));
                            });
                        });
                }

                Array.from(technicianSelect.options).forEach(function (option, index) {
                    if (index === 0) {
                        option.hidden = false;
                        option.disabled = false;
                        return;
                    }

                    const available = !dateValue
                        || (Boolean(dateAvailability) && availableTechnicianIds.has(String(option.value)));
                    option.hidden = !available;
                    option.disabled = !available;
                });

                const selectedTechnicianOption = technicianSelect.selectedOptions[0];
                if (technicianId && selectedTechnicianOption?.disabled) {
                    technicianSelect.value = '';
                    technicianId = '';
                    refreshInstallationAvailability();
                    return;
                }

                renderRouteOptions(dateValue, timeValue, technicianId);

                if (!installationTimeHint) {
                    return;
                }

                const dateCount = matchingDateCount(technicianId, timeValue);
                if (dateError) {
                    installationTimeHint.textContent = dateError;
                } else if (!dateValue) {
                    installationTimeHint.textContent = dateCount + ' available weekday'
                        + (dateCount === 1 ? '' : 's')
                        + ' in the next 90 days. Select one from the calendar.';
                } else if (availableSlotCount === 0) {
                    installationTimeHint.textContent = 'No installation times are available for this selection.';
                } else {
                    installationTimeHint.textContent = availableSlotCount + ' available installation time'
                        + (availableSlotCount === 1 ? '' : 's')
                        + ' for this selection.';
                }
            };

            const hashTab = window.location.hash.slice(1);
            const initialWorkspaceTab = availableWorkspaceTabs.has(hashTab)
                ? hashTab
                : (workspace?.dataset.initialTab || 'overview');
            activateWorkspaceTab(initialWorkspaceTab, false);
            initReadonlyMaps();

            if (mapModal && mapOpeners.length) {
                const openMapModal = function () {
                    mapModal.classList.add('is-open');
                    mapModal.setAttribute('aria-hidden', 'false');
                    document.body.classList.add('admin-app-map-open');
                    initReadonlyMaps();

                    const mapElement = document.getElementById('adminApplicationMap');
                    if (mapElement && mapElement._readonlyLeafletMap) {
                        setTimeout(function () {
                            mapElement._readonlyLeafletMap.invalidateSize();
                        }, 120);
                    }
                };

                const closeMapModal = function () {
                    mapModal.classList.remove('is-open');
                    mapModal.setAttribute('aria-hidden', 'true');
                    document.body.classList.remove('admin-app-map-open');
                };

                mapOpeners.forEach(function (button) {
                    button.addEventListener('click', openMapModal);
                });

                mapClosers.forEach(function (button) {
                    button.addEventListener('click', closeMapModal);
                });

                document.addEventListener('keydown', function (event) {
                    if (event.key === 'Escape' && mapModal.classList.contains('is-open')) {
                        closeMapModal();
                    }
                });
            }

            if (technicianSelect && installationDateInput && installationTimeSelect) {
                technicianSelect.addEventListener('change', refreshInstallationAvailability);
                installationDateInput.addEventListener('change', refreshInstallationAvailability);
                installationTimeSelect.addEventListener('change', refreshInstallationAvailability);
                technicianSelect.addEventListener('invalid', function (event) {
                    if (!routePlanner) return;
                    event.preventDefault();
                    setRoutePlannerState('Choose one of the available technician cards before saving the schedule.', true);
                    routePlanner.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    routePlanner.focus({ preventScroll: true });
                });
                routePlanner?.setAttribute('tabindex', '-1');
                refreshInstallationAvailability();
            }

            if (decisionForm && rejectionReasonField && rejectionReasonWrap && statusButtons.length) {
                const toggleRejectionRequirement = function (status) {
                    const isRejected = status === 'rejected';
                    rejectionReasonField.required = isRejected;
                    rejectionReasonField.setAttribute('aria-required', isRejected ? 'true' : 'false');
                    rejectionReasonWrap.classList.toggle('is-hidden', !isRejected && rejectionReasonField.value.trim() === '');
                };

                statusButtons.forEach(function (button) {
                    button.addEventListener('click', function () {
                        toggleRejectionRequirement(button.value);
                    });
                });

                rejectionReasonField.addEventListener('input', function () {
                    if (rejectionReasonField.required) {
                        rejectionReasonWrap.classList.remove('is-hidden');
                    }
                });

                toggleRejectionRequirement('{{ old('status', $application->status) }}');
            }
        });
    </script>
    @endpush
</x-app-layout>
