const MAX_ROWS = 500;
const MAX_SCALED = 1_000_000_000_000;
const format = (cents) => (cents / 100).toLocaleString('id-ID', {
    minimumFractionDigits: 0, maximumFractionDigits: 2,
});

export const schemas = {
    impresfund: [
        { key: 'no', label: 'No', optional: true },
        { key: 'uraian', label: 'Uraian' },
        { key: 'volume', label: 'Volume', numeric: true },
        { key: 'tarif', label: 'Tarif', numeric: true },
        { key: 'subtotal', label: 'Subtotal', numeric: true, optional: true },
    ],
    beban: [
        { key: 'no', label: 'No', optional: true },
        { key: 'nominal', label: 'Nominal', numeric: true },
        { key: 'pajak', label: 'Pot Pajak', numeric: true, defaultValue: '0' },
        { key: 'neto', label: 'Nominal Setelah Pajak', numeric: true },
        { key: 'bank', label: 'Nama Bank' },
        { key: 'nama', label: 'Nama Rekening' },
        { key: 'rekening', label: 'No Rekening' },
    ],
    honor: [
        { key: 'no', label: 'No', optional: true },
        { key: 'kewarganegaraan', label: 'WNI / WNA', choices: ['WNI', 'WNA'], list: 'honor-kewarganegaraan' },
        { key: 'nominal', label: 'Nominal Honor', numeric: true },
        { key: 'pajak', label: 'Potongan Pajak', numeric: true, defaultValue: '0' },
        { key: 'neto', label: 'Total Diterima', numeric: true },
        { key: 'penerima', label: 'Nama Penerima' },
        { key: 'nama', label: 'Nama Rekening' },
        { key: 'bank', label: 'Nama Bank' },
        { key: 'rekening', label: 'Nomor Rekening' },
        { key: 'identitas', label: 'No KTP / Paspor' },
        { key: 'npwp', label: 'No NPWP', optional: true },
    ],
};

export function parseAmount(value) {
    const text = String(value).trim();
    if (!/^(?:\d+|\d{1,3}(?:\.\d{3})+)(?:,\d{1,2})?$/.test(text)) {
        throw new Error('Gunakan angka positif format Indonesia, maksimal 2 desimal.');
    }
    const [whole, fraction = ''] = text.replaceAll('.', '').split(',');
    const cents = Number(whole) * 100 + Number(fraction.padEnd(2, '0'));
    if (!Number.isSafeInteger(cents) || cents > MAX_SCALED) {
        throw new Error('Angka terlalu besar (maksimal 10.000.000.000).');
    }
    return cents;
}

export function calculateRow(kind, values) {
    const columns = schemas[kind];
    if (!columns) throw new Error('Jenis form tidak dikenal.');
    if (columns.filter((column) => !column.computed && column.key !== 'no').every((column) => {
        const value = String(values[column.key] ?? '').trim();
        return !value || value === column.defaultValue;
    })) {
        return { empty: true, gross: 0, tax: 0, net: 0 };
    }
    const errors = {};
    const numbers = {};
    for (const column of columns.filter((column) => !column.computed)) {
        const value = String(values[column.key] ?? '').trim();
        if (!value) {
            if (!column.optional) errors[column.key] = `${column.label} wajib diisi.`;
        } else if (column.choices && !column.choices.includes(value.toUpperCase())) {
            errors[column.key] = `${column.label} harus diisi ${column.choices.join(' atau ')}.`;
        } else if (column.numeric) {
            try {
                numbers[column.key] = parseAmount(value);
            } catch (error) {
                errors[column.key] = error.message;
            }
        }
    }
    if (kind === 'impresfund' && numbers.volume === 0) errors.volume = 'Volume harus lebih dari nol.';
    if (kind !== 'impresfund' && numbers.pajak > numbers.nominal) errors.pajak = 'Potongan pajak tidak boleh melebihi nominal.';
    if (Object.keys(errors).length) return { errors };
    if (kind !== 'impresfund') return { gross: numbers.nominal, tax: numbers.pajak, net: numbers.neto };
    if (numbers.subtotal !== undefined) return { gross: numbers.subtotal, tax: 0, net: numbers.subtotal };
    // Both operands are scaled by 100; bound the product before rounding to cents.
    const product = numbers.volume * numbers.tarif;
    if (!Number.isSafeInteger(product)) return { errors: { tarif: 'Hasil perkalian terlalu besar.' } };
    const gross = Math.round(product / 100);
    return { gross, tax: 0, net: gross };
}

export function parseTsv(text) {
    const rows = [];
    let row = [];
    let cell = '';
    let quoted = false;
    let closed = false;
    for (let i = 0; i < text.length; i++) {
        const char = text[i];
        if (quoted) {
            if (char === '"' && text[i + 1] === '"') { cell += '"'; i++; }
            else if (char === '"') { quoted = false; closed = true; }
            else cell += char;
        } else if (char === '"' && cell === '' && !closed) {
            quoted = true;
        } else if (char === '\t' || char === '\n' || char === '\r') {
            row.push(cell);
            cell = '';
            closed = false;
            if (char !== '\t') {
                rows.push(row);
                row = [];
                if (char === '\r' && text[i + 1] === '\n') i++;
            }
        } else {
            if (closed) throw new Error('Format clipboard tidak valid setelah tanda kutip.');
            cell += char;
        }
    }
    if (quoted) throw new Error('Tanda kutip pada clipboard tidak lengkap.');
    if (cell !== '' || row.length || closed) { row.push(cell); rows.push(row); }
    return rows;
}

export function toTsv(rows) {
    return rows.map((row) => row.map((value) => {
        const text = String(value);
        return /["\t\r\n]/.test(text) ? `"${text.replaceAll('"', '""')}"` : text;
    }).join('\t')).join('\r\n');
}

export function mountGrid(form) {
    const kind = form.dataset.jenis;
    const columns = schemas[kind];
    const editable = columns.filter((column) => !column.computed);
    const body = form.querySelector('[data-grid-body]');
    const table = body.closest('table');
    const message = form.querySelector('[data-grid-message]');
    let active = null;
    let anchor = null;
    let selection = null;
    let editing = null;
    let dragging = false;
    form.addEventListener('submit', (event) => event.preventDefault());

    function notify(text, error = false) {
        message.textContent = text;
        message.className = `alert ${error ? 'alert-danger' : 'alert-info'}`;
    }

    function clearSelection() {
        selection = null;
        body.querySelectorAll('td').forEach((cell) => {
            cell.classList.remove('is-selected', 'is-active');
            cell.setAttribute('aria-selected', 'false');
        });
        table.querySelectorAll('th').forEach((cell) => {
            cell.classList.remove('is-selected');
            cell.setAttribute('aria-selected', 'false');
        });
    }

    function addRow() {
        const tr = document.createElement('tr');
        tr.setAttribute('role', 'row');
        const rowHeader = document.createElement('th');
        rowHeader.scope = 'row';
        rowHeader.className = 'spreadsheet-row-header';
        const rowButton = document.createElement('button');
        rowButton.type = 'button';
        rowButton.className = 'spreadsheet-selector';
        rowButton.dataset.gridSelectRow = '';
        rowHeader.append(rowButton);
        tr.append(rowHeader);
        for (const column of columns) {
            const td = document.createElement('td');
            td.setAttribute('role', 'gridcell');
            td.setAttribute('aria-readonly', String(Boolean(column.computed)));
            const input = document.createElement('input');
            input.type = 'text';
            input.className = `spreadsheet-cell${column.numeric || column.computed ? ' spreadsheet-numeric' : ''}${column.computed ? ' spreadsheet-computed' : ''}`;
            input.style.minWidth = column.key === 'no' ? '64px' : column.numeric || column.computed ? '145px' : '190px';
            input.dataset.key = column.key;
            input.value = column.key === 'no' ? String(body.rows.length + 1) : column.defaultValue ?? '';
            input.readOnly = true;
            input.tabIndex = -1;
            if (column.numeric) input.inputMode = 'decimal';
            td.append(input);
            tr.append(td);
        }
        body.append(tr);
        return tr;
    }

    function recalculate() {
        let gross = 0;
        const issues = [];
        [...body.rows].forEach((row, index) => {
            const rowButton = row.querySelector('[data-grid-select-row]');
            rowButton.textContent = index + 1;
            rowButton.setAttribute('aria-label', `Pilih baris ${index + 1}`);
            const values = {};
            row.querySelectorAll('input').forEach((input) => {
                values[input.dataset.key] = input.value;
                input.setAttribute('aria-label', `Baris ${index + 1}, ${columns.find((c) => c.key === input.dataset.key).label}`);
                input.classList.remove('is-invalid');
                input.removeAttribute('aria-invalid');
                input.removeAttribute('title');
            });
            const result = calculateRow(kind, values);
            for (const [key, error] of Object.entries(result.errors ?? {})) {
                const input = row.querySelector(`[data-key="${key}"]`);
                input.classList.add('is-invalid');
                input.setAttribute('aria-invalid', 'true');
                input.title = error;
                issues.push(`Baris ${index + 1}: ${error}`);
            }
            const subtotal = row.querySelector('[data-key="subtotal"]');
            if (subtotal) {
                subtotal.placeholder = !result.errors && !result.empty ? format(result.gross) : '';
                subtotal.setAttribute('aria-description', subtotal.value.trim()
                    ? 'Subtotal manual. Kosongkan untuk menghitung otomatis dari Volume x Tarif.'
                    : `Subtotal otomatis: ${subtotal.placeholder || 'belum tersedia'}. Isi untuk mengganti manual.`);
            }
            if (!result.errors) gross += result.gross;
        });
        if (!Number.isSafeInteger(gross)) issues.push('Total terlalu besar untuk dihitung secara akurat.');
        const total = issues.length ? 'Belum valid' : format(gross);
        form.querySelector('#nominal-diajukan').value = total;
        form.querySelector('[data-grid-errors]').textContent = issues.slice(0, 5).join(' ') + (issues.length > 5 ? ` (+${issues.length - 5} kesalahan lainnya)` : '');
    }

    function position(input) {
        return { row: input.closest('tr').sectionRowIndex, col: columns.findIndex((column) => column.key === input.dataset.key) };
    }

    function finishEditing() {
        if (!editing) return;
        editing.readOnly = true;
        editing.classList.remove('is-editing');
        editing.removeAttribute('list');
        editing = null;
    }

    function select(input, extend = false, focus = true) {
        finishEditing();
        const current = position(input);
        if (!extend || !anchor) anchor = current;
        clearSelection();
        if (active) active.tabIndex = -1;
        active = input;
        active.tabIndex = 0;
        selection = {
            top: Math.min(anchor.row, current.row), bottom: Math.max(anchor.row, current.row),
            left: Math.min(anchor.col, current.col), right: Math.max(anchor.col, current.col),
        };
        const setSelected = (cell, selected) => {
            cell.classList.toggle('is-selected', selected);
            cell.setAttribute('aria-selected', String(selected));
        };
        const fullWidth = selection.left === 0 && selection.right === columns.length - 1;
        const fullHeight = selection.top === 0 && selection.bottom === body.rows.length - 1;
        [...body.rows].forEach((row, r) => {
            const selectedRow = r >= selection.top && r <= selection.bottom;
            setSelected(row.firstElementChild, selectedRow && fullWidth);
            [...row.cells].slice(1).forEach((cell, c) => {
                setSelected(cell, selectedRow && c >= selection.left && c <= selection.right);
            });
        });
        table.querySelectorAll('[data-grid-select-column]').forEach((button) => {
            const col = Number(button.dataset.gridSelectColumn);
            setSelected(button.closest('th'), fullHeight && col >= selection.left && col <= selection.right);
        });
        const corner = table.querySelector('[data-grid-select-all]');
        if (corner) setSelected(corner.closest('th'), fullWidth && fullHeight);
        input.closest('td').classList.add('is-active');
        const address = (row, col) => `${String.fromCharCode(65 + col)}${row + 1}`;
        form.querySelector('[data-grid-address]').textContent =
            `${address(selection.top, selection.left)}:${address(selection.bottom, selection.right)}`;
        if (focus) {
            input.focus({ preventScroll: true });
            input.scrollIntoView({ block: 'nearest', inline: 'nearest', behavior: 'instant' });
        }
    }

    function selectRange(top, bottom, left, right) {
        anchor = { row: bottom, col: right };
        select(body.rows[top].querySelector(`[data-key="${columns[left].key}"]`), true);
        anchor = { row: top, col: left };
    }

    table.addEventListener('click', (event) => {
        const button = event.target.closest('button');
        if (!button || !table.contains(button)) return;
        if (button.hasAttribute('data-grid-select-all')) {
            selectRange(0, body.rows.length - 1, 0, columns.length - 1);
        } else if (button.hasAttribute('data-grid-select-column')) {
            const col = Number(button.dataset.gridSelectColumn);
            selectRange(0, body.rows.length - 1, col, col);
        } else if (button.hasAttribute('data-grid-select-row')) {
            const row = button.closest('tr').sectionRowIndex;
            selectRange(row, row, 0, columns.length - 1);
        }
    });

    function startEditing(input, replace = false) {
        const column = columns[position(input).col];
        if (column.computed) {
            notify('Kolom hasil hitung tidak dapat diedit.', true);
            return;
        }
        select(input);
        editing = input;
        input.readOnly = false;
        input.classList.add('is-editing');
        if (column.list) input.setAttribute('list', column.list);
        if (replace) input.value = '';
        else input.select();
    }

    body.addEventListener('focusin', (event) => {
        if (event.target.matches('input') && event.target !== active) select(event.target, false, false);
    });
    body.addEventListener('focusout', (event) => {
        if (event.target === editing) finishEditing();
    });
    body.addEventListener('pointerdown', (event) => {
        const input = event.target.closest('input');
        if (!input || event.button !== 0 || editing === input && !event.shiftKey) return;
        event.preventDefault();
        select(input, event.shiftKey);
        dragging = true;
        table.classList.add('is-selecting');
    });
    body.addEventListener('pointerover', (event) => {
        if (!dragging || !(event.buttons & 1)) return;
        const input = event.target.closest('input');
        if (input && input !== active) select(input, true);
    });
    body.ownerDocument.addEventListener('pointerup', () => {
        dragging = false;
        table.classList.remove('is-selecting');
    });
    body.addEventListener('dblclick', (event) => {
        if (event.target.matches('input')) startEditing(event.target);
    });
    body.addEventListener('input', recalculate);
    body.addEventListener('keydown', (event) => {
        const input = event.target;
        if (!input.matches('input') || event.isComposing) return;
        const modifier = event.ctrlKey || event.metaKey;
        if (event.key === 'Escape') {
            event.preventDefault();
            select(input);
            return;
        }
        if (editing === input && !['Tab', 'Enter'].includes(event.key)) return;
        if (modifier && event.key.toLowerCase() === 'a') {
            event.preventDefault();
            selectRange(0, body.rows.length - 1, 0, columns.length - 1);
            return;
        }
        if (modifier || event.altKey) return;
        const current = position(input);
        let { row, col } = current;
        if (event.key === 'Tab') {
            col += event.shiftKey ? -1 : 1;
            if (col >= columns.length) { col = 0; row++; }
            if (col < 0) { col = columns.length - 1; row--; }
            // Let Tab leave the grid at its boundaries.
            if (row < 0 || row >= body.rows.length) { finishEditing(); return; }
        } else if (event.key === 'Enter') row += event.shiftKey ? -1 : 1;
        else if (event.key === 'ArrowDown') row++;
        else if (event.key === 'ArrowUp') row--;
        else if (event.key === 'ArrowLeft') col--;
        else if (event.key === 'ArrowRight') col++;
        else if (['Delete', 'Backspace'].includes(event.key)) {
            event.preventDefault();
            const bounds = selection ?? { top: row, bottom: row, left: col, right: col };
            [...body.rows].slice(bounds.top, bounds.bottom + 1).forEach((tr) => {
                columns.forEach((column, c) => {
                    if (!column.computed && c >= bounds.left && c <= bounds.right) {
                        tr.querySelector(`[data-key="${column.key}"]`).value = '';
                    }
                });
            });
            recalculate();
            return;
        } else if (event.key === 'F2') {
            event.preventDefault();
            startEditing(input);
            return;
        } else if (event.key.length === 1) {
            startEditing(input, true);
            return;
        } else return;
        event.preventDefault();
        row = Math.max(0, Math.min(body.rows.length - 1, row));
        col = Math.max(0, Math.min(columns.length - 1, col));
        select(body.rows[row].querySelector(`[data-key="${columns[col].key}"]`),
            event.shiftKey && event.key.startsWith('Arrow'));
    });

    body.addEventListener('paste', (event) => {
        let input = event.target;
        if (!input.matches('input') || !event.clipboardData) return;
        if (!editing && selection) {
            input = body.rows[selection.top].querySelector(`[data-key="${columns[selection.left].key}"]`);
        }
        const text = event.clipboardData.getData('text/plain');
        if (columns[position(input).col].computed) { event.preventDefault(); notify('Kolom hasil hitung tidak dapat ditempel.', true); return; }
        // Keep native text editing only while the cell editor is open.
        if (editing === input && !/[\t\r\n]/.test(text)) return;
        event.preventDefault();
        let rows;
        try { rows = parseTsv(text); }
        catch (error) { notify(error.message, true); return; }
        const start = position(input).row;
        const offset = editable.findIndex((column) => column.key === input.dataset.key);
        if (start + rows.length > MAX_ROWS) { notify('Paste dibatalkan: maksimal 500 baris.', true); return; }
        if (rows.some((row) => row.length > editable.length - offset)) {
            notify('Paste dibatalkan: kolom melebihi kolom input yang tersedia. Jangan sertakan header atau hasil hitung.', true);
            return;
        }
        if (!rows.length) return;
        const startCol = position(input).col;
        finishEditing();
        while (body.rows.length < start + rows.length) addRow();
        rows.forEach((row, r) => row.forEach((value, c) => {
            body.rows[start + r].querySelector(`[data-key="${editable[offset + c].key}"]`).value = value;
        }));
        recalculate();
        anchor = { row: start, col: startCol };
        const width = Math.max(...rows.map((row) => row.length));
        select(body.rows[start + rows.length - 1].querySelector(`[data-key="${editable[offset + width - 1].key}"]`), true);
        notify(`${rows.length} baris ditempel. Periksa penanda merah bila ada data tidak valid.`);
    });

    function clipboardRows(all) {
        const value = (row, column) => {
            const input = row.querySelector(`[data-key="${column.key}"]`);
            return column.key === 'subtotal' && !input.value.trim() ? input.placeholder : input.value;
        };
        if (all) {
            return [...body.rows].map((row) =>
                editable.map((column) => value(row, column)));
        }
        const bounds = selection;
        const selected = columns.filter((column, c) => !column.computed && c >= bounds.left && c <= bounds.right);
        if (!selected.length) throw new Error('Pilih setidaknya satu kolom input untuk disalin.');
        return [...body.rows].slice(bounds.top, bounds.bottom + 1).map((row) =>
            selected.map((column) => value(row, column)));
    }
    body.addEventListener('copy', (event) => {
        if (editing || !selection || !event.clipboardData) return;
        event.preventDefault();
        try {
            event.clipboardData.setData('text/plain', toTsv(clipboardRows(false)));
            notify('Rentang kolom input disalin, termasuk nilai Subtotal yang berlaku.');
        } catch (error) { notify(error.message, true); }
    });
    form.querySelector('[data-grid-copy]').addEventListener('click', async () => {
        try {
            if (!navigator.clipboard?.writeText) throw new Error('Clipboard tidak tersedia. Gunakan Shift+klik untuk memilih rentang lalu Ctrl+C.');
            await navigator.clipboard.writeText(toTsv(clipboardRows(true)));
            notify('Semua kolom input disalin tanpa header. Siap ditempel ke Excel dan kembali ke form.');
        } catch (error) { notify(`Tidak dapat menyalin: ${error.message}`, true); }
    });
    form.querySelector('[data-grid-add]').addEventListener('click', () => {
        if (body.rows.length >= MAX_ROWS) { notify('Maksimal 500 baris.', true); return; }
        finishEditing();
        const row = addRow();
        recalculate();
        select(row.querySelector('input'));
    });
    form.querySelector('[data-grid-delete]').addEventListener('click', () => {
        if (!active || !body.contains(active)) { notify('Pilih sel pada baris yang ingin dihapus.', true); return; }
        const top = selection ? selection.top : position(active).row;
        const bottom = selection ? selection.bottom : top;
        finishEditing();
        for (let i = bottom; i >= top; i--) body.rows[i].remove();
        active = null;
        anchor = null;
        clearSelection();
        if (!body.rows.length) addRow();
        recalculate();
        select(body.rows[Math.min(top, body.rows.length - 1)].querySelector('input'));
        notify(`${bottom - top + 1} baris dihapus.`);
    });
    for (let i = 0; i < 5; i++) addRow();
    recalculate();
    select(body.rows[0].querySelector('input'), false, false);
}
