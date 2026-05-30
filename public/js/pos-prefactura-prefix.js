(() => {
    const formatPrefacturaNumber = (value) => {
        const id = String(value || '').trim();

        if (!/^\d+$/.test(id)) {
            return id;
        }

        return `PRE-${id.padStart(5, '0')}`;
    };

    const isPrefacturasTable = (table) => {
        const headers = Array.from(table.querySelectorAll('thead th'))
            .map((th) => th.textContent.trim().toLowerCase());

        return headers[0] === '#' && headers[1] === 'fecha' && headers[2] === 'hora';
    };

    const setCellStyle = (cell, styles) => {
        Object.entries(styles).forEach(([key, value]) => {
            cell.style[key] = value;
        });
    };

    const compactTable = (table) => {
        const headers = table.querySelectorAll('thead th');

        if (headers.length >= 5) {
            headers[1].textContent = 'Fecha / Hora';
            headers[2].remove();
        }

        table.style.tableLayout = 'fixed';

        table.querySelectorAll('tbody tr').forEach((row) => {
            const cells = row.querySelectorAll('td');

            if (cells.length < 5) {
                return;
            }

            const numberCell = cells[0];
            const dateCell = cells[1];
            const hourCell = cells[2];
            const clientCell = cells[3];
            const totalCell = cells[4];

            const raw = numberCell.dataset.prefacturaId || numberCell.textContent.trim();
            numberCell.dataset.prefacturaId = raw;
            numberCell.textContent = formatPrefacturaNumber(raw);

            dateCell.textContent = `${dateCell.textContent.trim()} ${hourCell.textContent.trim()}`;
            hourCell.remove();

            setCellStyle(numberCell, {
                width: '92px',
                maxWidth: '92px',
                whiteSpace: 'nowrap',
                fontSize: '12px',
            });

            setCellStyle(dateCell, {
                width: '128px',
                maxWidth: '128px',
                whiteSpace: 'nowrap',
                fontSize: '12px',
            });

            setCellStyle(clientCell, {
                whiteSpace: 'nowrap',
                overflow: 'hidden',
                textOverflow: 'ellipsis',
                fontSize: '12px',
            });

            setCellStyle(totalCell, {
                width: '76px',
                maxWidth: '76px',
                whiteSpace: 'nowrap',
                textAlign: 'right',
                fontSize: '12px',
            });
        });
    };

    const formatPrefacturas = () => {
        document.querySelectorAll('.pos-prefacturas-list table').forEach((table) => {
            if (!isPrefacturasTable(table)) {
                return;
            }

            compactTable(table);
        });
    };

    document.addEventListener('DOMContentLoaded', formatPrefacturas);
    document.addEventListener('livewire:init', () => {
        Livewire.hook('morph.updated', () => queueMicrotask(formatPrefacturas));
    });

    new MutationObserver(() => formatPrefacturas())
        .observe(document.documentElement, { childList: true, subtree: true });
})();
