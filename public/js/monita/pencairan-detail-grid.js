import { parseTsv, toTsv } from './pencairan-grid.js';

export function mountReadOnlySpreadsheetGrid(table) {
    mountSpreadsheetGrid(table, false);
}

export function mountEditableSpreadsheetGrid(table, notify) {
    mountSpreadsheetGrid(table, true, notify);
}

function mountSpreadsheetGrid(table, editable, notify) {
    if (!table) return;

    const body = table.tBodies[0];
    const rows = [...body.querySelectorAll('tr[data-grid-row]')];
    const columnCount = rows[0]?.querySelectorAll('[data-grid-cell]').length ?? 0;
    if (!rows.length || !columnCount) {
        table.querySelectorAll('[data-grid-select-all], [data-grid-select-column], [data-grid-select-row]')
            .forEach((button) => { button.disabled = true; });
        return;
    }

    let anchor = null;
    let active = null;
    let dragging = false;
    let selection = null;
    let editing = null;
    const controlFor = (cell) => cell.querySelector('input, textarea, select');
    const nativeEditing = (event) => event.target === editing || event.target.tagName === 'SELECT';

    function finishEditing() {
        if (!editing) return;
        editing.readOnly = true;
        editing.classList.remove('is-editing');
        editing = null;
    }

    function startEditing(control, replace = false) {
        if (!editable || !control || control.disabled || control.tagName === 'SELECT') return;
        finishEditing();
        const point = coordsFor(control.closest('[data-grid-cell]'));
        anchor = point;
        setSelection(point, point);
        editing = control;
        control.readOnly = false;
        control.classList.add('is-editing');
        control.focus();
        if (replace) control.value = '';
    }

    const changed = (control) => control.dispatchEvent(new Event('input', { bubbles: true }));

    const cellAt = ({ row, col }) => rows[row]?.querySelectorAll('[data-grid-cell]')[col] ?? null;
    const coordsFor = (cell) => {
        const row = cell.closest('tr[data-grid-row]');
        return { row: rows.indexOf(row), col: [...row.querySelectorAll('[data-grid-cell]')].indexOf(cell) };
    };

    function setSelection(start, end) {
        finishEditing();
        const top = Math.min(start.row, end.row);
        const bottom = Math.max(start.row, end.row);
        const left = Math.min(start.col, end.col);
        const right = Math.max(start.col, end.col);
        selection = { top, bottom, left, right };
        active = end;

        table.querySelectorAll('[data-grid-cell]').forEach((cell) => {
            const point = coordsFor(cell);
            const selected = point.row >= top && point.row <= bottom && point.col >= left && point.col <= right;
            cell.classList.toggle('is-selected', selected);
            cell.classList.toggle('is-active', point.row === end.row && point.col === end.col);
            cell.setAttribute('aria-selected', String(selected));
        });
        table.querySelectorAll('[data-grid-select-column]').forEach((button) => {
            const col = Number(button.dataset.gridSelectColumn);
            button.closest('th').classList.toggle('is-selected', col >= left && col <= right && top === 0 && bottom === rows.length - 1);
        });
        rows.forEach((row, index) => {
            row.querySelector('th[scope="row"]').classList.toggle('is-selected', index >= top && index <= bottom && left === 0 && right === columnCount - 1);
        });
        const selectAll = table.querySelector('[data-grid-select-all]');
        selectAll?.closest('th').classList.toggle('is-selected', top === 0 && bottom === rows.length - 1 && left === 0 && right === columnCount - 1);
        cellAt(end)?.querySelector('.spreadsheet-cell')?.focus({ preventScroll: true });
    }

    function pointFromEvent(event) {
        const cell = event.target.closest('[data-grid-cell]');
        return cell && table.contains(cell) ? coordsFor(cell) : null;
    }

    table.addEventListener('pointerdown', (event) => {
        if (event.button !== 0 || !event.target.closest('[data-grid-cell]')) return;
        if (nativeEditing(event)) return;
        const point = pointFromEvent(event);
        if (!point) return;
        if (event.shiftKey && anchor) {
            setSelection(anchor, point);
        } else {
            anchor = point;
            setSelection(point, point);
        }
        dragging = true;
        table.classList.add('is-selecting');
    });

    table.addEventListener('pointerover', (event) => {
        if (!dragging || !(event.buttons & 1)) return;
        const point = pointFromEvent(event);
        if (point) setSelection(anchor, point);
    });

    table.ownerDocument.addEventListener('pointerup', () => {
        dragging = false;
        table.classList.remove('is-selecting');
    });

    table.addEventListener('click', (event) => {
        const button = event.target.closest('button');
        if (!button || !table.contains(button)) return;
        if (button.hasAttribute('data-grid-select-all')) {
            anchor = { row: 0, col: 0 };
            setSelection(anchor, { row: rows.length - 1, col: columnCount - 1 });
        } else if (button.hasAttribute('data-grid-select-column')) {
            const col = Number(button.dataset.gridSelectColumn);
            anchor = { row: 0, col };
            setSelection(anchor, { row: rows.length - 1, col });
        } else if (button.hasAttribute('data-grid-select-row')) {
            const row = rows.indexOf(button.closest('tr[data-grid-row]'));
            anchor = { row, col: 0 };
            setSelection(anchor, { row, col: columnCount - 1 });
        }
    });

    table.addEventListener('focusin', (event) => {
        if (!event.target.matches('input, textarea, select')) return;
        const point = pointFromEvent(event);
        if (point && (!active || active.row !== point.row || active.col !== point.col)) {
            anchor = point;
            setSelection(point, point);
        }
    });
    table.addEventListener('dblclick', (event) => {
        const cell = event.target.closest('[data-grid-cell]');
        if (cell) startEditing(controlFor(cell));
    });
    table.addEventListener('focusout', (event) => {
        if (event.target === editing) finishEditing();
    });

    table.addEventListener('keydown', (event) => {
        const point = pointFromEvent(event);
        if (!point) return;
        if (event.key === 'Escape') {
            finishEditing();
            event.preventDefault();
            return;
        }
        if (nativeEditing(event) && !['Tab', 'Enter'].includes(event.key)) return;
        const modifier = event.ctrlKey || event.metaKey;
        if (modifier && event.key.toLowerCase() === 'a') {
            event.preventDefault();
            anchor = { row: 0, col: 0 };
            setSelection(anchor, { row: rows.length - 1, col: columnCount - 1 });
            return;
        }
        if (editable && event.key === 'F2') {
            event.preventDefault();
            startEditing(controlFor(cellAt(point)));
            return;
        }
        if (editable && ['Delete', 'Backspace'].includes(event.key)) {
            event.preventDefault();
            if (!selection) setSelection(point, point);
            for (let r = selection.top; r <= selection.bottom; r++) {
                for (let c = selection.left; c <= selection.right; c++) {
                    const control = controlFor(cellAt({ row: r, col: c }));
                    control.value = '';
                    changed(control);
                }
            }
            return;
        }
        if (editable && !modifier && !event.altKey && event.key.length === 1) {
            const control = controlFor(cellAt(point));
            event.preventDefault();
            startEditing(control, true);
            control.value = event.key;
            changed(control);
            return;
        }
        if (!['ArrowUp', 'ArrowDown', 'ArrowLeft', 'ArrowRight', 'Tab', 'Enter'].includes(event.key)) return;
        if (event.key === 'Enter' && event.altKey && editing?.tagName === 'TEXTAREA') return;
        if (event.key === 'Tab' && ((point.col === 0 && event.shiftKey) || (point.col === columnCount - 1 && !event.shiftKey))) {
            finishEditing();
            return;
        }
        event.preventDefault();
        let { row, col } = point;
        if (event.key === 'ArrowUp') row--;
        if (event.key === 'ArrowDown') row++;
        if (event.key === 'ArrowLeft') col--;
        if (event.key === 'ArrowRight') col++;
        if (event.key === 'Tab') col += event.shiftKey ? -1 : 1;
        if (event.key === 'Enter') row += event.shiftKey ? -1 : 1;
        const next = { row: Math.max(0, Math.min(rows.length - 1, row)), col: Math.max(0, Math.min(columnCount - 1, col)) };
        if (event.shiftKey && !['Tab', 'Enter'].includes(event.key)) {
            anchor ??= point;
        } else {
            anchor = next;
        }
        setSelection(anchor, next);
        cellAt(next)?.querySelector('.spreadsheet-cell')?.focus({ preventScroll: true });
    });

    table.addEventListener('copy', (event) => {
        if (event.target === editing) return;
        if (!selection || !event.clipboardData) return;
        event.preventDefault();
        const values = rows.slice(selection.top, selection.bottom + 1).map((row) => {
            const cells = [...row.querySelectorAll('[data-grid-cell]')];
            return cells.slice(selection.left, selection.right + 1).map((cell) => controlFor(cell)?.value ?? cell.textContent.trim());
        });
        event.clipboardData.setData('text/plain', toTsv(values));
    });

    table.addEventListener('paste', (event) => {
        if (!editable || !event.clipboardData || !pointFromEvent(event)) return;
        if (event.target === editing) return;
        event.preventDefault();
        try {
            const values = parseTsv(event.clipboardData.getData('text/plain'));
            const start = selection ? { row: selection.top, col: selection.left } : pointFromEvent(event);
            if (!values.length || start.row + values.length > rows.length
                || values.some((row) => start.col + row.length > columnCount)) {
                throw new Error('Paste harus muat dalam satu baris dan sembilan kolom SIMKUG.');
            }
            const updates = values.flatMap((row, r) => row.map((value, c) => {
                const control = controlFor(cellAt({ row: start.row + r, col: start.col + c }));
                if (control.tagName === 'SELECT' && ![...control.options].some((option) => option.value === value)) {
                    throw new Error('DC harus D atau C.');
                }
                if (control.maxLength > 0 && value.length > control.maxLength) {
                    throw new Error(`${control.getAttribute('aria-label')} melebihi batas panjang.`);
                }
                return { control, value };
            }));
            updates.forEach(({ control, value }) => { control.value = value; changed(control); });
            notify('');
        } catch (error) {
            notify(error.message);
        }
    });
}
