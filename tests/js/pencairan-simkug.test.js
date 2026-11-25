import test from 'node:test';
import assert from 'node:assert/strict';
import { mountSimkugCopy } from '../../public/js/monita/pencairan-simkug.js';
import { mountSimkugHeaderCopy } from '../../public/js/monita/pencairan-simkug-header.js';

function mockNavigator(t, value) {
    const descriptor = Object.getOwnPropertyDescriptor(globalThis, 'navigator');
    Object.defineProperty(globalThis, 'navigator', { configurable: true, value });
    t.after(() => {
        if (descriptor) Object.defineProperty(globalThis, 'navigator', descriptor);
        else delete globalThis.navigator;
    });
}

function fixture(value = '001234') {
    let click;
    const classes = new Set();
    const status = {
        textContent: '',
        classList: {
            add: (name) => classes.add(name),
            remove: (name) => classes.delete(name),
        },
    };
    const input = {
        value,
        focused: false,
        selected: false,
        focus() { this.focused = true; },
        select() { this.selected = true; },
    };
    const button = {
        disabled: false,
        parentElement: { querySelector: () => input },
        getAttribute: () => 'Salin Kode akun',
        addEventListener: (event, handler) => { assert.equal(event, 'click'); click = handler; },
    };
    mountSimkugCopy({
        querySelector: () => status,
        querySelectorAll: () => [button],
    });
    return { input, button, status, classes, click: () => click() };
}

test('SIMKUG copies exact text, preserving leading zeros, quotes and multiline narrative', async (t) => {
    const copied = [];
    mockNavigator(t, { clipboard: { writeText: async (value) => copied.push(value) } });
    for (const value of ['001234', 'Narasi "SIMKUG" & <uji>\nBaris kedua', '0,00', '']) {
        const view = fixture(value);
        await view.click();
        assert.equal(copied.at(-1), value);
        assert.equal(view.status.textContent, 'Kode akun berhasil disalin.');
        assert.ok(view.classes.has('text-success'));
        assert.equal(view.input.selected, false);
        assert.equal(view.button.disabled, false);
    }
});

test('SIMKUG reports denied clipboard and selects the field for manual copy', async (t) => {
    let denied = true;
    mockNavigator(t, {
        clipboard: { writeText: async () => { if (denied) throw new Error('Permission denied'); } },
    });
    const view = fixture();
    await view.click();
    assert.ok(view.status.textContent.includes('Gagal menyalin otomatis'));
    assert.ok(view.classes.has('text-danger'));
    assert.equal(view.input.focused, true);
    assert.equal(view.input.selected, true);
    assert.equal(view.button.disabled, false);
    denied = false;
    await view.click();
    assert.ok(view.classes.has('text-success'));
    assert.equal(view.classes.has('text-danger'), false);
});

test('SIMKUG reports unavailable clipboard instead of claiming success', async (t) => {
    mockNavigator(t, {});
    const view = fixture();
    await view.click();
    assert.ok(view.status.textContent.includes('Ctrl+C atau Cmd+C'));
    assert.ok(view.classes.has('text-danger'));
    assert.equal(view.input.selected, true);
    assert.equal(view.button.disabled, false);
});

function headerFixture(value) {
    let click;
    const classes = new Set();
    const status = {
        textContent: '',
        classList: { add: (name) => classes.add(name), remove: (name) => classes.delete(name) },
    };
    const button = {
        disabled: false,
        closest: () => ({ querySelector: () => ({ textContent: value }) }),
        getAttribute: () => 'Salin Uraian Pengajuan',
        addEventListener: (event, handler) => { click = handler; },
    };
    mountSimkugHeaderCopy({
        querySelector: () => status,
        querySelectorAll: () => [button],
    });
    return { status, button, classes, click: () => click() };
}

test('SIMKUG header copies exact value without label and confirms success', async (t) => {
    const copied = [];
    mockNavigator(t, { clipboard: { writeText: async (value) => copied.push(value) } });
    for (const value of ['001234', 'Narasi "SIMKUG" & <uji>\nBaris kedua', '1.000,50']) {
        const view = headerFixture(value);
        await view.click();
        assert.equal(copied.at(-1), value);
        assert.equal(view.status.textContent, 'Uraian Pengajuan berhasil disalin.');
        assert.ok(view.classes.has('text-success'));
        assert.equal(view.button.disabled, false);
    }
});

test('SIMKUG header reports denied or unavailable clipboard and allows retry', async (t) => {
    let denied = true;
    const navigator = { clipboard: { writeText: async () => { if (denied) throw new Error('Permission denied'); } } };
    mockNavigator(t, navigator);
    const view = headerFixture('001234');
    await view.click();
    assert.match(view.status.textContent, /Gagal menyalin otomatis.*Ctrl\+C/);
    assert.ok(view.classes.has('text-danger'));
    assert.equal(view.button.disabled, false);
    denied = false;
    await view.click();
    assert.ok(view.classes.has('text-success'));
    assert.equal(view.classes.has('text-danger'), false);
    navigator.clipboard = undefined;
    await view.click();
    assert.match(view.status.textContent, /Gagal menyalin otomatis/);
});
