export function mountReadOnlySpreadsheetGrid(table) {
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

    const cellAt = ({ row, col }) => rows[row]?.querySelectorAll('[data-grid-cell]')[col] ?? null;
    const coordsFor = (cell) => {
        const row = cell.closest('tr[data-grid-row]');
        return { row: rows.indexOf(row), col: [...row.querySelectorAll('[data-grid-cell]')].indexOf(cell) };
    };

    function setSelection(start, end) {
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
    }

    function pointFromEvent(event) {
        const cell = event.target.closest('[data-grid-cell]');
        return cell && table.contains(cell) ? coordsFor(cell) : null;
    }

    table.addEventListener('pointerdown', (event) => {
        if (event.button !== 0 || !event.target.closest('[data-grid-cell]')) return;
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

    table.addEventListener('keydown', (event) => {
        const point = pointFromEvent(event);
        if (!point) return;
        const modifier = event.ctrlKey || event.metaKey;
        if (modifier && event.key.toLowerCase() === 'a') {
            event.preventDefault();
            anchor = { row: 0, col: 0 };
            setSelection(anchor, { row: rows.length - 1, col: columnCount - 1 });
            return;
        }
        if (!['ArrowUp', 'ArrowDown', 'ArrowLeft', 'ArrowRight'].includes(event.key)) return;
        event.preventDefault();
        let { row, col } = point;
        if (event.key === 'ArrowUp') row--;
        if (event.key === 'ArrowDown') row++;
        if (event.key === 'ArrowLeft') col--;
        if (event.key === 'ArrowRight') col++;
        const next = { row: Math.max(0, Math.min(rows.length - 1, row)), col: Math.max(0, Math.min(columnCount - 1, col)) };
        if (!anchor || !event.shiftKey) anchor = point;
        setSelection(anchor, next);
        cellAt(next)?.querySelector('.spreadsheet-cell')?.focus({ preventScroll: true });
    });

    table.addEventListener('copy', (event) => {
        if (!selection || !event.clipboardData) return;
        event.preventDefault();
        const values = rows.slice(selection.top, selection.bottom + 1).map((row) => {
            const cells = [...row.querySelectorAll('[data-grid-cell]')];
            return cells.slice(selection.left, selection.right + 1).map((cell) => cell.textContent.trim()).join('\t');
        });
        event.clipboardData.setData('text/plain', values.join('\r\n'));
    });
}
