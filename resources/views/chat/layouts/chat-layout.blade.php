@once
    @push('styles')
        <style>
            .chat-ui {
                color: #e2e8f0;
            }

            .chat-ui__shell {
                display: flex;
                flex-direction: column;
                gap: 1rem;
                padding: 1.1rem;
                border: 1px solid rgba(51, 65, 85, 0.5);
                border-radius: 1rem;
                background: #0f172a;
                box-shadow: 0 18px 42px rgba(2, 6, 23, 0.28);
            }

            .chat-ui--customer .chat-ui__shell {
                gap: 0.85rem;
                padding: 0.95rem 1rem;
            }

            .chat-ui__header,
            .chat-ui__panel-head,
            .chat-ui__conversation-top,
            .chat-ui__conversation-meta,
            .chat-ui__filter-actions,
            .chat-ui__sidebar-toggle,
            .chat-ui__panel-actions,
            .chat-ui__composer,
            .chat-ui__message-meta {
                display: flex;
                align-items: center;
                justify-content: space-between;
                gap: 0.75rem;
            }

            .chat-ui__header {
                flex-wrap: wrap;
                padding-bottom: 0.8rem;
                border-bottom: 1px solid rgba(51, 65, 85, 0.5);
            }

            .chat-ui__eyebrow,
            .chat-ui__subtitle,
            .chat-ui__sidebar-head p,
            .chat-ui__conversation-meta,
            .chat-ui__conversation-submeta,
            .chat-ui__message-meta span,
            .chat-ui__context-item span,
            .chat-ui__empty-state p,
            .chat-ui__panel-head p {
                margin: 0;
                color: #94a3b8;
                font-size: 0.75rem;
            }

            .chat-ui__eyebrow {
                margin-bottom: 0.35rem;
                text-transform: uppercase;
                letter-spacing: 0.12em;
            }

            .chat-ui__header h1,
            .chat-ui__panel-head h2,
            .chat-ui__sidebar-head h2,
            .chat-ui__empty-state h3,
            .chat-ui__context-card h3 {
                margin: 0;
                color: #f8fafc;
            }

            .chat-ui__header h1 {
                font-size: 1.35rem;
                line-height: 1.2;
            }

            .chat-ui--customer .chat-ui__header {
                padding-bottom: 0.65rem;
            }

            .chat-ui__layout {
                display: grid;
                grid-template-columns: minmax(0, 30%) minmax(0, 70%);
                gap: 1rem;
                align-items: start;
            }

            .chat-ui__layout.is-single {
                grid-template-columns: minmax(0, 1fr);
            }

            .chat-ui__sidebar-shell {
                min-width: 0;
            }

            .chat-ui__sidebar-toggle {
                display: none;
                width: 100%;
                padding: 0.9rem 1rem;
                border: 1px solid rgba(51, 65, 85, 0.5);
                border-radius: 0.75rem;
                background: #111827;
                color: #e2e8f0;
                cursor: pointer;
                list-style: none;
            }

            .chat-ui__sidebar-toggle::-webkit-details-marker {
                display: none;
            }

            .chat-ui__sidebar,
            .chat-ui__panel,
            .chat-ui__thread-card,
            .chat-ui__context-card {
                border: 1px solid rgba(51, 65, 85, 0.5);
                border-radius: 0.75rem;
                background: #111827;
            }

            .chat-ui__sidebar {
                padding: 0.85rem;
            }

            .chat-ui__sidebar-head {
                display: flex;
                align-items: flex-start;
                justify-content: space-between;
                gap: 0.75rem;
                margin-bottom: 0.75rem;
            }

            .chat-ui__filters {
                display: grid;
                gap: 0.55rem;
                margin-bottom: 0.75rem;
            }

            .chat-ui__filters.is-compact {
                grid-template-columns: minmax(0, 1fr) auto auto;
                align-items: center;
            }

            .chat-ui__filters.is-compact.has-scope {
                grid-template-columns: minmax(0, 1fr) 140px auto auto;
            }

            .chat-ui__filters input,
            .chat-ui__filters select,
            .chat-ui__composer textarea,
            .chat-ui__note-form textarea {
                width: 100%;
                padding: 0.68rem 0.85rem;
                border: 1px solid rgba(51, 65, 85, 0.7);
                border-radius: 0.75rem;
                background: #0f172a;
                color: #e2e8f0;
                font-size: 0.875rem;
                resize: vertical;
            }

            .chat-ui__filters input::placeholder,
            .chat-ui__composer textarea::placeholder,
            .chat-ui__note-form textarea::placeholder {
                color: #64748b;
            }

            .chat-ui__button {
                display: inline-flex;
                align-items: center;
                justify-content: center;
                min-height: 2.3rem;
                padding: 0.55rem 0.9rem;
                border-radius: 0.75rem;
                border: 1px solid transparent;
                font-size: 0.875rem;
                font-weight: 600;
                text-decoration: none;
                cursor: pointer;
                transition: background-color 0.18s ease, border-color 0.18s ease, color 0.18s ease;
            }

            .chat-ui__button--primary {
                background: #2563eb;
                border-color: #2563eb;
                color: #eff6ff;
            }

            .chat-ui__button--primary:hover {
                background: #1d4ed8;
                border-color: #1d4ed8;
            }

            .chat-ui__button--secondary {
                background: transparent;
                border-color: rgba(71, 85, 105, 0.9);
                color: #cbd5e1;
            }

            .chat-ui__button--secondary:hover {
                border-color: #64748b;
                color: #f8fafc;
            }

            .chat-ui__conversation-list {
                display: flex;
                flex-direction: column;
                gap: 0.6rem;
                max-height: 68vh;
                overflow-y: auto;
            }

            .chat-ui__conversation-item {
                display: flex;
                flex-direction: column;
                gap: 0.45rem;
                padding: 0.8rem 0.85rem;
                border: 1px solid rgba(51, 65, 85, 0.55);
                border-radius: 0.75rem;
                background: #0f172a;
                color: #e2e8f0;
                text-decoration: none;
                transition: border-color 0.18s ease, background-color 0.18s ease;
            }

            .chat-ui__conversation-item:hover,
            .chat-ui__conversation-item.is-active {
                border-color: rgba(59, 130, 246, 0.8);
                background: #172036;
            }

            .chat-ui__conversation-top strong,
            .chat-ui__message-meta strong,
            .chat-ui__context-item strong {
                font-size: 0.875rem;
                color: #f8fafc;
            }

            .chat-ui__conversation-preview,
            .chat-ui__message-text,
            .chat-ui__attachment-control span {
                margin: 0;
                color: #cbd5e1;
                font-size: 0.875rem;
                line-height: 1.5;
            }

            .chat-ui__conversation-preview {
                display: -webkit-box;
                -webkit-line-clamp: 1;
                -webkit-box-orient: vertical;
                overflow: hidden;
            }

            .chat-ui__conversation-submeta,
            .chat-ui__context-list {
                display: flex;
                flex-wrap: wrap;
                gap: 0.5rem 0.75rem;
            }

            .chat-ui__badge {
                display: inline-flex;
                align-items: center;
                padding: 0.3rem 0.65rem;
                border-radius: 999px;
                font-size: 0.75rem;
                font-weight: 600;
                border: 1px solid transparent;
                white-space: nowrap;
            }

            .chat-ui__badge--gray {
                background: rgba(71, 85, 105, 0.18);
                color: #cbd5e1;
                border-color: rgba(100, 116, 139, 0.45);
            }

            .chat-ui__badge--blue {
                background: rgba(37, 99, 235, 0.18);
                color: #bfdbfe;
                border-color: rgba(59, 130, 246, 0.45);
            }

            .chat-ui__badge--yellow {
                background: rgba(234, 179, 8, 0.16);
                color: #fde68a;
                border-color: rgba(250, 204, 21, 0.4);
            }

            .chat-ui__badge--green {
                background: rgba(34, 197, 94, 0.16);
                color: #bbf7d0;
                border-color: rgba(74, 222, 128, 0.38);
            }

            .chat-ui__badge--red {
                background: rgba(239, 68, 68, 0.16);
                color: #fecaca;
                border-color: rgba(248, 113, 113, 0.38);
            }

            .chat-ui__panel {
                padding: 0.85rem;
            }

            .chat-ui--customer .chat-ui__panel {
                padding: 0.75rem;
            }

            .chat-ui__panel-head {
                flex-wrap: wrap;
                padding-bottom: 0.8rem;
                margin-bottom: 0.8rem;
                border-bottom: 1px solid rgba(51, 65, 85, 0.5);
            }

            .chat-ui__panel-grid {
                display: grid;
                gap: 0.85rem;
            }

            .chat-ui__panel-grid.is-staff {
                grid-template-columns: minmax(0, 1.6fr) minmax(280px, 0.8fr);
            }

            .chat-ui__thread-card,
            .chat-ui__context-card {
                padding: 0.85rem;
            }

            .chat-ui__thread-card {
                display: flex;
                flex-direction: column;
                min-height: clamp(24rem, calc(100vh - 20rem), 31rem);
                max-height: clamp(24rem, calc(100vh - 20rem), 31rem);
            }

            .chat-ui--customer .chat-ui__thread-card {
                min-height: clamp(24rem, calc(100vh - 20rem), 31rem);
                max-height: clamp(24rem, calc(100vh - 20rem), 31rem);
            }

            .chat-ui__messages {
                display: flex;
                flex-direction: column;
                flex: 1 1 auto;
                min-height: 0;
                gap: 0.85rem;
                overflow-y: auto;
                padding-right: 0.25rem;
            }

            .chat-ui__messages::-webkit-scrollbar,
            .chat-ui__conversation-list::-webkit-scrollbar {
                width: 0.45rem;
            }

            .chat-ui__messages::-webkit-scrollbar-thumb,
            .chat-ui__conversation-list::-webkit-scrollbar-thumb {
                background: rgba(100, 116, 139, 0.45);
                border-radius: 999px;
            }

            .chat-ui__message-row {
                display: flex;
                gap: 0.75rem;
            }

            .chat-ui__message-row.self {
                justify-content: flex-end;
            }

            .chat-ui__avatar {
                display: inline-flex;
                align-items: center;
                justify-content: center;
                width: 2rem;
                height: 2rem;
                margin-top: auto;
                border-radius: 999px;
                background: #1e293b;
                color: #e2e8f0;
                font-size: 0.75rem;
                font-weight: 700;
            }

            .chat-ui__message-bubble,
            .chat-ui__system-banner,
            .chat-ui__note {
                max-width: min(100%, 38rem);
                padding: 0.75rem 0.9rem;
                border: 1px solid rgba(51, 65, 85, 0.55);
                border-radius: 0.9rem;
                background: #0f172a;
            }

            .chat-ui--customer .chat-ui__message-bubble,
            .chat-ui--customer .chat-ui__system-banner {
                max-width: min(100%, 36rem);
            }

            .chat-ui__message-bubble.self {
                background: #1d4ed8;
                border-color: #1d4ed8;
            }

            .chat-ui__message-bubble.self .chat-ui__message-meta strong,
            .chat-ui__message-bubble.self .chat-ui__message-meta span,
            .chat-ui__message-bubble.self .chat-ui__message-text {
                color: #eff6ff;
            }

            .chat-ui__message-bubble.ai {
                background: #132033;
                border-color: rgba(59, 130, 246, 0.35);
            }

            .chat-ui__message-bubble.agent {
                background: #172033;
            }

            .chat-ui__system-banner,
            .chat-ui__note {
                color: #cbd5e1;
                font-size: 0.875rem;
            }

            .chat-ui__quick-replies {
                display: flex;
                flex-wrap: wrap;
                gap: 0.5rem;
                margin-top: 0.75rem;
            }

            .chat-ui__quick-replies.is-hidden {
                display: none;
            }

            .chat-ui__quick-reply {
                padding: 0.55rem 0.8rem;
                border: 1px solid rgba(59, 130, 246, 0.38);
                border-radius: 999px;
                background: rgba(30, 64, 175, 0.18);
                color: #dbeafe;
                font-size: 0.75rem;
                cursor: pointer;
            }

            .chat-ui__quick-reply:hover {
                background: rgba(30, 64, 175, 0.3);
            }

            .chat-ui__composer,
            .chat-ui__note-form {
                display: grid;
                gap: 0.65rem;
                margin-top: 0.75rem;
            }

            .chat-ui__composer {
                grid-template-columns: minmax(0, 1fr) auto;
                align-items: end;
            }

            .chat-ui__composer-fields {
                display: grid;
                gap: 0.55rem;
                min-width: 0;
            }

            .chat-ui__composer textarea {
                min-height: 58px;
            }

            .chat-ui__attachment-control {
                display: flex;
                flex-wrap: wrap;
                align-items: center;
                gap: 0.55rem;
            }

            .chat-ui__attachment-control input[type="file"] {
                position: absolute;
                width: 1px;
                height: 1px;
                overflow: hidden;
                clip: rect(0, 0, 0, 0);
                white-space: nowrap;
            }

            .chat-ui__attachment-button {
                display: inline-flex;
                align-items: center;
                justify-content: center;
                min-height: 2rem;
                padding: 0.4rem 0.7rem;
                border: 1px solid rgba(71, 85, 105, 0.9);
                border-radius: 0.65rem;
                color: #cbd5e1;
                font-size: 0.78rem;
                font-weight: 650;
                cursor: pointer;
            }

            .chat-ui__attachment-button:hover {
                border-color: #64748b;
                color: #f8fafc;
            }

            .chat-ui__message-attachment {
                display: grid;
                gap: 0.45rem;
                margin-top: 0.55rem;
                color: inherit;
                text-decoration: none;
            }

            .chat-ui__message-attachment img {
                display: block;
                width: min(100%, 18rem);
                max-height: 260px;
                object-fit: cover;
                border: 1px solid rgba(226, 232, 240, 0.2);
                border-radius: 0.65rem;
                background: rgba(15, 23, 42, 0.35);
            }

            .chat-ui__message-attachment span {
                color: inherit;
                font-size: 0.78rem;
                font-weight: 650;
                text-decoration: underline;
                text-underline-offset: 3px;
            }

            .chat-ui__message-bubble.self .chat-ui__message-attachment span {
                color: #eff6ff;
            }

            .chat-ui__composer-notice {
                margin-top: 0.75rem;
                padding: 0.85rem 1rem;
                border: 1px solid rgba(148, 163, 184, 0.22);
                border-radius: 14px;
                background: rgba(15, 23, 42, 0.42);
                color: #cbd5e1;
                font-size: 0.88rem;
                line-height: 1.45;
            }

            .chat-ui--customer .chat-ui__composer {
                margin-top: 0.7rem;
            }

            .chat-ui--customer .chat-ui__composer textarea {
                min-height: 72px;
                max-height: 120px;
                padding: 0.72rem 0.85rem;
            }

            .chat-ui__note-form label {
                color: #cbd5e1;
                font-size: 0.75rem;
                font-weight: 600;
            }

            .chat-ui__context-card h3 {
                font-size: 1rem;
                margin-bottom: 0.75rem;
            }

            .chat-ui__context-item {
                display: grid;
                gap: 0.25rem;
                padding: 0.85rem 0;
                border-top: 1px solid rgba(51, 65, 85, 0.45);
                min-width: calc(50% - 0.5rem);
            }

            .chat-ui__context-item.is-full {
                width: 100%;
            }

            .chat-ui__context-item:first-child,
            .chat-ui__context-item:nth-child(2) {
                border-top: 0;
            }

            .chat-ui__empty-state {
                display: flex;
                flex-direction: column;
                align-items: center;
                justify-content: center;
                gap: 0.4rem;
                min-height: 10rem;
                padding: 1rem;
                text-align: center;
            }

            .chat-ui__empty-state.is-compact {
                min-height: 7rem;
                padding: 0.85rem;
            }

            .chat-ui__empty-icon {
                display: inline-flex;
                align-items: center;
                justify-content: center;
                width: 2rem;
                height: 2rem;
                border-radius: 999px;
                background: #172033;
                color: #cbd5e1;
                font-size: 0.8rem;
                font-weight: 700;
            }

            .chat-ui__modal {
                position: fixed;
                inset: 0;
                display: none;
                align-items: center;
                justify-content: center;
                padding: 1rem;
                z-index: 60;
            }

            .chat-ui__modal.is-open {
                display: flex;
            }

            .chat-ui__modal-backdrop {
                position: absolute;
                inset: 0;
                background: rgba(2, 6, 23, 0.72);
            }

            .chat-ui__modal-dialog {
                position: relative;
                z-index: 1;
                width: min(100%, 28rem);
                padding: 1rem;
                border: 1px solid rgba(51, 65, 85, 0.6);
                border-radius: 1rem;
                background: #111827;
                box-shadow: 0 20px 48px rgba(2, 6, 23, 0.4);
            }

            .chat-ui__modal-head {
                display: flex;
                align-items: flex-start;
                justify-content: space-between;
                gap: 0.75rem;
                margin-bottom: 0.9rem;
            }

            .chat-ui__modal-head h3 {
                margin: 0;
                color: #f8fafc;
                font-size: 1rem;
            }

            .chat-ui__modal-head p {
                margin: 0.3rem 0 0;
                color: #94a3b8;
                font-size: 0.8rem;
            }

            .chat-ui__modal-close {
                display: inline-flex;
                align-items: center;
                justify-content: center;
                width: 2rem;
                height: 2rem;
                border: 1px solid rgba(71, 85, 105, 0.8);
                border-radius: 999px;
                background: transparent;
                color: #cbd5e1;
                cursor: pointer;
            }

            .chat-ui__modal-body {
                display: grid;
                gap: 0.8rem;
            }

            .chat-ui__modal-body label {
                color: #cbd5e1;
                font-size: 0.8rem;
                font-weight: 600;
            }

            .chat-ui__modal-actions {
                display: flex;
                flex-wrap: wrap;
                gap: 0.65rem;
                margin-top: 0.2rem;
            }

            @media (max-width: 1024px) {
                .chat-ui__layout,
                .chat-ui__panel-grid.is-staff {
                    grid-template-columns: minmax(0, 1fr);
                }

                .chat-ui__thread-card,
                .chat-ui--customer .chat-ui__thread-card {
                    min-height: 68vh;
                    max-height: 68vh;
                }
            }

            @media (max-width: 768px) {
                .chat-ui__shell {
                    padding: 0.85rem;
                }

                .chat-ui--customer .chat-ui__shell {
                    padding: 0.8rem;
                }

                .chat-ui__header h1 {
                    font-size: 1.25rem;
                }

                .chat-ui__sidebar-toggle {
                    display: flex;
                }

                .chat-ui__sidebar-shell:not([open]) .chat-ui__sidebar {
                    display: none;
                }

                .chat-ui__composer {
                    grid-template-columns: minmax(0, 1fr);
                }

                .chat-ui__filters.is-compact,
                .chat-ui__filters.is-compact.has-scope {
                    grid-template-columns: minmax(0, 1fr);
                }

                .chat-ui__thread-card,
                .chat-ui--customer .chat-ui__thread-card {
                    min-height: clamp(26rem, calc(100vh - 13rem), 34rem);
                    max-height: clamp(26rem, calc(100vh - 13rem), 34rem);
                }

                .chat-ui__panel-actions,
                .chat-ui__header-actions {
                    width: 100%;
                    justify-content: flex-start;
                    flex-wrap: wrap;
                }

                .chat-ui__modal-dialog {
                    width: min(100%, 100%);
                }
            }
        </style>
    @endpush
@endonce

@php
    $hasSidebar = !empty($sidebarContent);
    $layoutMarkup = '<div class="chat-ui__layout' . ($hasSidebar ? '' : ' is-single') . '">';

    if ($hasSidebar) {
        $layoutMarkup .= $sidebarContent;
    }

    $layoutMarkup .= $panelContent;
    $layoutMarkup .= '</div>';
@endphp

@include('chat.components.container', [
    'role' => $role,
    'eyebrow' => $eyebrow,
    'title' => $title,
    'subtitle' => $subtitle ?? null,
    'headerActions' => $headerActions ?? null,
    'body' => new \Illuminate\Support\HtmlString($layoutMarkup),
])
