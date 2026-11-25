import test from 'node:test';
import assert from 'node:assert/strict';
import { mountSimkugCopyAll } from '../../public/js/monita/pencairan-detail-grid.js';

function mockNavigator(t, clipboard) {
    const original = Object.getOwnPropertyDescriptor(globalThis, 'navigator');
    Object.defineProperty(globalThis, 'navigator', { configurable: true, value: { clipboard } });
    t.after(() => {
        if (original) Object.defineProperty(globalThis, 'navigator', original);
        else delete globalThis.navigator;
    });
}

function fixture(rows) {
    let click;
    const classes = new Set();
    const status = {
        textContent: '',
        classList: { add: (value) => classes.add(value), remove: (value) => classes.delete(value) },
    };
    const button = {
        disabled: false,
        addEventListener: (event, handler) => { click = handler; },
    };
    const elements = {
        '[data-simkug-copy-all]': button,
        '[data-simkug-copy-all-status]': status,
        'table[role="grid"]': { querySelectorAll: () => rows },
    };
    mountSimkugCopyAll({ querySelector: (selector) => elements[selector] });
    return { button, status, classes, click: () => click() };
}

function row(cells) {
    return { querySelectorAll: () => cells };
}

function control(value) {
    const input = { value };
    return { input, querySelector: () => input, textContent: 'ignored' };
}

function text(value) {
    return { querySelector: () => null, textContent: value };
}

test('copies all rows without headers, using current controls and spreadsheet-safe TSV', async (t) => {
    const copied = [];
    mockNavigator(t, { writeText: async (value) => copied.push(value) });
    const dc = control('D');
    const view = fixture([
        row([control('001234'), dc, control('Baris "satu"\nbaris dua'), control('')]),
        row([text('  002  '), text('C'), text('A\tB'), text('0,00')]),
    ]);
    dc.input.value = 'C';
    await view.click();
    assert.equal(copied[0], '001234\tC\t"Baris ""satu""\nbaris dua"\t\r\n002\tC\t"A\tB"\t0,00');
    assert.equal(view.status.textContent, 'Seluruh data tabel disalin tanpa header.');
    assert.ok(view.classes.has('text-success'));
    assert.equal(view.button.disabled, false);
});

test('reports unavailable or denied clipboard and allows retry', async (t) => {
    let denied = true;
    mockNavigator(t, { writeText: async () => { if (denied) throw new Error('Permission denied'); } });
    const view = fixture([row([text('Data')])]);
    await view.click();
    assert.match(view.status.textContent, /Gagal menyalin otomatis.*Ctrl\+C/);
    assert.ok(view.classes.has('text-danger'));
    assert.equal(view.button.disabled, false);
    denied = false;
    await view.click();
    assert.ok(view.classes.has('text-success'));
    assert.equal(view.classes.has('text-danger'), false);

    globalThis.navigator.clipboard = undefined;
    await view.click();
    assert.match(view.status.textContent, /Gagal menyalin otomatis/);
});

test('disables copying when there are no data rows', () => {
    const view = fixture([]);
    assert.equal(view.button.disabled, true);
});
