(() => {
    'use strict';
    const form = document.getElementById('customerApplyWizard');
    const save = document.getElementById('applicationDraftSave');
    if (!form || !save) return;
    const discard = document.getElementById('applicationDraftDiscard');
    const token = document.getElementById('applicationDraftToken');
    const status = document.getElementById('applicationDraftStatus');
    const photo = document.getElementById('id_selfie_image');
    const idImage = document.getElementById('id_image');
    let busy = false;
    let leaving = false;
    let autoSaveTimer = null;
    let saveQueued = false;
    const autoSaveDelay = 1400;
    const step = () => document.querySelector('[data-panel].is-active')?.dataset.panel || '1';
    function snapshot() {
        const fields = [...new FormData(form)].filter(([name]) => !['_token', 'draft_token'].includes(name))
            .map(([name, value]) => [name, value instanceof File ? [value.name, value.size, value.lastModified] : value]);
        // Empty file inputs create fresh File objects; their timestamps are not edits.
        fields.forEach(([, value]) => { if (Array.isArray(value) && value[0] === '') value[2] = 0; });
        return JSON.stringify([fields, step()]);
    }
    let savedState = snapshot();
    function message(text, error = false) {
        status.textContent = text;
        status.dataset.error = String(error);
    }
    function setBusy(value) {
        busy = value;
        save.disabled = value;
        discard.disabled = value || !token.value;
        form.querySelectorAll('button[type=submit]').forEach(button => { button.disabled = value; });
        save.textContent = value ? 'Please wait...' : 'Save now';
    }
    async function send(url, options) {
        const controller = new AbortController();
        const timer = setTimeout(() => controller.abort(), 20000);
        try {
            const response = await fetch(url, { ...options, signal: controller.signal, headers: {
                Accept: 'application/json', 'X-CSRF-TOKEN': form.querySelector('[name=_token]').value,
            }});
            const data = response.status === 204 ? {} : await response.json().catch(() => ({}));
            if (!response.ok) {
                if ([401, 419].includes(response.status)) throw new Error('Your session expired. Sign in again to save; your last saved draft is retained.');
                throw new Error(Object.values(data.errors || {}).flat()[0] || data.message || 'Draft was not saved. Please try again.');
            }
            return data;
        } finally { clearTimeout(timer); }
    }
    async function saveDraft({ automatic = false } = {}) {
        clearTimeout(autoSaveTimer);
        autoSaveTimer = null;
        if (busy) {
            saveQueued = true;
            return;
        }
        if (automatic && snapshot() === savedState) return;
        const sentState = snapshot();
        const sentPhoto = photo.files[0];
        const sentIdImage = idImage.files[0];
        const body = new FormData(form);
        body.set('draft_step', step());
        if (!sentPhoto) body.delete('id_selfie_image');
        if (!sentIdImage) body.delete('id_image');
        setBusy(true);
        message(automatic ? 'Saving your progress automatically...' : 'Saving your draft...');
        try {
            const data = await send(form.dataset.draftSaveUrl, { method: 'POST', body });
            if (typeof data.token !== 'string' || !data.saved_at) throw new Error('Could not confirm the save. Your session may have expired; reload Apply after signing in.');
            const unchanged = snapshot() === sentState;
            token.value = data.token;
            photo.dataset.savedPhotoUrl = data.photo_url || '';
            photo.required = !data.photo_url;
            idImage.dataset.savedPhotoUrl = data.id_image_url || '';
            idImage.required = !data.id_image_url;
            if (photo.files[0] === sentPhoto) photo.value = '';
            if (idImage.files[0] === sentIdImage) idImage.value = '';
            photo.dispatchEvent(new Event('change', { bubbles: true }));
            idImage.dispatchEvent(new Event('change', { bubbles: true }));
            savedState = unchanged ? snapshot() : sentState;
            message((automatic ? 'Progress saved automatically at ' : 'Draft saved at ') +
                new Date(data.saved_at).toLocaleString() + '. ' +
                (unchanged ? 'You can safely return to Apply later.' : 'Saving your newer edits next...'));
        } catch (error) {
            message((automatic ? 'Automatic save failed. ' : '') + (error.name === 'AbortError' || error instanceof TypeError
                ? 'Could not confirm the save. Keep this page open, check your connection, and try again. Reload Apply if it reports a draft conflict.'
                : error.message), true);
        } finally {
            setBusy(false);
            if (saveQueued || snapshot() !== sentState) {
                saveQueued = false;
                scheduleAutoSave();
            }
        }
    }
    function scheduleAutoSave() {
        if (leaving) return;
        clearTimeout(autoSaveTimer);
        if (busy) {
            saveQueued = true;
            return;
        }
        if (snapshot() === savedState) return;
        message('Changes detected. Your progress will save automatically...');
        autoSaveTimer = setTimeout(() => saveDraft({ automatic: true }), autoSaveDelay);
    }
    save.addEventListener('click', () => saveDraft());
    form.addEventListener('input', scheduleAutoSave);
    form.addEventListener('change', scheduleAutoSave);
    form.addEventListener('click', event => {
        if (event.target.closest('[data-next-step], [data-prev-step]')) {
            setTimeout(scheduleAutoSave, 0);
        }
    });
    discard.addEventListener('click', async () => {
        if (busy || !token.value || !window.confirm('Discard this saved draft and its ID photos? This cannot be undone.')) return;
        const body = new FormData();
        body.set('draft_token', token.value);
        body.set('_method', 'DELETE');
        setBusy(true);
        try {
            await send(form.dataset.draftDiscardUrl, { method: 'POST', body });
            leaving = true;
            window.location.reload();
        } catch (error) { message('Draft was not discarded. ' + error.message, true); }
        finally { setBusy(false); }
    });
    form.addEventListener('submit', event => {
        if (busy) { event.preventDefault(); return; }
        leaving = true;
    });
    window.addEventListener('beforeunload', event => {
        if (leaving || (!busy && snapshot() === savedState)) return;
        event.preventDefault();
        event.returnValue = '';
    });
})();
