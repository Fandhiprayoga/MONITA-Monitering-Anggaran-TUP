const MAX_ROWS = 500;
const MAX_SCALED = 1_000_000_000_000;
const format = (cents) => (cents / 100).toLocaleString('id-ID', {
    minimumFractionDigits: 0, maximumFractionDigits: 2,
});

export const schemas = {
    impresfund: [
        { key: 'uraian', label: 'Uraian' },
        { key: 'volume', label: 'Volume', numeric: true },
        { key: 'tarif', label: 'Tarif', numeric: true },
        { key: 'subtotal', label: 'Subtotal', computed: true },
    ],
    beban: [
        { key: 'nominal', label: 'Nominal', numeric: true },
        { key: 'pajak', label: 'Pot Pajak', numeric: true, defaultValue: '0' },
        { key: 'neto', label: 'Nominal Setelah Pajak', numeric: true },
        { key: 'bank', label: 'Nama Bank' },
        { key: 'nama', label: 'Nama Rekening' },
        { key: 'rekening', label: 'No Rekening' },
    ],
    honor: [
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
    if (columns.filter((column) => !column.computed).every((column) => {
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
    const message = form.querySelector('[data-grid-message]');
    let active = null;
    let anchor = null;
    let selection = null;
    form.addEventListener('submit', (event) => event.preventDefault());

    function notify(text, error = false) {
        message.textContent = text;
        message.className = `alert ${error ? 'alert-danger' : 'alert-info'}`;
    }

    function clearSelection() {
        selection = null;
        body.querySelectorAll('td').forEach((cell) => cell.classList.remove('table-primary'));
    }

    function addRow() {
        const tr = document.createElement('tr');
        const number = document.createElement('th');
        number.scope = 'row';
        tr.append(number);
        for (const column of columns) {
            const td = document.createElement('td');
            const input = document.createElement('input');
            input.type = 'text';
            input.className = `form-control form-control-sm ${column.computed ? 'bg-light' : ''}`;
            input.style.minWidth = column.numeric || column.computed ? '130px' : '170px';
            input.dataset.key = column.key;
            input.value = column.defaultValue ?? '';
            input.readOnly = Boolean(column.computed);
            if (column.list) input.setAttribute('list', column.list);
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
            row.firstElementChild.textContent = index + 1;
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
            const computed = row.querySelector('[readonly]');
            if (computed) computed.value = result.errors ? 'Tidak valid' : format(kind === 'impresfund' ? result.gross : result.net);
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

    body.addEventListener('focusin', (event) => {
        if (event.target.matches('input')) active = event.target;
    });
    body.addEventListener('click', (event) => {
        if (!event.target.matches('input')) return;
        const current = position(event.target);
        clearSelection();
        if (event.shiftKey && anchor) {
            selection = {
                top: Math.min(anchor.row, current.row), bottom: Math.max(anchor.row, current.row),
                left: Math.min(anchor.col, current.col), right: Math.max(anchor.col, current.col),
            };
            [...body.rows].forEach((row, r) => [...row.cells].slice(1).forEach((cell, c) => {
                cell.classList.toggle('table-primary', r >= selection.top && r <= selection.bottom && c >= selection.left && c <= selection.right);
            }));
        } else anchor = current;
    });
    body.addEventListener('input', () => { clearSelection(); recalculate(); });
    body.addEventListener('keydown', (event) => {
        if (!event.shiftKey && !event.ctrlKey && !event.metaKey) clearSelection();
    });

    body.addEventListener('paste', (event) => {
        const input = event.target;
        if (!input.matches('input') || !event.clipboardData) return;
        const text = event.clipboardData.getData('text/plain');
        if (input.readOnly) { event.preventDefault(); notify('Kolom hasil hitung tidak dapat ditempel.', true); return; }
        // A single text cell keeps native editing; rectangular clipboard data fills input columns only.
        if (!/[\t\r\n]/.test(text)) return;
        event.preventDefault();
        let rows;
        try { rows = parseTsv(text); }
        catch (error) { notify(error.message, true); return; }
        const start = position(input).row;
        const offset = editable.findIndex((column) => column.key === input.dataset.key);
        if (start + rows.length > MAX_ROWS) { notify('Paste dibatalkan: maksimal 500 baris.', true); return; }
        if (rows.some((row) => row.length > editable.length - offset)) {
            notify('Paste dibatalkan: kolom melebihi kolom input yang tersedia. Jangan sertakan No atau hasil hitung.', true);
            return;
        }
        clearSelection();
        while (body.rows.length < start + rows.length) addRow();
        rows.forEach((row, r) => row.forEach((value, c) => {
            body.rows[start + r].querySelector(`[data-key="${editable[offset + c].key}"]`).value = value;
        }));
        recalculate();
        notify(`${rows.length} baris ditempel. Periksa penanda merah bila ada data tidak valid.`);
    });

    function clipboardRows(all) {
        if (all) {
            return [...body.rows].map((row) => [
                row.firstElementChild.textContent,
                ...columns.map((column) => row.querySelector(`[data-key="${column.key}"]`).value),
            ]);
        }
        const bounds = selection;
        const selected = columns.filter((column, c) => !column.computed && c >= bounds.left && c <= bounds.right);
        if (!selected.length) throw new Error('Pilih setidaknya satu kolom input untuk disalin.');
        return [...body.rows].slice(bounds.top, bounds.bottom + 1).map((row) =>
            selected.map((column) => row.querySelector(`[data-key="${column.key}"]`).value));
    }
    body.addEventListener('copy', (event) => {
        if (!selection || !event.clipboardData) return;
        event.preventDefault();
        try {
            event.clipboardData.setData('text/plain', toTsv(clipboardRows(false)));
            notify('Rentang kolom input disalin tanpa No dan hasil hitung.');
        } catch (error) { notify(error.message, true); }
    });
    form.querySelector('[data-grid-copy]').addEventListener('click', async () => {
        try {
            if (!navigator.clipboard?.writeText) throw new Error('Clipboard tidak tersedia. Gunakan Shift+klik untuk memilih rentang lalu Ctrl+C.');
            await navigator.clipboard.writeText(toTsv(clipboardRows(true)));
            notify('Semua data kolom disalin, termasuk No dan hasil hitung, tanpa header tabel. Siap ditempel ke Excel.');
        } catch (error) { notify(`Tidak dapat menyalin: ${error.message}`, true); }
    });
    form.querySelector('[data-grid-add]').addEventListener('click', () => {
        if (body.rows.length >= MAX_ROWS) { notify('Maksimal 500 baris.', true); return; }
        clearSelection();
        const row = addRow();
        recalculate();
        row.querySelector('input').focus();
    });
    form.querySelector('[data-grid-delete]').addEventListener('click', () => {
        if (!active || !body.contains(active)) { notify('Pilih sel pada baris yang ingin dihapus.', true); return; }
        const top = selection ? selection.top : position(active).row;
        const bottom = selection ? selection.bottom : top;
        for (let i = bottom; i >= top; i--) body.rows[i].remove();
        active = null;
        anchor = null;
        clearSelection();
        if (!body.rows.length) addRow();
        recalculate();
        notify(`${bottom - top + 1} baris dihapus.`);
    });
    for (let i = 0; i < 5; i++) addRow();
    recalculate();
}
