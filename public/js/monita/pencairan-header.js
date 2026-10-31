export function mountBudgetHeader(form) {
    const select = form.querySelector('#pilih-drk');
    const error = form.querySelector('[data-header-error]');
    const search = form.querySelector('#cari-drk');
    const searchStatus = form.querySelector('#hasil-cari-drk');
    const options = [...select.options];

    function filterOptions() {
        const query = search.value.trim().toLocaleLowerCase('id-ID');
        const selected = select.value;
        const matches = options.filter((option) => option.value && option.textContent.toLocaleLowerCase('id-ID').includes(query));
        select.replaceChildren(...options.filter((option) => !option.value || option.value === selected || matches.includes(option)));
        select.value = selected;
        const retained = selected && !matches.some((option) => option.value === selected);
        searchStatus.textContent = `${matches.length} DRK ditemukan.${retained ? ' DRK terpilih tetap ditampilkan meskipun tidak cocok dengan pencarian.' : ''}`;
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
            Object.entries(fields).forEach(([key, selector]) => { form.querySelector(selector).value = data[key]; });
        } catch (exception) {
            console.error('Gagal mengisi header DRK.', exception);
            error.textContent = 'Gagal mengisi informasi DRK. Silakan muat ulang halaman atau hubungi administrator.';
            error.classList.remove('d-none');
        }
    }

    search.addEventListener('input', filterOptions);
    select.addEventListener('change', () => {
        update();
        filterOptions();
    });
    filterOptions();
    update();
}
