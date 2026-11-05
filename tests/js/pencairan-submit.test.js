import test from 'node:test';
import assert from 'node:assert/strict';
import { mountSubmission } from '../../public/js/monita/pencairan-submit.js';

test('save and submit retain the originating menu in edit and detail URLs', async (t) => {
    for (const menu of ['pengajuan', 'verifikasi', 'transaksi']) {
        await t.test(menu, async (t) => {
            const events = {};
            const savedUrls = [];
            const redirects = [];
            const message = {};
            const button = { addEventListener: (event, handler) => { events.save = handler; } };
            const fields = {
                '#pilih-drk': { disabled: false, value: 'drk' },
                '#uraian-pengajuan': { value: 'Uraian' },
                '[name="_token"]': { value: 'csrf' },
                '[data-grid-body]': { addEventListener() {} },
                '[data-save-draft]': button,
            };
            const form = {
                dataset: { jenis: 'honor', menu },
                querySelector: (selector) => fields[selector],
                querySelectorAll: (selector) => selector === 'button' ? [button] : [],
                addEventListener: (event, handler) => { events[event] = handler; },
            };
            t.mock.method(globalThis, 'fetch', async () => ({
                ok: true,
                json: async () => ({ uuid: 'submission', lock_version: 2, redirect_url: '/pencairan/pengajuan/submission' }),
            }));
            for (const [name, value] of Object.entries({
                document: { getElementById: () => message },
                window: {
                    addEventListener() {},
                    confirm: () => true,
                    location: { href: 'http://localhost/pencairan/pengajuan/submission/edit', assign: (url) => redirects.push(url) },
                },
                history: { replaceState: (state, title, url) => savedUrls.push(url) },
            })) {
                const descriptor = Object.getOwnPropertyDescriptor(globalThis, name);
                Object.defineProperty(globalThis, name, { configurable: true, value });
                t.after(() => {
                    if (descriptor) Object.defineProperty(globalThis, name, descriptor);
                    else delete globalThis[name];
                });
            }
            mountSubmission(form, { rows: () => [] }, { uuid: 'submission', lock_version: 1 }, '/pencairan/pengajuan');
            await events.save();
            assert.equal(savedUrls.at(-1), `http://localhost/pencairan/pengajuan/submission/edit?menu=${menu}`);
            await events.submit({ preventDefault() {} });
            await new Promise(setImmediate);
            assert.equal(redirects.at(-1), `http://localhost/pencairan/pengajuan/submission?menu=${menu}`);
            assert.equal(message.className, 'alert alert-info');
        });
    }
});
