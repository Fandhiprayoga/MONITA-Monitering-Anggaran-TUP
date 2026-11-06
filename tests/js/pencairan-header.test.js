import test from 'node:test';
import assert from 'node:assert/strict';
import { mountBudgetHeader } from '../../public/js/monita/pencairan-header.js';

function header(id, unit) {
    return {
        id, kode_pp: unit, drk_tup: id, tipe: 'Operasional',
        kode_akun: '001', nama_akun: 'Akun', kode_pp_sheet: '8910', nama_pp: unit,
        kode_drk: 'SIM1', uraian_drk: 'Kegiatan', saldo_awal: '100,00',
        anggaran_terpakai: '0,00', saldo_terkini: '100,00',
    };
}

function mount(t) {
    const option = (data) => ({
        value: data?.id ?? '', textContent: data?.drk_tup ?? '-- Pilih DRK --',
        dataset: data ? { header: JSON.stringify(data) } : {},
    });
    const unitEvents = {};
    const selectEvents = {};
    const select = {
        dispatchEvent() {},
        value: 'original', options: [option(), option(header('original', 'PP01'))],
        replaceChildren(...children) { this.options = children; },
        append(...children) { this.options.push(...children); },
        get selectedOptions() { return this.options.filter((item) => item.value === this.value); },
        addEventListener(event, handler) { selectEvents[event] = handler; },
    };
    const unit = {
        value: 'PP01', dataset: { drkUrl: 'http://localhost/pencairan/drk-options' },
        addEventListener(event, handler) { unitEvents[event] = handler; },
    };
    const error = { textContent: '', classList: { add() {}, remove() {} } };
    const sourceMessage = { hidden: false };
    const fields = Object.fromEntries([
        '#kode-akun', '#nama-akun', '#kode-pp', '#nama-pp', '#kode-drk',
        '#uraian-drk', '#saldo_awal', '#anggaran_terpakai', '#saldo_terkini',
    ].map((key) => [key, { value: '' }]));
    const status = { textContent: '' };
    const form = {
        dataset: {}, dispatchEvent() {},
        querySelector(selector) {
            return {
                '#pilih-drk': select, '[data-header-error]': error,
                '#cari-drk': { value: '', addEventListener() {} },
                '#hasil-cari-drk': status,
                ...fields,
            }[selector];
        },
        querySelectorAll: () => [sourceMessage],
    };
    for (const [name, value] of Object.entries({
        document: { getElementById: () => unit, createElement: () => option() },
        window: { location: { href: 'http://localhost/pencairan/form/beban' } },
    })) {
        const descriptor = Object.getOwnPropertyDescriptor(globalThis, name);
        Object.defineProperty(globalThis, name, { configurable: true, value });
        t.after(() => {
            if (descriptor) Object.defineProperty(globalThis, name, descriptor);
            else delete globalThis[name];
        });
    }
    mountBudgetHeader(form);
    return { form, unit, select, fields, error, status, sourceMessage, change: unitEvents.change, selectChange: selectEvents.change };
}

test('changing unit clears old DRK/header and populates the new unit options', async (t) => {
    const state = mount(t);
    assert.equal(state.fields['#kode-akun'].value, '001');
    let finish;
    t.mock.method(globalThis, 'fetch', (url) => {
        assert.equal(url.searchParams.get('unit_kode_pp'), 'PP02');
        return new Promise((resolve) => { finish = resolve; });
    });
    state.unit.value = 'PP02';
    const loading = state.change();
    assert.equal(state.select.value, '');
    assert.equal(state.fields['#kode-akun'].value, '');
    assert.equal(state.select.disabled, true);
    assert.equal(state.form.dataset.drkLoading, 'true');
    finish({ ok: true, json: async () => ({ options: [header('new', 'PP02')] }) });
    await loading;
    assert.equal(state.select.options.length, 2);
    assert.equal(state.status.textContent, '1 DRK ditemukan');
    assert.equal(state.select.disabled, false);
    assert.equal(state.sourceMessage.hidden, true);
    assert.equal(state.form.dataset.drkLoading, 'false');
    state.select.value = 'new';
    state.selectChange();
    assert.equal(state.fields['#nama-pp'].value, 'PP02');
});

test('a stale unit response cannot overwrite the latest selection', async (t) => {
    const state = mount(t);
    const responses = [];
    t.mock.method(globalThis, 'fetch', () => new Promise((resolve) => responses.push(resolve)));
    state.unit.value = 'PP02';
    const first = state.change();
    state.unit.value = 'PP03';
    const second = state.change();
    responses[1]({ ok: true, json: async () => ({ options: [header('latest', 'PP03')] }) });
    await second;
    responses[0]({ ok: true, json: async () => ({ options: [header('stale', 'PP02')] }) });
    await first;
    assert.deepEqual(state.select.options.map((item) => item.value), ['', 'latest']);
});

test('empty units and source failures never retain old DRK options', async (t) => {
    const state = mount(t);
    t.mock.method(globalThis, 'fetch', async () => ({ ok: true, json: async () => ({ options: [] }) }));
    state.unit.value = 'PP02';
    await state.change();
    assert.equal(state.select.disabled, true);
    assert.equal(state.status.textContent, '0 DRK ditemukan');
    t.mock.method(globalThis, 'fetch', async () => ({ ok: false, json: async () => ({ message: 'Sumber tidak tersedia' }) }));
    t.mock.method(console, 'error', () => {});
    await state.change();
    assert.equal(state.error.textContent, 'Sumber tidak tersedia');
    assert.equal(state.select.options.length, 1);
    assert.equal(state.form.dataset.drkLoading, 'false');
});
