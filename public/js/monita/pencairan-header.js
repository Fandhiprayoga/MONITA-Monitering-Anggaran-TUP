export function mountBudgetHeader(form) {
    const select = form.querySelector('#pilih-drk');
    const error = form.querySelector('[data-header-error]');
    const search = form.querySelector('#cari-drk');
    const searchStatus = form.querySelector('#hasil-cari-drk');
    let options = [...select.options];
    const unit = document.getElementById('pilih-unit');
    let loadId = 0;

    function filterOptions() {
        const query = search.value.trim().toLocaleLowerCase('id-ID');
        const selected = select.value;
        const matches = options.filter((option) => option.value && option.textContent.toLocaleLowerCase('id-ID').includes(query));
        select.replaceChildren(...options.filter((option) => !option.value || option.value === selected || matches.includes(option)));
        select.value = selected;
        select.dispatchEvent(new Event('drk:options-updated'));
        searchStatus.textContent = `${matches.length} DRK ditemukan`;
    }
    const fields = {
        kode_akun: '#kode-akun',
        nama_akun: '#nama-akun',
        kode_pp_sheet: '#kode-pp',
        nama_pp: '#nama-pp',
        kode_drk: '#kode-drk',
        uraian_drk: '#uraian-drk',
        saldo_awal: '#saldo_awal',
        anggaran_terpakai: '#anggaran_terpakai',
        saldo_terkini: '#saldo_terkini',
    };
    const moneyFields = new Set(['saldo_awal', 'anggaran_terpakai', 'saldo_terkini']);

    // Backend sudah mengirim format "1.234,56"; samakan tampilan ke rupiah.
    function formatMoney(value) {
        const parsed = Number(String(value).replace(/\./g, '').replace(',', '.'));
        return Number.isFinite(parsed)
            ? parsed.toLocaleString('id-ID', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
            : value;
    }

    function update() {
        Object.values(fields).forEach((selector) => { form.querySelector(selector).value = ''; });
        error.textContent = '';
        error.classList.add('d-none');
        if (!select.value) return;
        try {
            const data = JSON.parse(select.selectedOptions[0].dataset.header);
            if (!data || !Object.keys(fields).every((key) => typeof data[key] === 'string')) {
                throw new Error('Informasi anggaran DRK tidak lengkap.');
            }
            Object.entries(fields).forEach(([key, selector]) => { const value = moneyFields.has(key) ? formatMoney(data[key]) : data[key]; form.querySelector(selector).value = value; });
        } catch (exception) {
            console.error('Gagal mengisi header DRK.', exception);
            error.textContent = 'Gagal mengisi informasi DRK. Silakan muat ulang halaman atau hubungi administrator.';
            error.classList.remove('d-none');
        }
    }

    search.addEventListener('input', filterOptions);
    if (unit) unit.addEventListener('change', async () => {
        const currentLoad = ++loadId;
        form.dataset.drkLoading = 'true';
        form.dispatchEvent(new Event('change', { bubbles: true }));
        select.replaceChildren(options[0]);
        options = [...select.options];
        select.value = '';
        search.value = '';
        select.disabled = true;
        search.disabled = true;
        form.querySelectorAll('[data-drk-source-message]').forEach((message) => { message.hidden = true; });
        update();
        filterOptions();
        searchStatus.textContent = unit.value ? 'Memuat DRK unit terpilih...' : 'Pilih unit terlebih dahulu.';
        try {
            if (!unit.value) return;
            const url = new URL(unit.dataset.drkUrl, window.location.href);
            url.searchParams.set('unit_kode_pp', unit.value);
            const response = await fetch(url, { credentials: 'same-origin', headers: { Accept: 'application/json' } });
            const result = await response.json();
            if (currentLoad !== loadId) return;
            if (!response.ok) throw new Error(Object.values(result.errors ?? {}).flat().join(' ') || result.message || 'Gagal memuat DRK.');
            if (!Array.isArray(result.options)) throw new Error('Respons daftar DRK tidak valid.');
            const newOptions = result.options.map((header) => {
                const option = document.createElement('option');
                option.value = header.id;
                option.dataset.header = JSON.stringify(header);
                option.textContent = `${header.drk_tup} - SIMKUG: ${header.kode_drk || 'Belum tersedia'} - ${header.kode_akun} - ${header.nama_akun} - ${header.uraian_drk} (${header.tipe})`;
                return option;
            });
            select.append(...newOptions);
            options = [...select.options];
            select.disabled = newOptions.length === 0;
            search.disabled = newOptions.length === 0;
            filterOptions();
        } catch (exception) {
            if (currentLoad !== loadId) return;
            console.error('Gagal memuat DRK unit.', exception);
            error.textContent = exception.message || 'Gagal memuat DRK unit. Silakan coba kembali.';
            error.classList.remove('d-none');
        } finally {
            if (currentLoad === loadId) form.dataset.drkLoading = 'false';
        }
    });
    select.addEventListener('change', () => {
        update();
        filterOptions();
    });
    filterOptions();
    update();
}
