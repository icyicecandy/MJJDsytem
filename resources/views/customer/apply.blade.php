@push('styles')
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin="" />
    <link rel="stylesheet" href="{{ asset('css/application-camera.css') }}?v={{ filemtime(public_path('css/application-camera.css')) }}">
    <link rel="stylesheet" href="{{ asset('css/application-draft.css') }}?v={{ filemtime(public_path('css/application-draft.css')) }}">
@endpush
@push('scripts')
    <script defer src="{{ asset('js/application-camera.js') }}?v={{ filemtime(public_path('js/application-camera.js')) }}"></script>
    <script defer src="{{ asset('js/application-draft.js') }}?v={{ filemtime(public_path('js/application-draft.js')) }}"></script>
@endpush

<x-app-layout>
    @php
        $draft = $draft ?? null;
        $formApplication = $draft ? (object) array_map(fn ($value) => $value ?? '', $draft->payload) : $application;
        $draftPhotoUrl = $draft?->photoUrl();
        $regions = $addressCatalog ?? [];
        $currentLat = old('lat', $formApplication->lat ?? null);
        $currentLng = old('lng', $formApplication->lng ?? null);
        $selectedRegion = old('region', $formApplication->region ?? ($regions[0]['name'] ?? ''));
        $selectedRegionItem = collect($regions)->firstWhere('name', $selectedRegion);
        $selectedRegion = $selectedRegionItem['name'] ?? $selectedRegion;
        $provinceItems = $selectedRegionItem['provinces'] ?? [];
        $selectedProvince = old('province', $formApplication->province ?? ($provinceItems[0]['name'] ?? ''));
        $selectedProvinceItem = collect($provinceItems)->firstWhere('name', $selectedProvince);
        $selectedProvince = $selectedProvinceItem['name'] ?? $selectedProvince;
        $municipalityItems = $selectedProvinceItem['municipalities'] ?? [];
        $selectedMunicipality = old('municipality', $formApplication->municipality ?? '');
        $selectedMunicipalityItem = collect($municipalityItems)->firstWhere('name', $selectedMunicipality);
        $selectedMunicipality = $selectedMunicipalityItem['name'] ?? $selectedMunicipality;
        $selectedBarangay = old('barangay', $formApplication->barangay ?? '');
        $barangayItems = $selectedMunicipalityItem['barangays'] ?? [];
        $hasConfiguredBarangays = !empty($barangayItems);
        $serviceBounds = $mapBounds ?? ['south' => 15.45, 'west' => 119.55, 'north' => 16.55, 'east' => 121.05, 'center' => [15.96, 120.34], 'zoom' => 9];
        $initialStep = $errors->any() ? 1 : ($draft?->step ?? 1);
    @endphp

    <div class="apply-wizard-shell">
        <section class="apply-draft-bar" aria-label="Application draft">
            <div>
                <strong>{{ $draft ? 'Your saved application draft has been restored.' : 'Need to finish later?' }}</strong>
                <p>Save your progress before leaving. Drafts are private and expire 30 days after your last save.</p>
                <p id="applicationDraftStatus" role="status" aria-live="polite">{{ $draft ? 'Last saved: ' . $draft->updated_at->format('M d, Y h:i A') : 'Your application is submitted only when you select Submit Application.' }}</p>
            </div>
            <div class="apply-draft-actions">
                <button type="button" class="btn secondary" id="applicationDraftSave">Save draft</button>
                <button type="button" class="apply-link-btn" id="applicationDraftDiscard" @disabled(!$draft)>Discard draft</button>
            </div>
        </section>

        <div class="apply-stepper" data-apply-stepper>
            <div class="apply-step is-active" data-step="1"><span>1</span><label>Applicant</label></div>
            <div class="apply-step" data-step="2"><span>2</span><label>Location</label></div>
            <div class="apply-step" data-step="3"><span>3</span><label>Plan</label></div>
            <div class="apply-step" data-step="4"><span>4</span><label>Upload ID</label></div>
            <div class="apply-step" data-step="5"><span>5</span><label>Review</label></div>
        </div>

        @if ($errors->any())
            <section class="notice-card apply-notice-card">
                Please review the required fields before submitting your application.
                @error('draft')<p>{{ $message }}</p>@enderror
            </section>
        @endif

        <form method="POST" action="{{ route('customer.apply.store') }}" enctype="multipart/form-data" class="apply-wizard-form" id="customerApplyWizard" data-draft-save-url="{{ route('customer.apply.draft.save') }}" data-draft-discard-url="{{ route('customer.apply.draft.destroy') }}" novalidate>
            @csrf
            <input type="hidden" name="draft_token" id="applicationDraftToken" value="{{ $draft?->token }}">
            <input type="hidden" name="lat" id="apply_lat" value="{{ $currentLat }}">
            <input type="hidden" name="lng" id="apply_lng" value="{{ $currentLng }}">

            <section class="panel-card apply-step-panel is-active" data-panel="1">
                <div class="apply-step-head">
                    <div>
                        <h1>Applicant Information</h1>
                        <p>Please provide your basic contact details before setting the service location.</p>
                    </div>
                </div>

                <div class="apply-form-grid">
                    <div class="apply-field">
                        <label for="first_name">First Name</label>
                        <input id="first_name" type="text" name="first_name" value="{{ old('first_name', $formApplication->first_name ?? '') }}" required maxlength="100" pattern="^[A-Za-z][A-Za-z\s'\-]*$">
                        @error('first_name')
                            <span class="error">{{ $message }}</span>
                        @enderror
                        <span class="error" id="firstNameLiveError" hidden></span>
                    </div>

                    <div class="apply-field">
                        <label for="last_name">Last Name</label>
                        <input id="last_name" type="text" name="last_name" value="{{ old('last_name', $formApplication->last_name ?? '') }}" required maxlength="100" pattern="^[A-Za-z][A-Za-z\s'\-]*$">
                        @error('last_name')
                            <span class="error">{{ $message }}</span>
                        @enderror
                        <span class="error" id="lastNameLiveError" hidden></span>
                    </div>

                    <div class="apply-field">
                        <label for="email">Email Address</label>
                        <input id="email" type="email" name="email" value="{{ old('email', $formApplication->email ?? auth()->user()->email) }}" required maxlength="255" autocomplete="email">
                        @error('email')
                            <span class="error">{{ $message }}</span>
                        @enderror
                        <span class="error" id="emailDuplicateError" aria-live="polite" hidden></span>
                    </div>

                    <div class="apply-field">
                        <label for="mobile_number">Mobile Number</label>
                        <input id="mobile_number" type="tel" name="mobile_number" value="{{ old('mobile_number', $formApplication->mobile_number ?? auth()->user()->phone) }}" placeholder="09171234567" required maxlength="32" inputmode="tel" autocomplete="tel" pattern="^\+?[0-9]{10,15}$">
                        @error('mobile_number')
                            <span class="error">{{ $message }}</span>
                        @enderror
                        <span class="error" id="mobileDuplicateError" aria-live="polite" hidden></span>
                    </div>
                </div>

                <div class="apply-step-actions end">
                    <button type="button" class="btn" data-next-step="2" onclick="return window.customerApplyWizardNext ? window.customerApplyWizardNext(2) : false;">Continue to Location</button>
                </div>
            </section>

            <section class="panel-card apply-step-panel" data-panel="2">
                <div class="apply-step-head">
                    <div>
                        <h1>Service Location</h1>
                        <p>Select the service address in Pangasinan, then pin the exact location on the map.</p>
                    </div>
                </div>

                <div class="apply-form-grid">
                    <div class="apply-field">
                        <label for="region">Region</label>
                        <select id="region" name="region" required>
                            @foreach($regions as $region)
                                <option value="{{ $region['name'] }}" @selected($selectedRegion === ($region['name'] ?? ''))>
                                    {{ $region['name'] }}
                                </option>
                            @endforeach
                        </select>
                        <p class="text-muted mt-1">MJJD currently operates in Pangasinan only.</p>
                        @error('region')
                            <span class="error">{{ $message }}</span>
                        @enderror
                    </div>

                    <div class="apply-field">
                        <label for="province">Province</label>
                        <select id="province" name="province" required @disabled(empty($provinceItems))>
                            @foreach($provinceItems as $province)
                                <option value="{{ $province['name'] }}" @selected($selectedProvince === ($province['name'] ?? ''))>
                                    {{ $province['name'] }}
                                </option>
                            @endforeach
                        </select>
                        @error('province')
                            <span class="error">{{ $message }}</span>
                        @enderror
                    </div>

                    <div class="apply-field">
                        <label for="municipality">Municipality / City</label>
                        <select id="municipality" name="municipality" required @disabled(empty($municipalityItems))>
                            <option value="">Select Municipality / City</option>
                            @foreach($municipalityItems as $municipality)
                                <option value="{{ $municipality['name'] }}" @selected($selectedMunicipality === ($municipality['name'] ?? ''))>
                                    {{ $municipality['name'] }}
                                </option>
                            @endforeach
                        </select>
                        @error('municipality')
                            <span class="error">{{ $message }}</span>
                        @enderror
                    </div>

                    <div class="apply-field">
                        <label for="barangay_select">Barangay / Zone</label>
                        <input type="hidden" id="barangay" name="barangay" value="{{ $selectedBarangay }}">
                        <select id="barangay_select" required @disabled(empty($barangayItems)) @if(! $hasConfiguredBarangays) hidden @endif>
                            <option value="">Select Barangay / Zone</option>
                            @foreach($barangayItems as $barangay)
                                @php
                                    $barangayName = is_array($barangay) ? ($barangay['name'] ?? '') : $barangay;
                                @endphp
                                <option value="{{ $barangayName }}" @selected($selectedBarangay === $barangayName)>
                                    {{ $barangayName }}
                                </option>
                            @endforeach
                        </select>
                        <input
                            id="barangay_manual"
                            type="text"
                            value="{{ $selectedBarangay }}"
                            placeholder="Enter Barangay / Zone"
                            @if($hasConfiguredBarangays || ! $selectedMunicipality) hidden @endif
                        >
                        <p class="text-muted mt-1" id="barangayHelper">
                            {{ $hasConfiguredBarangays ? 'Choose your Barangay / Zone from the list.' : ($selectedMunicipality ? 'Type the Barangay / Zone manually for this municipality.' : 'Select Municipality / City first.') }}
                        </p>
                        @error('barangay')
                            <span class="error">{{ $message }}</span>
                        @enderror
                    </div>

                    <div class="apply-field full-span">
                        <label for="street_landmark">Street / Landmark / House Details</label>
                        <input id="street_landmark" type="text" name="street_landmark" value="{{ old('street_landmark', $formApplication->street_landmark ?? '') }}" placeholder="House #, street name, purok, nearby landmarks">
                        @error('street_landmark')
                            <span class="error">{{ $message }}</span>
                        @enderror
                    </div>
                </div>

                <div class="apply-map-card real-map-card">
                    <div id="customerApplyMap" class="apply-live-map" data-lat="{{ $currentLat }}" data-lng="{{ $currentLng }}"></div>
                </div>

                <div class="apply-location-readout">
                    <div class="tracking-meta"><strong>Latitude</strong><span id="applyLatDisplay">{{ $currentLat ?: 'Not set' }}</span></div>
                    <div class="tracking-meta"><strong>Longitude</strong><span id="applyLngDisplay">{{ $currentLng ?: 'Not set' }}</span></div>
                    <div class="tracking-meta full-width"><strong>Address Summary</strong><span id="applyAddressSummary">Complete the dropdowns to build the service address.</span></div>
                    <div class="tracking-meta full-width"><strong>Map Preview</strong><span id="applyLocationPreview">Click the map to load the nearest location details.</span></div>
                </div>
                <p class="apply-map-status" id="applyMapStatus">Pinning is limited to MJJD service coverage in Pangasinan.</p>
                @error('lat')
                    <span class="error">{{ $message }}</span>
                @enderror
                @error('lng')
                    <span class="error">{{ $message }}</span>
                @enderror

                <div class="apply-step-actions between">
                    <button type="button" class="apply-link-btn" data-prev-step="1" onclick="return window.customerApplyWizardPrev ? window.customerApplyWizardPrev(1) : false;">Back</button>
                    <button type="button" class="btn accent" data-next-step="3" onclick="return window.customerApplyWizardNext ? window.customerApplyWizardNext(3) : false;">Choose Plan</button>
                </div>
            </section>

            <section class="panel-card apply-step-panel" data-panel="3">
                <div class="apply-step-head">
                    <div>
                        <h1>Choose Your Plan</h1>
                        <p>Select the internet package that fits your household or business needs.</p>
                    </div>
                </div>

                <div class="apply-plan-stack">
                    <label class="apply-plan-option {{ old('plan_code', $formApplication->plan_code ?? '') === 'S2S_50' ? 'selected' : '' }}">
                        <input type="radio" name="plan_code" value="S2S_50" {{ old('plan_code', $formApplication->plan_code ?? '') === 'S2S_50' ? 'checked' : '' }} required>
                        <div>
                            <strong>Superfiber 50 Mbps</strong>
                            <span>Good for daily browsing, messaging, streaming, and home use.</span>
                        </div>
                        <div class="apply-plan-price">PHP 999 installation fee</div>
                    </label>

                    <label class="apply-plan-option {{ old('plan_code', $formApplication->plan_code ?? '') === 'S2S_100' ? 'selected' : '' }}">
                        <input type="radio" name="plan_code" value="S2S_100" {{ old('plan_code', $formApplication->plan_code ?? '') === 'S2S_100' ? 'checked' : '' }} required>
                        <div>
                            <strong>Extraboost 100 Mbps</strong>
                            <span>Higher-speed access for multiple users, devices, and heavier streaming.</span>
                        </div>
                        <div class="apply-plan-price">PHP 1,699 installation fee</div>
                    </label>
                </div>
                @error('plan_code')
                    <span class="error">{{ $message }}</span>
                @enderror

                <div class="apply-step-actions between">
                    <button type="button" class="apply-link-btn" data-prev-step="2" onclick="return window.customerApplyWizardPrev ? window.customerApplyWizardPrev(2) : false;">Back</button>
                    <button type="button" class="btn accent" data-next-step="4" onclick="return window.customerApplyWizardNext ? window.customerApplyWizardNext(4) : false;">Upload ID</button>
                </div>
            </section>

            <section class="panel-card apply-step-panel" data-panel="4">
                <div class="apply-step-head">
                    <div>
                        <h1>Upload Valid ID</h1>
                        <p>Upload one clear photo of yourself holding your valid ID before submitting your application.</p>
                    </div>
                </div>

                <div class="upload-field apply-upload-field">
                    <label for="id_selfie_image">Customer holding valid ID</label>
                    <p class="text-muted mt-1">Hold your valid ID beside your face so both are clearly visible. JPG, PNG, or WebP, up to 5 MB.</p>
                    <div class="id-photo-actions">
                    <input id="id_selfie_image" type="file" name="id_selfie_image" accept=".jpg,.jpeg,.png,.webp" data-saved-photo-url="{{ $draftPhotoUrl }}" @required(!$draftPhotoUrl)>
                        <button type="button" class="btn secondary" id="idCameraOpen" aria-haspopup="dialog" aria-controls="idCameraDialog">Take photo</button>
                    </div>
                    <p id="idPhotoStatus" class="id-photo-status" role="status" aria-live="polite">{{ $draftPhotoUrl ? 'Your saved ID photo is ready. Choose File or Take photo to replace it.' : '' }}</p>
                    <img id="idPhotoPreview" class="id-photo-preview" alt="Selected photo of you holding your ID" @if($draftPhotoUrl) src="{{ $draftPhotoUrl }}" @else hidden @endif>
                    @error('id_selfie_image')
                        <span class="error">{{ $message }}</span>
                    @enderror
                </div>

                <div class="apply-step-actions between">
                    <button type="button" class="apply-link-btn" data-prev-step="3" onclick="return window.customerApplyWizardPrev ? window.customerApplyWizardPrev(3) : false;">Back</button>
                    <button type="button" class="btn accent" data-next-step="5" onclick="return window.customerApplyWizardNext ? window.customerApplyWizardNext(5) : false;">Review Application</button>
                </div>
            </section>

            <section class="panel-card apply-step-panel" data-panel="5">
                <div class="apply-step-head">
                    <div>
                        <h1>Review and Submit</h1>
                        <p>Confirm the details below before sending your service application.</p>
                    </div>
                </div>

                <div class="apply-review-grid">
                    <div class="tracking-meta"><strong>Applicant</strong><span id="reviewApplicant">-</span></div>
                    <div class="tracking-meta"><strong>Contact</strong><span id="reviewContact">-</span></div>
                    <div class="tracking-meta"><strong>Address</strong><span id="reviewAddress">-</span></div>
                    <div class="tracking-meta"><strong>Location Pin</strong><span id="reviewPin">Not set</span></div>
                    <div class="tracking-meta"><strong>Plan</strong><span id="reviewPlan">-</span></div>
                    <div class="tracking-meta"><strong>ID held by customer</strong><span id="reviewIdSelfie">Required</span></div>
                </div>

                <div class="apply-step-actions between">
                    <button type="button" class="apply-link-btn" data-prev-step="4" onclick="return window.customerApplyWizardPrev ? window.customerApplyWizardPrev(4) : false;">Back</button>
                    <button type="submit" class="btn">Submit Application</button>
                </div>
            </section>
        </form>
        <dialog id="idCameraDialog" class="id-camera" aria-labelledby="idCameraTitle" aria-describedby="idCameraHelp">
            <div class="id-camera-heading">
                <h2 id="idCameraTitle">Take your ID photo</h2>
                <button type="button" class="btn secondary" id="idCameraClose" aria-label="Close camera">Close</button>
            </div>
            <p id="idCameraHelp">Hold your valid ID beside your face. Keep the text readable and your face visible.</p>
            <p id="idCameraStatus" class="id-photo-status" role="status" aria-live="polite"></p>
            <div class="id-camera-stage">
                <video id="idCameraVideo" autoplay muted playsinline aria-label="Live camera preview"></video>
                <img id="idCameraSnapshot" alt="Captured photo to review" hidden>
            </div>
            <div class="id-camera-actions">
                <button type="button" class="btn secondary" id="idCameraRetake" hidden>Retake</button>
                <button type="button" class="btn" id="idCameraCapture" disabled>Capture photo</button>
                <button type="button" class="btn" id="idCameraUse" hidden>Use this photo</button>
            </div>
        </dialog>
    </div>
</x-app-layout>

<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>
<script>
    (function () {
        const panels = Array.from(document.querySelectorAll('[data-panel]'));
        const steps = Array.from(document.querySelectorAll('.apply-step'));
        const form = document.getElementById('customerApplyWizard');
        const latInput = document.getElementById('apply_lat');
        const lngInput = document.getElementById('apply_lng');
        const latDisplay = document.getElementById('applyLatDisplay');
        const lngDisplay = document.getElementById('applyLngDisplay');
        const preview = document.getElementById('applyLocationPreview');
        const addressSummary = document.getElementById('applyAddressSummary');
        const statusText = document.getElementById('applyMapStatus');
        const mapElement = document.getElementById('customerApplyMap');
        const idSelfieInput = document.getElementById('id_selfie_image');
        const firstNameInput = document.getElementById('first_name');
        const lastNameInput = document.getElementById('last_name');
        const emailInput = document.getElementById('email');
        const mobileInput = document.getElementById('mobile_number');
        const firstNameLiveError = document.getElementById('firstNameLiveError');
        const lastNameLiveError = document.getElementById('lastNameLiveError');
        const emailDuplicateError = document.getElementById('emailDuplicateError');
        const mobileDuplicateError = document.getElementById('mobileDuplicateError');
        const regionSelect = document.getElementById('region');
        const provinceSelect = document.getElementById('province');
        const municipalitySelect = document.getElementById('municipality');
        const barangayValueInput = document.getElementById('barangay');
        const barangaySelect = document.getElementById('barangay_select');
        const barangayManualInput = document.getElementById('barangay_manual');
        const barangayHelper = document.getElementById('barangayHelper');
        const streetInput = document.getElementById('street_landmark');
        const contactCheckUrl = @json(route('customer.apply.contact-check'));
        const addressCatalog = @json($addressCatalog ?? []);
        const selectedValues = {
            region: @json($selectedRegion),
            province: @json($selectedProvince),
            municipality: @json($selectedMunicipality),
            barangay: @json($selectedBarangay),
        };
        const serviceBounds = @json($serviceBounds);

        if (!form || !panels.length || !steps.length) {
            return;
        }

        let allowedBounds = null;
        let currentStep = @json($initialStep);
        let marker = null;
        let map = null;
        let reverseLookupTimer = null;
        let contactCheckTimer = null;
        let latestContactRequest = 0;
        let latestGeocodeRequest = 0;
        let contactCheckPromise = null;
        const namePattern = /^[A-Za-z][A-Za-z\s'-]*$/;
        const emailPattern = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        const mobileCharactersPattern = /^\+?[0-9]*$/;
        const mobilePattern = /^\+?[0-9]{10,15}$/;

        function setLiveError(field, errorEl, message) {
            if (!field) return;
            field.setCustomValidity(message || '');
            field.classList.toggle('is-invalid', Boolean(message));
            if (errorEl) {
                errorEl.textContent = message || '';
                errorEl.hidden = !message;
            }
        }

        function setDuplicateState(field, errorEl, message) {
            if (!field) return;
            field.setCustomValidity(message || '');
            field.classList.toggle('is-invalid', Boolean(message));
            if (errorEl) {
                errorEl.textContent = message || '';
                errorEl.hidden = !message;
            }
        }

        function validateNameField(field, errorEl, label) {
            if (!field) return true;

            const value = field.value.trim();
            if (value === '') {
                setLiveError(field, errorEl, '');
                return true;
            }

            if (!namePattern.test(value)) {
                setLiveError(field, errorEl, `${label} may only contain letters, spaces, hyphens, and apostrophes.`);
                return false;
            }

            setLiveError(field, errorEl, '');
            return true;
        }

        function normalizeEmailValue(value) {
            return String(value || '').trim().toLowerCase();
        }

        function validateEmailFormat() {
            if (!emailInput) return true;

            const value = normalizeEmailValue(emailInput.value);
            if (emailInput.value !== value) {
                emailInput.value = value;
            }

            if (value === '') {
                setLiveError(emailInput, emailDuplicateError, '');
                return true;
            }

            if (!emailPattern.test(value)) {
                setLiveError(emailInput, emailDuplicateError, 'Please enter a valid email address.');
                return false;
            }

            setLiveError(emailInput, emailDuplicateError, '');
            return true;
        }

        function normalizeMobileValue(value) {
            const normalized = String(value || '').replace(/[\s()\-]+/g, '');
            return /^\+?639[0-9]{9}$/.test(normalized)
                ? '0' + normalized.replace(/^\+/, '').slice(2)
                : normalized;
        }

        function validateMobileNumber(options = {}) {
            if (!mobileInput) return true;

            const value = normalizeMobileValue(mobileInput.value);
            const enforceComplete = options.enforceComplete !== false;
            if (mobileInput.value !== value) {
                mobileInput.value = value;
            }

            if (value === '') {
                setLiveError(mobileInput, mobileDuplicateError, '');
                return true;
            }

            if (!mobileCharactersPattern.test(value)) {
                setLiveError(mobileInput, mobileDuplicateError, 'Contact number may only contain numbers and an optional leading plus sign.');
                return false;
            }

            if ((enforceComplete || value.replace(/^\+/, '').length >= 10) && !mobilePattern.test(value)) {
                setLiveError(mobileInput, mobileDuplicateError, 'Enter a valid contact number with 10 to 15 digits.');
                return false;
            }

            setLiveError(mobileInput, mobileDuplicateError, '');
            return true;
        }

        function shouldCheckDuplicate(field) {
            if (!field) return false;
            const value = field.value.trim();
            if (value === '') return false;
            if (field === emailInput) {
                return validateEmailFormat();
            }
            if (field === mobileInput) {
                return validateMobileNumber();
            }
            return false;
        }

        function runContactDuplicateCheck() {
            if (!emailInput || !mobileInput) {
                return Promise.resolve(true);
            }

            clearTimeout(contactCheckTimer);
            const payload = new URLSearchParams();
            if (shouldCheckDuplicate(emailInput)) {
                payload.set('email', emailInput.value.trim());
            }
            if (shouldCheckDuplicate(mobileInput)) {
                payload.set('mobile_number', mobileInput.value.trim());
            }

            if ([...payload.keys()].length === 0) {
                return Promise.resolve(true);
            }

            const requestId = ++latestContactRequest;
            const snapshot = [emailInput.value, mobileInput.value];
            const isCurrent = () => requestId === latestContactRequest
                && snapshot[0] === emailInput.value && snapshot[1] === mobileInput.value;
            const controller = new AbortController();
            const timeout = setTimeout(() => controller.abort(), 10000);
            contactCheckPromise = fetch(`${contactCheckUrl}?${payload.toString()}`, {
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                credentials: 'same-origin',
                signal: controller.signal,
            })
                .then(async response => {
                    const data = await response.json();
                    if (!response.ok) {
                        const error = new Error('Contact check failed');
                        error.fieldErrors = response.status === 422 ? data.errors : null;
                        throw error;
                    }
                    return data;
                })
                .then(data => {
                    if (!isCurrent()) return false;
                    if (typeof data?.email?.duplicate !== 'boolean' || typeof data?.mobile_number?.duplicate !== 'boolean') {
                        throw new Error('Invalid contact check response');
                    }

                    const emailMessage = data?.email?.duplicate ? (data.email.message || 'This email address is already used in another application.') : '';
                    const mobileMessage = data?.mobile_number?.duplicate ? (data.mobile_number.message || 'This mobile number is already used in another application.') : '';

                    if (payload.has('email')) {
                        setDuplicateState(emailInput, emailDuplicateError, emailMessage);
                    }
                    if (payload.has('mobile_number')) {
                        setDuplicateState(mobileInput, mobileDuplicateError, mobileMessage);
                    }

                    return !emailMessage && !mobileMessage;
                })
                .catch(error => {
                    if (isCurrent()) {
                        const message = 'Unable to check contact details. Check your connection and try Next again.';
                        if (payload.has('email')) setDuplicateState(emailInput, emailDuplicateError, error.fieldErrors?.email?.[0] || message);
                        if (payload.has('mobile_number')) setDuplicateState(mobileInput, mobileDuplicateError, error.fieldErrors?.mobile_number?.[0] || message);
                    }
                    return false;
                })
                .finally(() => {
                    clearTimeout(timeout);
                    if (requestId === latestContactRequest) {
                        contactCheckPromise = null;
                    }
                });

            return contactCheckPromise;
        }

        function scheduleContactDuplicateCheck() {
            clearTimeout(contactCheckTimer);
            contactCheckTimer = setTimeout(() => {
                runContactDuplicateCheck();
            }, 350);
        }

        function setText(id, value) {
            const el = document.getElementById(id);
            if (el) el.textContent = value;
        }

        function setStatus(message, isError) {
            if (!statusText) return;
            statusText.textContent = message;
            statusText.classList.toggle('is-error', Boolean(isError));
        }

        function valueOf(id) {
            const field = document.getElementById(id);
            return field ? field.value.trim() : '';
        }

        function currentAddressParts() {
            return [
                valueOf('street_landmark'),
                valueOf('barangay'),
                valueOf('municipality'),
                valueOf('province'),
                valueOf('region')
            ].filter(Boolean);
        }

        function updateAddressSummary() {
            const summary = currentAddressParts().join(', ');
            if (addressSummary) {
                addressSummary.textContent = summary || 'Complete the dropdowns to build the service address.';
            }
        }

        function updateReview() {
            const checkedPlan = form.querySelector('input[name="plan_code"]:checked');
            setText('reviewApplicant', [valueOf('first_name'), valueOf('last_name')].filter(Boolean).join(' ') || '-');
            setText('reviewContact', [valueOf('email'), valueOf('mobile_number')].filter(Boolean).join(' / ') || '-');
            setText('reviewAddress', currentAddressParts().join(', ') || '-');
            setText('reviewPin', latInput && lngInput && latInput.value && lngInput.value ? `${latInput.value}, ${lngInput.value}` : 'Not set');
            setText('reviewPlan', checkedPlan ? checkedPlan.value.replace('_', ' ') : '-');
            setText('reviewIdSelfie', idSelfieInput && idSelfieInput.files.length ? idSelfieInput.files[0].name : (idSelfieInput?.dataset.savedPhotoUrl ? 'Saved ID photo' : 'Required'));
            updateAddressSummary();
        }

        function setStep(step) {
            // Stop an active map animation before its wizard panel is hidden.
            if (map) map.stop();
            currentStep = Number(step);
            panels.forEach(panel => panel.classList.toggle('is-active', Number(panel.dataset.panel) === currentStep));
            steps.forEach(item => {
                const itemStep = Number(item.dataset.step);
                item.classList.toggle('is-active', itemStep === currentStep);
                item.classList.toggle('is-complete', itemStep < currentStep);
            });
            updateReview();
            if (currentStep === 2 && map) {
                map.invalidateSize({ pan: false, animate: false });
            }
            return false;
        }

        async function validateApplicantStep() {
            const namesValid = [
                validateNameField(firstNameInput, firstNameLiveError, 'First name'),
                validateNameField(lastNameInput, lastNameLiveError, 'Last name'),
            ].every(Boolean);

            if (!namesValid) {
                const invalidNameField = [firstNameInput, lastNameInput].find(field => field && !field.checkValidity());
                if (invalidNameField) {
                    invalidNameField.reportValidity();
                }
                return false;
            }

            const contactFormatValid = [
                validateEmailFormat(),
                validateMobileNumber(),
            ].every(Boolean);

            if (!contactFormatValid) {
                const invalidContactField = [emailInput, mobileInput].find(field => field && !field.checkValidity());
                if (invalidContactField) {
                    invalidContactField.reportValidity();
                }
                return false;
            }

            const ids = ['first_name', 'last_name', 'email', 'mobile_number'];
            for (const id of ids) {
                const field = document.getElementById(id);
                if (field && !field.checkValidity()) {
                    field.reportValidity();
                    return false;
                }
            }

            const contactCheckPassed = await runContactDuplicateCheck();
            if (!contactCheckPassed) {
                const invalidField = [emailInput, mobileInput].find(field => field && !field.checkValidity());
                if (invalidField) {
                    invalidField.reportValidity();
                }
                return false;
            }

            return true;
        }

        function validateLocationStep() {
            const ids = ['region', 'province', 'municipality'];
            for (const id of ids) {
                const field = document.getElementById(id);
                if (field && !field.checkValidity()) {
                    field.reportValidity();
                    return false;
                }
            }

            if (barangaySelect && !barangaySelect.hidden && !barangaySelect.value) {
                barangaySelect.reportValidity();
                return false;
            }

            if (barangayManualInput && !barangayManualInput.hidden && !barangayManualInput.value.trim()) {
                barangayManualInput.reportValidity();
                return false;
            }

            if (!latInput || !lngInput || !latInput.value || !lngInput.value) {
                setStatus('Please select your municipality and fine-tune the exact service pin before proceeding.', true);
                return false;
            }

            return true;
        }

        function validatePlanStep() {
            const checkedPlan = form.querySelector('input[name="plan_code"]:checked');
            if (!checkedPlan) {
                const firstPlan = form.querySelector('input[name="plan_code"]');
                if (firstPlan) firstPlan.reportValidity();
                return false;
            }
            return true;
        }

        function validateUploadStep() {
            for (const field of [idSelfieInput]) {
                if (field && !field.checkValidity()) {
                    field.reportValidity();
                    return false;
                }
            }

            return true;
        }

        function validateStep(step) {
            step = Number(step);
            if (step === 1) return validateApplicantStep();
            if (step === 2) return validateLocationStep();
            if (step === 3) return validatePlanStep();
            if (step === 4) return validateUploadStep();
            return true;
        }

        function updateCoordinateDisplay(latlng) {
            if (!latInput || !lngInput) return;
            latInput.value = latlng.lat.toFixed(6);
            lngInput.value = latlng.lng.toFixed(6);
            if (latDisplay) latDisplay.textContent = latInput.value;
            if (lngDisplay) lngDisplay.textContent = lngInput.value;
            updateReview();
        }

        function clearCoordinateDisplay() {
            if (latInput) latInput.value = '';
            if (lngInput) lngInput.value = '';
            if (latDisplay) latDisplay.textContent = 'Not set';
            if (lngDisplay) lngDisplay.textContent = 'Not set';
            updateReview();
        }

        function reverseGeocode(latlng) {
            if (!preview) return;
            preview.textContent = 'Loading nearest location...';
            clearTimeout(reverseLookupTimer);
            reverseLookupTimer = setTimeout(() => {
                fetch(`https://nominatim.openstreetmap.org/reverse?format=jsonv2&lat=${latlng.lat}&lon=${latlng.lng}&zoom=18&addressdetails=1`, {
                    headers: { 'Accept': 'application/json' }
                })
                    .then(response => response.ok ? response.json() : Promise.reject())
                    .then(data => { preview.textContent = data.display_name || 'Pinned inside MJJD coverage.'; })
                    .catch(() => { preview.textContent = 'Pinned inside MJJD coverage.'; });
            }, 250);
        }

        function clearPin(message) {
            if (marker && map) {
                map.removeLayer(marker);
                marker = null;
            }
            clearCoordinateDisplay();
            if (preview && message) {
                preview.textContent = message;
            }
        }

        function placeMarker(latlng, message, options = {}) {
            if (!allowedBounds || !allowedBounds.contains(latlng)) {
                setStatus('Selected point is outside MJJD service coverage in Pangasinan. Please choose a Pangasinan location.', true);
                return false;
            }
            setStatus(message || 'Pinned inside MJJD coverage.', false);
            if (!marker) {
                marker = L.marker(latlng, { draggable: true }).addTo(map);
                marker.on('dragend', function () { placeMarker(marker.getLatLng(), 'Pin adjusted manually.'); });
            } else {
                marker.setLatLng(latlng);
            }
            updateCoordinateDisplay(latlng);
            if (options.skipReverseLookup) {
                if (preview && options.previewMessage) {
                    preview.textContent = options.previewMessage;
                }
            } else {
                reverseGeocode(latlng);
            }
            return true;
        }

        function setOptions(select, options, placeholder, selectedValue, config = {}) {
            if (!select) return;
            select.innerHTML = '';

            if (config.placeholder !== false && placeholder) {
                const placeholderOption = document.createElement('option');
                placeholderOption.value = '';
                placeholderOption.textContent = placeholder;
                select.appendChild(placeholderOption);
            }

            options.forEach(option => {
                const optionLabel = typeof option === 'string' ? option : option.name;
                const element = document.createElement('option');
                element.value = optionLabel;
                element.textContent = optionLabel;
                if (selectedValue && selectedValue === optionLabel) {
                    element.selected = true;
                }
                select.appendChild(element);
            });

            const optionValues = options.map(option => typeof option === 'string' ? option : option.name);
            const fallbackValue = config.selectFirst && optionValues.length === 1 ? optionValues[0] : '';
            const nextValue = optionValues.includes(selectedValue) ? selectedValue : fallbackValue;
            select.disabled = options.length === 0 || Boolean(config.disabled);

            if (nextValue) {
                select.value = nextValue;
            } else if (!selectedValue || !optionValues.includes(selectedValue)) {
                select.value = '';
            }
        }

        function getRegionItem(regionName) {
            return addressCatalog.find(region => region.name === regionName) || null;
        }

        function getProvinceItem(regionName, provinceName) {
            const region = getRegionItem(regionName);
            if (!region) return null;
            return (region.provinces || []).find(province => province.name === provinceName) || null;
        }

        function getMunicipalityItem(regionName, provinceName, municipalityName) {
            const province = getProvinceItem(regionName, provinceName);
            if (!province) return null;
            return (province.municipalities || []).find(city => city.name === municipalityName) || null;
        }

        function getBarangayItem(regionName, provinceName, municipalityName, barangayName) {
            const municipality = getMunicipalityItem(regionName, provinceName, municipalityName);
            if (!municipality) return null;
            return (municipality.barangays || []).find(item => (typeof item === 'string' ? item : item.name) === barangayName) || null;
        }

        function syncBarangayValue() {
            if (!barangayValueInput) {
                return '';
            }

            const nextValue = barangaySelect && !barangaySelect.hidden
                ? barangaySelect.value
                : (barangayManualInput ? barangayManualInput.value.trim() : '');

            barangayValueInput.value = nextValue;
            return nextValue;
        }

        function getPlaceName(item) {
            return typeof item === 'string' ? item : (item && item.name ? item.name : '');
        }

        function hasCoordinatePair(target) {
            if (!target) {
                return false;
            }

            const lat = parseFloat(target.lat);
            const lng = parseFloat(target.lng);
            return !Number.isNaN(lat) && !Number.isNaN(lng);
        }

        function keepTargetInsideBounds(target, fallbackTarget) {
            if (!target || !allowedBounds || typeof window.L === 'undefined') {
                return target;
            }

            const targetLatLng = L.latLng(target.lat, target.lng);
            if (allowedBounds.contains(targetLatLng)) {
                return target;
            }

            if (!hasCoordinatePair(fallbackTarget)) {
                return null;
            }

            const fallbackLat = parseFloat(fallbackTarget.lat);
            const fallbackLng = parseFloat(fallbackTarget.lng);
            for (const scale of [0.75, 0.5, 0.25, 0]) {
                const candidate = {
                    ...target,
                    lat: fallbackLat + ((target.lat - fallbackLat) * scale),
                    lng: fallbackLng + ((target.lng - fallbackLng) * scale),
                };

                if (allowedBounds.contains(L.latLng(candidate.lat, candidate.lng))) {
                    return candidate;
                }
            }

            return {
                ...target,
                lat: fallbackLat,
                lng: fallbackLng,
            };
        }

        function buildBarangayStarterTarget(municipality, barangayName) {
            if (!hasCoordinatePair(municipality) || !barangayName) {
                return null;
            }

            const barangayNames = (municipality.barangays || []).map(getPlaceName).filter(Boolean);
            const index = Math.max(0, barangayNames.indexOf(barangayName));
            const lat = parseFloat(municipality.lat);
            const lng = parseFloat(municipality.lng);
            const angle = (index * 2.399963229728653) % (Math.PI * 2);
            const ring = Math.floor(index / 8);
            const radius = 0.006 + ((ring % 5) * 0.005);
            const lngScale = Math.max(Math.cos(lat * Math.PI / 180), 0.35);
            const target = {
                lat: lat + (Math.sin(angle) * radius),
                lng: lng + ((Math.cos(angle) * radius) / lngScale),
                zoom: 15,
                estimated: true,
            };

            return keepTargetInsideBounds(target, municipality);
        }

        function focusMapTarget(target, options = {}) {
            if (!map || !target) {
                return false;
            }

            const lat = parseFloat(target.lat);
            const lng = parseFloat(target.lng);
            const zoom = parseInt(target.zoom || options.zoom || 14, 10);
            if (Number.isNaN(lat) || Number.isNaN(lng)) {
                return false;
            }

            const latlng = L.latLng(lat, lng);
            if (allowedBounds && !allowedBounds.contains(latlng)) {
                return false;
            }

            map.stop();
            if (mapElement.clientWidth > 0 && mapElement.clientHeight > 0) {
                // Leaflet can cache a zero size while the location step is hidden.
                map.invalidateSize({ pan: false, animate: false });
                map.flyTo(latlng, zoom, { duration: 0.75 });
            } else {
                // The location step starts hidden. Animating a zero-size map
                // produces invalid coordinates; its size is refreshed on step 2.
                map.setView(latlng, zoom, { animate: false });
            }

            if (options.placePin) {
                placeMarker(latlng, options.message || 'Starter pin placed. Drag the marker or click the map to set the exact service point.', {
                    previewMessage: options.previewMessage,
                    skipReverseLookup: options.skipReverseLookup,
                });
            } else if (preview && options.previewMessage) {
                preview.textContent = options.previewMessage;
            }

            return true;
        }

        function populateRegions() {
            setOptions(regionSelect, addressCatalog.map(region => region.name), 'Select Region', selectedValues.region, {
                placeholder: false,
                selectFirst: true,
            });
            selectedValues.region = regionSelect ? regionSelect.value : selectedValues.region;
        }

        function populateProvinces(selectedProvinceValue) {
            const region = getRegionItem(regionSelect.value);
            const provinces = region ? (region.provinces || []).map(province => province.name) : [];
            setOptions(provinceSelect, provinces, 'Select Province', selectedProvinceValue || selectedValues.province, {
                placeholder: false,
                selectFirst: true,
            });
            selectedValues.province = provinceSelect ? provinceSelect.value : selectedValues.province;
        }

        function populateMunicipalities(selectedMunicipalityValue) {
            const province = getProvinceItem(regionSelect.value, provinceSelect.value);
            const municipalities = province ? (province.municipalities || []).map(city => city.name) : [];
            setOptions(municipalitySelect, municipalities, 'Select Municipality / City', selectedMunicipalityValue || '');
        }

        function populateBarangays(selectedBarangayValue) {
            const municipality = getMunicipalityItem(regionSelect.value, provinceSelect.value, municipalitySelect.value);
            const barangays = municipality ? (municipality.barangays || []) : [];

            if (!barangaySelect || !barangayManualInput) {
                return;
            }

            if (barangays.length) {
                setOptions(barangaySelect, barangays, 'Select Barangay / Zone', selectedBarangayValue || '');
                barangaySelect.hidden = false;
                barangaySelect.disabled = false;
                barangaySelect.required = true;
                barangayManualInput.hidden = true;
                barangayManualInput.value = '';
                barangayManualInput.required = false;
                if (barangayHelper) {
                    barangayHelper.textContent = 'Choose your Barangay / Zone from the list.';
                }
            } else {
                barangaySelect.innerHTML = '<option value="">Select Barangay / Zone</option>';
                barangaySelect.hidden = true;
                barangaySelect.disabled = true;
                barangaySelect.required = false;
                barangayManualInput.hidden = !municipalitySelect.value;
                barangayManualInput.value = municipalitySelect.value ? (selectedBarangayValue || '') : '';
                barangayManualInput.required = Boolean(municipalitySelect.value);
                if (barangayHelper) {
                    barangayHelper.textContent = municipalitySelect.value
                        ? 'Type the Barangay / Zone manually for this municipality.'
                        : 'Select Municipality / City first.';
                }
            }

            syncBarangayValue();
        }

        function handleRegionChange() {
            selectedValues.region = regionSelect.value;
            selectedValues.province = '';
            selectedValues.municipality = '';
            selectedValues.barangay = '';
            populateProvinces('');
            selectedValues.province = provinceSelect.value;
            populateMunicipalities('');
            populateBarangays('');
            clearPin('Select a municipality to place a starter pin on the map.');
            focusMapTarget(getRegionItem(regionSelect.value), {
                previewMessage: regionSelect.value ? `Map focused on ${regionSelect.value}. Continue with Municipality / City.` : 'Select a municipality to focus the map.',
                zoom: 8,
            });
            updateReview();
        }

        function handleProvinceChange() {
            selectedValues.province = provinceSelect.value;
            selectedValues.municipality = '';
            selectedValues.barangay = '';
            populateMunicipalities('');
            populateBarangays('');
            clearPin('Select a municipality to place a starter pin on the map.');
            focusMapTarget(getProvinceItem(regionSelect.value, provinceSelect.value), {
                previewMessage: provinceSelect.value ? `Map focused on ${provinceSelect.value}. Select Municipality / City next.` : 'Select a municipality to focus the map.',
                zoom: 10,
            });
            updateReview();
        }

        function handleMunicipalityChange(resetBarangay = true) {
            if (typeof resetBarangay !== 'boolean') {
                resetBarangay = true;
            }

            latestGeocodeRequest += 1;
            selectedValues.municipality = municipalitySelect.value;
            if (resetBarangay) {
                selectedValues.barangay = '';
            }
            populateBarangays(resetBarangay ? '' : selectedValues.barangay);
            const municipality = getMunicipalityItem(regionSelect.value, provinceSelect.value, municipalitySelect.value);
            focusMapTarget(municipality, {
                placePin: Boolean(municipality),
                message: municipality ? `Starter pin placed for ${municipality.name}. Drag the marker or click the map to set the exact service point.` : '',
                previewMessage: municipality ? `Map focused on ${municipality.name}.` : 'Select a municipality to focus the map.',
                zoom: municipality && municipality.zoom ? municipality.zoom : 13,
            });
            updateReview();
        }

        function normalizeSearchText(value) {
            return String(value || '')
                .toLowerCase()
                .replace(/[^a-z0-9]+/g, ' ')
                .trim();
        }

        function normalizeGeocodeQueries(queryInputs) {
            if (!Array.isArray(queryInputs)) {
                return [];
            }

            const queries = queryInputs.every(item => typeof item === 'string')
                ? [queryInputs.filter(Boolean).join(', ')]
                : queryInputs.map(item => Array.isArray(item) ? item.filter(Boolean).join(', ') : String(item || ''));

            return [...new Set(queries.map(query => query.trim()).filter(Boolean))];
        }

        function bestGeocodeTarget(results, expected = {}) {
            if (!Array.isArray(results)) {
                return null;
            }

            const expectedMunicipality = normalizeSearchText(expected.municipality);
            const expectedBarangay = normalizeSearchText(expected.barangay);
            const candidates = [];

            for (const result of results) {
                const lat = parseFloat(result.lat);
                const lng = parseFloat(result.lon);
                if (Number.isNaN(lat) || Number.isNaN(lng)) {
                    continue;
                }

                const latlng = L.latLng(lat, lng);
                if (!allowedBounds.contains(latlng)) {
                    continue;
                }

                const displayName = normalizeSearchText(result.display_name || result.name || '');
                const score = (expectedMunicipality && displayName.includes(expectedMunicipality) ? 2 : 0)
                    + (expectedBarangay && displayName.includes(expectedBarangay) ? 1 : 0);

                candidates.push({ score, target: { lat, lng, zoom: 16 } });
            }

            if (!candidates.length) {
                return null;
            }

            candidates.sort((a, b) => b.score - a.score);
            if (expectedMunicipality && candidates[0].score < 2) {
                return null;
            }

            return candidates[0].target;
        }

        async function geocodeServiceAddress(queryInputs, expected = {}) {
            if (!allowedBounds || typeof window.L === 'undefined') {
                return null;
            }

            const queries = normalizeGeocodeQueries(queryInputs);
            for (const query of queries) {
                const params = new URLSearchParams({
                    format: 'jsonv2',
                    limit: '5',
                    countrycodes: 'ph',
                    q: query,
                });

                try {
                    const response = await fetch(`https://nominatim.openstreetmap.org/search?${params.toString()}`, {
                        headers: { 'Accept': 'application/json' },
                    });
                    if (!response.ok) {
                        continue;
                    }

                    const target = bestGeocodeTarget(await response.json(), expected);
                    if (target) {
                        return target;
                    }
                } catch (error) {
                    continue;
                }
            }

            return null;
        }

        async function focusGeocodedBarangay(municipality, barangayName) {
            const requestId = ++latestGeocodeRequest;
            const cleanBarangayName = barangayName.replace(/^barangay\s+/i, '').trim();

            if (preview) {
                preview.textContent = `Searching the map for ${barangayName}...`;
            }

            const target = await geocodeServiceAddress([
                [`Barangay ${cleanBarangayName}`, municipality.name, 'Pangasinan', 'Philippines'],
                [cleanBarangayName, municipality.name, 'Pangasinan', 'Philippines'],
                [`Brgy ${cleanBarangayName}`, municipality.name, 'Pangasinan', 'Philippines'],
                [`Barangay ${cleanBarangayName}`, 'Pangasinan', 'Philippines'],
            ], {
                barangay: cleanBarangayName,
                municipality: municipality.name,
            });

            if (
                requestId !== latestGeocodeRequest
                || selectedValues.barangay !== barangayName
                || selectedValues.municipality !== municipality.name
            ) {
                return;
            }

            if (target) {
                focusMapTarget(target, {
                    placePin: true,
                    message: `Starter pin moved to ${barangayName}. Drag the marker or click the map for the exact service point.`,
                    previewMessage: `Map focused on ${barangayName}.`,
                    zoom: target.zoom,
                });
                return;
            }

            setStatus(`Starter pin is near ${barangayName}. Drag it or click the map for the exact service point.`, false);
            if (preview) {
                preview.textContent = `Could not automatically find an exact map point for ${barangayName}. The starter pin is near the selected barangay.`;
            }
        }

        function handleBarangayChange() {
            latestGeocodeRequest += 1;
            selectedValues.barangay = syncBarangayValue();
            const municipality = getMunicipalityItem(regionSelect.value, provinceSelect.value, municipalitySelect.value);
            const barangay = getBarangayItem(regionSelect.value, provinceSelect.value, municipalitySelect.value, selectedValues.barangay);
            const hasBarangayCoordinates = hasCoordinatePair(barangay);
            const target = selectedValues.barangay
                ? (hasBarangayCoordinates ? barangay : buildBarangayStarterTarget(municipality, selectedValues.barangay))
                : (municipality ? { lat: municipality.lat, lng: municipality.lng, zoom: (municipality.zoom || 13) + 1 } : null);
            const targetIsEstimated = Boolean(target && target.estimated);

            focusMapTarget(target, {
                placePin: Boolean(target),
                message: targetIsEstimated
                    ? `Starter pin moved near ${selectedValues.barangay}. Searching for an exact barangay map point...`
                    : (selectedValues.barangay ? `Starter pin moved to ${selectedValues.barangay}. Drag the marker or click the map for the exact service point.` : ''),
                previewMessage: selectedValues.barangay
                    ? (targetIsEstimated ? `Map focused near ${selectedValues.barangay}.` : `Map focused on ${selectedValues.barangay}.`)
                    : 'Select a barangay to refine the map.',
                zoom: target && target.zoom ? target.zoom : 14,
                skipReverseLookup: targetIsEstimated,
            });

            if (selectedValues.barangay && municipality && !hasBarangayCoordinates) {
                focusGeocodedBarangay(municipality, selectedValues.barangay);
            }

            updateReview();
        }

        window.customerApplyWizardNext = function (step) {
            Promise.resolve(validateStep(currentStep)).then(valid => {
                if (valid) {
                    setStep(step);
                }
            });
            return false;
        };

        window.customerApplyWizardPrev = function (step) {
            return setStep(step);
        };

        document.querySelectorAll('[data-next-step]').forEach(button => {
            button.addEventListener('click', function (event) {
                event.preventDefault();
                window.customerApplyWizardNext(this.dataset.nextStep);
            });
        });

        document.querySelectorAll('[data-prev-step]').forEach(button => {
            button.addEventListener('click', function (event) {
                event.preventDefault();
                window.customerApplyWizardPrev(this.dataset.prevStep);
            });
        });

        if (regionSelect && provinceSelect && municipalitySelect && barangayValueInput) {
            populateRegions();
            populateProvinces(selectedValues.province);
            populateMunicipalities(selectedValues.municipality);
            populateBarangays(selectedValues.barangay);

            regionSelect.addEventListener('change', handleRegionChange);
            provinceSelect.addEventListener('change', handleProvinceChange);
            municipalitySelect.addEventListener('change', handleMunicipalityChange);
            if (barangaySelect) {
                barangaySelect.addEventListener('change', handleBarangayChange);
            }
            if (barangayManualInput) {
                barangayManualInput.addEventListener('input', function () {
                    syncBarangayValue();
                    updateReview();
                });
                barangayManualInput.addEventListener('blur', handleBarangayChange);
            }
        }

        try {
            if (typeof window.L !== 'undefined' && mapElement) {
                allowedBounds = L.latLngBounds([
                    serviceBounds.south, serviceBounds.west
                ], [
                    serviceBounds.north, serviceBounds.east
                ]);

                map = L.map(mapElement, {
                    center: serviceBounds.center,
                    zoom: serviceBounds.zoom,
                    minZoom: 8,
                    maxZoom: 18,
                    maxBounds: allowedBounds,
                    maxBoundsViscosity: 1.0,
                    zoomControl: true,
                    attributionControl: true,
                });

                L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                    maxZoom: 19,
                    attribution: '&copy; OpenStreetMap contributors'
                }).addTo(map);

                map.on('click', function (event) { placeMarker(event.latlng, 'Pin placed manually.'); });

                const existingLat = parseFloat(mapElement.dataset.lat);
                const existingLng = parseFloat(mapElement.dataset.lng);
                if (!Number.isNaN(existingLat) && !Number.isNaN(existingLng)) {
                    const existingLatLng = L.latLng(existingLat, existingLng);
                    if (allowedBounds.contains(existingLatLng)) {
                        placeMarker(existingLatLng, 'Existing service pin loaded.');
                        map.setView(existingLatLng, 16);
                    }
                } else if (selectedValues.municipality) {
                    handleMunicipalityChange(false);
                    if (selectedValues.barangay) {
                        handleBarangayChange();
                    }
                } else if (selectedValues.province) {
                    focusMapTarget(getProvinceItem(selectedValues.region, selectedValues.province), {
                        previewMessage: `Map focused on ${selectedValues.province}. Select Municipality / City next.`,
                        zoom: 10,
                    });
                } else if (selectedValues.region) {
                    focusMapTarget(getRegionItem(selectedValues.region), {
                        previewMessage: `Map focused on ${selectedValues.region}. Continue with Province and Municipality.`,
                        zoom: 8,
                    });
                }
            } else {
                setStatus('Map failed to load. You can still move through the form and refresh if needed.', true);
            }
        } catch (error) {
            console.error('Customer apply map init failed:', error);
            setStatus('Map failed to load. You can still move through the form and refresh if needed.', true);
        }

        form.querySelectorAll('input, select, textarea').forEach(field => {
            field.addEventListener('input', updateReview);
            field.addEventListener('change', updateReview);
        });

        if (emailInput) {
            emailInput.addEventListener('input', function () {
                latestContactRequest += 1;
                const emailValid = validateEmailFormat();
                updateReview();
                if (emailInput.value !== '' && emailValid) {
                    scheduleContactDuplicateCheck();
                } else {
                    clearTimeout(contactCheckTimer);
                }
            });

            emailInput.addEventListener('blur', function () {
                emailInput.value = normalizeEmailValue(emailInput.value);
                updateReview();
                if (validateEmailFormat()) {
                    runContactDuplicateCheck();
                }
            });
        }

        if (mobileInput) {
            mobileInput.addEventListener('input', function () {
                latestContactRequest += 1;
                const mobileValid = validateMobileNumber({ enforceComplete: false });
                updateReview();
                if (mobilePattern.test(mobileInput.value.trim()) && mobileValid) {
                    scheduleContactDuplicateCheck();
                } else {
                    clearTimeout(contactCheckTimer);
                }
            });

            mobileInput.addEventListener('blur', function () {
                mobileInput.value = normalizeMobileValue(mobileInput.value);
                updateReview();
                if (validateMobileNumber()) {
                    runContactDuplicateCheck();
                }
            });
        }

        [
            [firstNameInput, firstNameLiveError, 'First name'],
            [lastNameInput, lastNameLiveError, 'Last name'],
        ].forEach(([field, errorEl, label]) => {
            if (!field) return;

            field.addEventListener('input', function () {
                validateNameField(field, errorEl, label);
            });

            field.addEventListener('blur', function () {
                validateNameField(field, errorEl, label);
            });
        });

        updateReview();
        setStep(currentStep);
    })();
</script>








