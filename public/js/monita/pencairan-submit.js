export function mountSubmission(form, grid, initial, baseUrl) {
    let uuid = initial?.uuid;
    let version = initial?.lock_version;
    let busy = false;
    let dirty = false;
    let submitKey = null;
    const message = document.getElementById('save-message');
    const buttons = [...form.querySelectorAll('button')];
    const drk = form.querySelector('#pilih-drk');
    const unit = form.elements.namedItem('unit_kode_pp');
    // Disabled source selectors must not erase the saved DRK while Sheets is unavailable.
    const chosenDrk = () => drk.disabled ? initial?.drk_id ?? null : drk.value || null;
    const navigationUrl = (path) => {
        const url = new URL(path, window.location.href);
        url.searchParams.set('menu', form.dataset.menu || 'pengajuan');
        return url.href;
    };
    const markDirty = () => { dirty = true; submitKey = null; };
    form.addEventListener('input', markDirty);
    form.addEventListener('change', markDirty);
    form.querySelector('[data-grid-body]').addEventListener('paste', markDirty);
    form.querySelectorAll('[data-grid-add], [data-grid-delete]').forEach((button) => button.addEventListener('click', markDirty));
    window.addEventListener('beforeunload', (event) => { if (dirty) { event.preventDefault(); event.returnValue = ''; } });
    async function request(url, method, payload) {
        const response = await fetch(url, { method, credentials: 'same-origin', headers: {
            'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': form.querySelector('[name="_token"]').value,
        }, body: JSON.stringify(payload) });
        const result = await response.json().catch(() => ({}));
        if (!response.ok) throw new Error(Object.values(result.errors ?? {}).flat().join(' ') || result.message || 'Permintaan gagal. Silakan coba lagi.');
        return result;
    }
    async function save(submit) {
        if (busy) return;
        if (form.dataset.drkLoading === 'true') {
            message.className = 'alert alert-warning'; message.textContent = 'Tunggu hingga DRK unit selesai dimuat.';
            return;
        }
        if (submit && !window.confirm('Kirim pengajuan untuk verifikasi? Isi akan dikunci setelah berhasil.')) return;
        if (!submit) submitKey = null;
        busy = true; buttons.forEach((button) => { button.disabled = true; });
        if (unit) unit.disabled = true;
        message.className = 'alert alert-info'; message.textContent = 'Menyimpan…';
        try {
            const payload = { jenis: form.dataset.jenis, drk_id: chosenDrk(), uraian_pengajuan: form.querySelector('#uraian-pengajuan').value, details: grid.rows(),
                ...(unit ? { unit_kode_pp: unit.value } : {}) };
            // A failed submit retry retains its idempotency key and version, without rewriting the draft.
            if (!submitKey || !submit) {
                const result = await request(uuid ? `${baseUrl}/${uuid}` : baseUrl, uuid ? 'PATCH' : 'POST', { ...payload, ...(uuid ? { lock_version: version } : {}) });
                uuid = result.uuid; version = result.lock_version; dirty = false;
                history.replaceState(null, '', navigationUrl(`${baseUrl}/${uuid}/edit`));
            }
            if (submit) {
                submitKey ??= crypto.randomUUID();
                const result = await request(`${baseUrl}/${uuid}/submit`, 'POST', { lock_version: version, confirmed: true, idempotency_key: submitKey });
                dirty = false; window.location.assign(navigationUrl(result.redirect_url));
            } else {
                message.className = 'alert alert-success'; message.textContent = 'Isian berhasil disimpan.';
            }
        } catch (error) {
            message.className = 'alert alert-danger'; message.textContent = error.message;
        } finally {
            busy = false; buttons.forEach((button) => { button.disabled = false; });
            if (unit && !uuid) unit.disabled = false;
        }
    }
    form.querySelector('[data-save-draft]').addEventListener('click', () => save(false));
    form.addEventListener('submit', (event) => { event.preventDefault(); save(true); });
}