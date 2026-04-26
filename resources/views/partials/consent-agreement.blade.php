@php
    $consentPrefix = $consentPrefix ?? 'consent';
    $formId = $formId ?? '';
    $termsFieldName = $termsFieldName ?? 'agreed_to_terms';
    $privacyFieldName = $privacyFieldName ?? 'agreed_to_privacy';
    $helperText = $helperText ?? 'Please read and agree to the Terms and Conditions and Privacy Notice before proceeding.';
    $validationMessage = config('legal.validation_message');
    $sectionClass = $sectionClass ?? '';
    $panelClass = $panelClass ?? '';
    $optionClass = $optionClass ?? '';
    $optionMainClass = $optionMainClass ?? '';
    $optionTextClass = $optionTextClass ?? '';
    $linkClass = $linkClass ?? '';
    $errorClass = $errorClass ?? '';
    $validationBag = $validationBag ?? 'default';
    $consentErrors = $errors->getBag($validationBag);
    $termsVersion = config('legal.terms_version');
    $privacyVersion = config('legal.privacy_version');
    $termsContent = config('legal.terms', []);
    $privacyContent = config('legal.privacy', []);
    $termsOldAccepted = (bool) old($termsFieldName);
    $privacyOldAccepted = (bool) old($privacyFieldName);
@endphp

<div class="consent-section {{ $sectionClass }}" data-consent-section data-consent-form="{{ $formId }}" data-consent-message="{{ $validationMessage }}">
    <div class="consent-section-head">
        <p>{{ $helperText }}</p>
    </div>

    <div class="consent-panel {{ $panelClass }} {{ $consentErrors->has($termsFieldName) || $consentErrors->has($privacyFieldName) ? 'has-error' : '' }}" data-consent-panel>
        <input type="hidden" name="terms_version" value="{{ old('terms_version', $termsVersion) }}">
        <input type="hidden" name="privacy_version" value="{{ old('privacy_version', $privacyVersion) }}">

        <div class="consent-option consent-option-inline {{ $optionClass }} {{ $consentErrors->has($termsFieldName) ? 'is-invalid' : '' }}" data-consent-option>
            <label for="{{ $consentPrefix }}_terms" class="consent-option-main {{ $optionMainClass }}">
                <input
                    id="{{ $consentPrefix }}_terms"
                    type="checkbox"
                    name="{{ $termsFieldName }}"
                    value="1"
                    {{ $termsOldAccepted ? 'checked' : '' }}
                    {{ $termsOldAccepted ? '' : 'disabled' }}
                    required
                    data-consent-checkbox
                    data-consent-lock-id="{{ $consentPrefix }}_terms"
                    aria-describedby="{{ $consentPrefix }}_error"
                >
                <span class="consent-option-text {{ $optionTextClass }}">I agree to the <button type="button" class="consent-link-inline {{ $linkClass }}" data-consent-open="{{ $consentPrefix }}_terms_modal" aria-haspopup="dialog">Terms and Conditions</button></span>
            </label>
        </div>

        <div class="consent-option consent-option-inline {{ $optionClass }} {{ $consentErrors->has($privacyFieldName) ? 'is-invalid' : '' }}" data-consent-option>
            <label for="{{ $consentPrefix }}_privacy" class="consent-option-main {{ $optionMainClass }}">
                <input
                    id="{{ $consentPrefix }}_privacy"
                    type="checkbox"
                    name="{{ $privacyFieldName }}"
                    value="1"
                    {{ $privacyOldAccepted ? 'checked' : '' }}
                    {{ $privacyOldAccepted ? '' : 'disabled' }}
                    required
                    data-consent-checkbox
                    data-consent-lock-id="{{ $consentPrefix }}_privacy"
                    aria-describedby="{{ $consentPrefix }}_error"
                >
                <span class="consent-option-text {{ $optionTextClass }}">I agree to the <button type="button" class="consent-link-inline {{ $linkClass }}" data-consent-open="{{ $consentPrefix }}_privacy_modal" aria-haspopup="dialog">Privacy Notice</button></span>
            </label>
        </div>

        <div
            id="{{ $consentPrefix }}_error"
            class="consent-error {{ $errorClass }} {{ $consentErrors->has($termsFieldName) || $consentErrors->has($privacyFieldName) ? 'is-visible' : '' }}"
            data-consent-error
            tabindex="-1"
            aria-live="polite"
        >
            {{ $consentErrors->has($termsFieldName) || $consentErrors->has($privacyFieldName) ? $validationMessage : '' }}
        </div>
    </div>
</div>

<div class="consent-modal" id="{{ $consentPrefix }}_terms_modal" data-consent-modal data-consent-lock-id="{{ $consentPrefix }}_terms" hidden>
    <div class="consent-modal-backdrop" data-consent-close></div>
    <div class="consent-modal-dialog" role="dialog" aria-modal="true" aria-labelledby="{{ $consentPrefix }}_terms_title">
        <div class="consent-modal-head">
            <div>
                <h3 id="{{ $consentPrefix }}_terms_title">Terms and Conditions</h3>
                <p>Version {{ $termsVersion }}</p>
            </div>
            <button type="button" class="consent-modal-close" data-consent-close aria-label="Close Terms and Conditions">&times;</button>
        </div>
        <div class="consent-modal-body" data-consent-scroll-body>
            <div class="consent-modal-scroll-message" data-consent-scroll-message>
                Scroll to the end to confirm you have read this document.
            </div>
            @foreach($termsContent as $heading => $paragraphs)
                <section class="consent-copy-block">
                    <h4>{{ $heading }}</h4>
                    @foreach($paragraphs as $paragraph)
                        <p>{{ $paragraph }}</p>
                    @endforeach
                </section>
            @endforeach
        </div>
        <div class="consent-modal-actions">
            <button type="button" class="btn secondary consent-modal-action" data-consent-close>Close</button>
        </div>
    </div>
</div>

<div class="consent-modal" id="{{ $consentPrefix }}_privacy_modal" data-consent-modal data-consent-lock-id="{{ $consentPrefix }}_privacy" hidden>
    <div class="consent-modal-backdrop" data-consent-close></div>
    <div class="consent-modal-dialog" role="dialog" aria-modal="true" aria-labelledby="{{ $consentPrefix }}_privacy_title">
        <div class="consent-modal-head">
            <div>
                <h3 id="{{ $consentPrefix }}_privacy_title">Privacy Notice</h3>
                <p>Version {{ $privacyVersion }}</p>
            </div>
            <button type="button" class="consent-modal-close" data-consent-close aria-label="Close Privacy Notice">&times;</button>
        </div>
        <div class="consent-modal-body" data-consent-scroll-body>
            <div class="consent-modal-scroll-message" data-consent-scroll-message>
                Scroll to the end to confirm you have read this document.
            </div>
            @foreach($privacyContent as $heading => $paragraphs)
                <section class="consent-copy-block">
                    <h4>{{ $heading }}</h4>
                    @foreach($paragraphs as $paragraph)
                        <p>{{ $paragraph }}</p>
                    @endforeach
                </section>
            @endforeach
        </div>
        <div class="consent-modal-actions">
            <button type="button" class="btn secondary consent-modal-action" data-consent-close>Close</button>
        </div>
    </div>
</div>
