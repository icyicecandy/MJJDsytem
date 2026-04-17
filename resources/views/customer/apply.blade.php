@push('styles')
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin="" />
@endpush

<x-app-layout>
    @php
        $currentLat = old('lat', $application->lat ?? null);
        $currentLng = old('lng', $application->lng ?? null);
        $selectedRegion = old('region', $application->region ?? '');
        $selectedProvince = old('province', $application->province ?? '');
        $selectedMunicipality = old('municipality', $application->municipality ?? '');
        $selectedBarangay = old('barangay', $application->barangay ?? '');
        $luzonBounds = $mapBounds ?? ['south' => 11.8, 'west' => 118.8, 'north' => 19.9, 'east' => 123.9, 'center' => [16.2, 121.0], 'zoom' => 7];
        $initialStep = ($errors->has('agreed_to_terms') || $errors->has('agreed_to_privacy')) ? 5 : 1;
    @endphp

    <div class="apply-wizard-shell">
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
            </section>
        @endif

        <form method="POST" action="{{ route('customer.apply.store') }}" enctype="multipart/form-data" class="apply-wizard-form" id="customerApplyWizard" novalidate>
            @csrf
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
                        <input id="first_name" type="text" name="first_name" value="{{ old('first_name', $application->first_name ?? '') }}" required>
                        @error('first_name')<span class="error">{{ $message }}</span>@enderror
                    </div>

                    <div class="apply-field">
                        <label for="last_name">Last Name</label>
                        <input id="last_name" type="text" name="last_name" value="{{ old('last_name', $application->last_name ?? '') }}" required>
                        @error('last_name')<span class="error">{{ $message }}</span>@enderror
                    </div>

                    <div class="apply-field">
                        <label for="email">Email Address</label>
                        <input id="email" type="email" name="email" value="{{ old('email', $application->email ?? auth()->user()->email) }}" required>
                        @error('email')<span class="error">{{ $message }}</span>@enderror
                    </div>

                    <div class="apply-field">
                        <label for="mobile_number">Mobile Number</label>
                        <input id="mobile_number" type="text" name="mobile_number" value="{{ old('mobile_number', $application->mobile_number ?? auth()->user()->phone) }}" placeholder="09XX XXX XXXX" required>
                        @error('mobile_number')<span class="error">{{ $message }}</span>@enderror
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
                        <p>Select the service address from the Luzon service areas, then pin the exact location on the map.</p>
                    </div>
                </div>

                <div class="apply-form-grid">
                    <div class="apply-field">
                        <label for="region">Region</label>
                        <select id="region" name="region" required></select>
                        @error('region')<span class="error">{{ $message }}</span>@enderror
                    </div>

                    <div class="apply-field">
                        <label for="province">Province</label>
                        <select id="province" name="province" required disabled></select>
                        @error('province')<span class="error">{{ $message }}</span>@enderror
                    </div>

                    <div class="apply-field">
                        <label for="municipality">Municipality / City</label>
                        <select id="municipality" name="municipality" required disabled></select>
                        @error('municipality')<span class="error">{{ $message }}</span>@enderror
                    </div>

                    <div class="apply-field">
                        <label for="barangay">Barangay / Zone</label>
                        <select id="barangay" name="barangay" required disabled></select>
                        @error('barangay')<span class="error">{{ $message }}</span>@enderror
                    </div>

                    <div class="apply-field full-span">
                        <label for="street_landmark">Street / Landmark / House Details</label>
                        <input id="street_landmark" type="text" name="street_landmark" value="{{ old('street_landmark', $application->street_landmark ?? '') }}" placeholder="House #, street name, purok, nearby landmarks">
                        @error('street_landmark')<span class="error">{{ $message }}</span>@enderror
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
                <p class="apply-map-status" id="applyMapStatus">Pinning is limited to Luzon operational coverage.</p>
                @error('lat')<span class="error">{{ $message }}</span>@enderror
                @error('lng')<span class="error">{{ $message }}</span>@enderror

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
                    <label class="apply-plan-option {{ old('plan_code', $application->plan_code ?? '') === 'S2S_50' ? 'selected' : '' }}">
                        <input type="radio" name="plan_code" value="S2S_50" {{ old('plan_code', $application->plan_code ?? '') === 'S2S_50' ? 'checked' : '' }} required>
                        <div>
                            <strong>Surf2Sawa Prepaid 50 Mbps</strong>
                            <span>Good for daily browsing, messaging, streaming, and home use.</span>
                        </div>
                        <div class="apply-plan-price">PHP 700</div>
                    </label>

                    <label class="apply-plan-option {{ old('plan_code', $application->plan_code ?? '') === 'S2S_100' ? 'selected' : '' }}">
                        <input type="radio" name="plan_code" value="S2S_100" {{ old('plan_code', $application->plan_code ?? '') === 'S2S_100' ? 'checked' : '' }} required>
                        <div>
                            <strong>Surf2Sawa Prepaid 100 Mbps</strong>
                            <span>Higher-speed access for multiple users, devices, and heavier streaming.</span>
                        </div>
                        <div class="apply-plan-price">PHP 999</div>
                    </label>
                </div>
                @error('plan_code')<span class="error">{{ $message }}</span>@enderror

                <div class="apply-step-actions between">
                    <button type="button" class="apply-link-btn" data-prev-step="2" onclick="return window.customerApplyWizardPrev ? window.customerApplyWizardPrev(2) : false;">Back</button>
                    <button type="button" class="btn accent" data-next-step="4" onclick="return window.customerApplyWizardNext ? window.customerApplyWizardNext(4) : false;">Upload ID</button>
                </div>
            </section>

            <section class="panel-card apply-step-panel" data-panel="4">
                <div class="apply-step-head">
                    <div>
                        <h1>Upload Valid ID</h1>
                        <p>Attach one clear image of a valid ID for account verification.</p>
                    </div>
                </div>

                <div class="upload-field apply-upload-field">
                    <label for="id_image">Valid ID Image</label>
                    <input id="id_image" type="file" name="id_image" accept=".jpg,.jpeg,.png" required>
                    @error('id_image')<span class="error">{{ $message }}</span>@enderror
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
                    <div class="tracking-meta"><strong>ID Upload</strong><span id="reviewId">Required</span></div>
                </div>

                @include('partials.consent-agreement', [
                    'consentPrefix' => 'application_consent',
                    'formId' => 'customerApplyWizard',
                    'helperText' => 'Please read and agree to the Terms and Conditions and Privacy Notice before proceeding.'
                ])

                <div class="apply-step-actions between">
                    <button type="button" class="apply-link-btn" data-prev-step="4" onclick="return window.customerApplyWizardPrev ? window.customerApplyWizardPrev(4) : false;">Back</button>
                    <button type="submit" class="btn">Submit Application</button>
                </div>
            </section>
        </form>
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
        const fileInput = document.getElementById('id_image');
        const regionSelect = document.getElementById('region');
        const provinceSelect = document.getElementById('province');
        const municipalitySelect = document.getElementById('municipality');
        const barangaySelect = document.getElementById('barangay');
        const streetInput = document.getElementById('street_landmark');
        const addressCatalog = @json($addressCatalog ?? []);
        const selectedValues = {
            region: @json($selectedRegion),
            province: @json($selectedProvince),
            municipality: @json($selectedMunicipality),
            barangay: @json($selectedBarangay),
        };
        const luzonBounds = @json($luzonBounds);

        if (!form || !panels.length || !steps.length) {
            return;
        }

        let allowedBounds = null;
        let currentStep = @json($initialStep);
        let marker = null;
        let map = null;
        let reverseLookupTimer = null;

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
            setText('reviewId', fileInput && fileInput.files.length ? fileInput.files[0].name : 'Required');
            updateAddressSummary();
        }

        function setStep(step) {
            currentStep = Number(step);
            panels.forEach(panel => panel.classList.toggle('is-active', Number(panel.dataset.panel) === currentStep));
            steps.forEach(item => {
                const itemStep = Number(item.dataset.step);
                item.classList.toggle('is-active', itemStep === currentStep);
                item.classList.toggle('is-complete', itemStep < currentStep);
            });
            updateReview();
            if (currentStep === 2 && map) {
                setTimeout(() => map.invalidateSize(), 80);
            }
            return false;
        }

        function validateApplicantStep() {
            const ids = ['first_name', 'last_name', 'email', 'mobile_number'];
            for (const id of ids) {
                const field = document.getElementById(id);
                if (field && !field.checkValidity()) {
                    field.reportValidity();
                    return false;
                }
            }
            return true;
        }

        function validateLocationStep() {
            const ids = ['region', 'province', 'municipality', 'barangay'];
            for (const id of ids) {
                const field = document.getElementById(id);
                if (field && !field.checkValidity()) {
                    field.reportValidity();
                    return false;
                }
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

        function validateStep(step) {
            step = Number(step);
            if (step === 1) return validateApplicantStep();
            if (step === 2) return validateLocationStep();
            if (step === 3) return validatePlanStep();
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

        function placeMarker(latlng, message) {
            if (!allowedBounds || !allowedBounds.contains(latlng)) {
                setStatus('Selected point is outside the allowed Luzon service area. Please choose a location within Luzon.', true);
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
            reverseGeocode(latlng);
            return true;
        }

        function setOptions(select, options, placeholder, selectedValue) {
            if (!select) return;
            select.innerHTML = '';

            const placeholderOption = document.createElement('option');
            placeholderOption.value = '';
            placeholderOption.textContent = placeholder;
            select.appendChild(placeholderOption);

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
            select.disabled = options.length === 0;
            if (!selectedValue || !optionValues.includes(selectedValue)) {
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

        function focusMapTarget(target, options = {}) {
            if (!map || !target) {
                return;
            }

            const lat = parseFloat(target.lat);
            const lng = parseFloat(target.lng);
            const zoom = parseInt(target.zoom || options.zoom || 14, 10);
            if (Number.isNaN(lat) || Number.isNaN(lng)) {
                return;
            }

            map.flyTo([lat, lng], zoom, { duration: 0.75 });

            if (options.placePin) {
                placeMarker(L.latLng(lat, lng), options.message || 'Starter pin placed. Drag the marker or click the map to set the exact service point.');
            } else if (preview && options.previewMessage) {
                preview.textContent = options.previewMessage;
            }
        }

        function populateRegions() {
            setOptions(regionSelect, addressCatalog.map(region => region.name), 'Select Region', selectedValues.region);
        }

        function populateProvinces(selectedProvinceValue) {
            const region = getRegionItem(regionSelect.value);
            const provinces = region ? (region.provinces || []).map(province => province.name) : [];
            setOptions(provinceSelect, provinces, 'Select Province', selectedProvinceValue || '');
        }

        function populateMunicipalities(selectedMunicipalityValue) {
            const province = getProvinceItem(regionSelect.value, provinceSelect.value);
            const municipalities = province ? (province.municipalities || []).map(city => city.name) : [];
            setOptions(municipalitySelect, municipalities, 'Select Municipality / City', selectedMunicipalityValue || '');
        }

        function populateBarangays(selectedBarangayValue) {
            const municipality = getMunicipalityItem(regionSelect.value, provinceSelect.value, municipalitySelect.value);
            const barangays = municipality ? (municipality.barangays || []) : [];
            setOptions(barangaySelect, barangays, 'Select Barangay / Zone', selectedBarangayValue || '');
        }

        function handleRegionChange() {
            selectedValues.region = regionSelect.value;
            selectedValues.province = '';
            selectedValues.municipality = '';
            selectedValues.barangay = '';
            populateProvinces('');
            populateMunicipalities('');
            populateBarangays('');
            clearPin('Select a municipality to place a starter pin on the map.');
            focusMapTarget(getRegionItem(regionSelect.value), {
                previewMessage: regionSelect.value ? `Map focused on ${regionSelect.value}. Continue with Province and Municipality.` : 'Select a municipality to focus the map.',
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

        function handleMunicipalityChange() {
            selectedValues.municipality = municipalitySelect.value;
            selectedValues.barangay = '';
            populateBarangays('');
            const municipality = getMunicipalityItem(regionSelect.value, provinceSelect.value, municipalitySelect.value);
            focusMapTarget(municipality, {
                placePin: Boolean(municipality),
                message: municipality ? `Starter pin placed for ${municipality.name}. Drag the marker or click the map to set the exact service point.` : '',
                previewMessage: municipality ? `Map focused on ${municipality.name}.` : 'Select a municipality to focus the map.',
                zoom: municipality && municipality.zoom ? municipality.zoom : 13,
            });
            updateReview();
        }

        function handleBarangayChange() {
            selectedValues.barangay = barangaySelect.value;
            const municipality = getMunicipalityItem(regionSelect.value, provinceSelect.value, municipalitySelect.value);
            const barangay = getBarangayItem(regionSelect.value, provinceSelect.value, municipalitySelect.value, barangaySelect.value);
            const target = barangay && typeof barangay === 'object' && barangay.lat && barangay.lng
                ? barangay
                : municipality
                    ? { lat: municipality.lat, lng: municipality.lng, zoom: (municipality.zoom || 13) + 1 }
                    : null;

            focusMapTarget(target, {
                placePin: Boolean(target),
                message: barangaySelect.value ? `Starter pin aligned to ${barangaySelect.value}. Adjust it for the exact house or installation point.` : '',
                previewMessage: barangaySelect.value ? `Map focused on ${barangaySelect.value}.` : 'Select a barangay to refine the map.',
                zoom: target && target.zoom ? target.zoom : 14,
            });
            updateReview();
        }

        window.customerApplyWizardNext = function (step) {
            if (!validateStep(currentStep)) return false;
            return setStep(step);
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

        if (regionSelect && provinceSelect && municipalitySelect && barangaySelect) {
            populateRegions();
            populateProvinces(selectedValues.province);
            populateMunicipalities(selectedValues.municipality);
            populateBarangays(selectedValues.barangay);

            regionSelect.addEventListener('change', handleRegionChange);
            provinceSelect.addEventListener('change', handleProvinceChange);
            municipalitySelect.addEventListener('change', handleMunicipalityChange);
            barangaySelect.addEventListener('change', handleBarangayChange);
        }

        try {
            if (typeof window.L !== 'undefined' && mapElement) {
                allowedBounds = L.latLngBounds([
                    luzonBounds.south, luzonBounds.west
                ], [
                    luzonBounds.north, luzonBounds.east
                ]);

                map = L.map(mapElement, {
                    center: luzonBounds.center,
                    zoom: luzonBounds.zoom,
                    minZoom: 6,
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
                    handleMunicipalityChange();
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

        updateReview();
        setStep(currentStep);
    })();
</script>





