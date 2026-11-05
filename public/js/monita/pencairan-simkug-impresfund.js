import { mountEditableSpreadsheetGrid, mountReadOnlySpreadsheetGrid } from './pencairan-detail-grid.js';

export function mountSimkugImpresfund(panel) {
    const form = panel.querySelector('[data-simkug-form]');
    const table = form.querySelector('table');
    const gridStatus = form.querySelector('[data-simkug-grid-status]');
    if (form.dataset.editable === 'true') {
        mountEditableSpreadsheetGrid(table, (message) => { gridStatus.textContent = message; });
    } else {
        mountReadOnlySpreadsheetGrid(table);
    }
    let dirty = false;
    const changed = () => { dirty = true; };
    form.addEventListener('input', changed);
    form.addEventListener('change', changed);
    form.addEventListener('submit', () => {
        dirty = false;
        form.querySelector('[type="submit"]').disabled = true;
    });
    window.addEventListener('beforeunload', (event) => {
        if (dirty) { event.preventDefault(); event.returnValue = ''; }
    });
    const tab = panel.closest('[data-simkug-panel]');
    const tabButton = document.querySelector(`[data-bs-target="#${tab.id}"]`);
    tabButton.addEventListener('shown.bs.tab', () => {
        const url = new URL(window.location.href);
        url.searchParams.set('tab', 'simkug');
        history.replaceState(null, '', url.href);
    });
}
