import test from 'node:test';
import assert from 'node:assert/strict';
import { mountSimkugImprestfund } from '../../public/js/monita/pencairan-simkug-imprestfund.js';

test('SIMKUG tracks unsaved edits and retains navigation without a separate copy section', (t) => {
    const events = {};
    const button = {};
    const form = {
        dataset: { editable: 'false' },
        querySelector: (selector) => selector === '[type="submit"]' ? button : null,
        addEventListener: (event, handler) => { events[event] = handler; },
    };
    const panel = {
        querySelector: (selector) => selector === '[data-simkug-form]' ? form : null,
        closest: () => ({ id: 'simkug-panel' }),
    };
    const tabEvents = {};
    const windowEvents = {};
    const urls = [];
    for (const [key, value] of Object.entries({
        document: { querySelector: () => ({ addEventListener: (event, handler) => { tabEvents[event] = handler; } }) },
        window: {
            location: { href: 'http://localhost/pencairan/pengajuan/uuid?menu=transaksi' },
            addEventListener: (event, handler) => { windowEvents[event] = handler; },
        },
        history: { replaceState: (state, title, url) => urls.push(url) },
    })) {
        const descriptor = Object.getOwnPropertyDescriptor(globalThis, key);
        Object.defineProperty(globalThis, key, { configurable: true, value });
        t.after(() => {
            if (descriptor) Object.defineProperty(globalThis, key, descriptor);
            else delete globalThis[key];
        });
    }
    mountSimkugImprestfund(panel);
    events.change();
    let prevented = false;
    windowEvents.beforeunload({ preventDefault() { prevented = true; } });
    assert.equal(prevented, true);
    tabEvents['shown.bs.tab']();
    assert.equal(urls[0], 'http://localhost/pencairan/pengajuan/uuid?menu=transaksi&tab=simkug');
    events.submit();
    assert.equal(button.disabled, true);
    prevented = false;
    windowEvents.beforeunload({ preventDefault() { prevented = true; } });
    assert.equal(prevented, false);
});
