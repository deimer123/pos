<div x-data @focus-new-row.window="$nextTick(() => { const inputs = document.querySelectorAll('[x-ref=codigoInput]'); if (inputs.length > 0) { inputs[inputs.length - 1]?.focus(); } })">
</div>