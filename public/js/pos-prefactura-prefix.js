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

    const formatPrefacturas = () => {
        document.querySelectorAll('.pos-prefacturas-list table').forEach((table) => {
            if (!isPrefacturasTable(table)) {
                return;
            }

            table.querySelectorAll('tbody tr td:first-child').forEach((cell) => {
                const raw = cell.dataset.prefacturaId || cell.textContent.trim();
                const formatted = formatPrefacturaNumber(raw);

                if (formatted && cell.textContent.trim() !== formatted) {
                    cell.dataset.prefacturaId = raw;
                    cell.textContent = formatted;
                }
            });
        });
    };

    document.addEventListener('DOMContentLoaded', formatPrefacturas);
    document.addEventListener('livewire:init', () => {
        Livewire.hook('morph.updated', () => queueMicrotask(formatPrefacturas));
    });

    new MutationObserver(() => formatPrefacturas())
        .observe(document.documentElement, { childList: true, subtree: true });
})();
