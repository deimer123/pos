/* POS mobile menu extras disabled: actions now live in Blade to avoid duplicated clicks. */
/* POS edit products save */
window.posEditProductsSave = function (button) {
    var panel = button.closest('.pos-edit-modal-panel');
    if (!panel) return;

    var cambios = {};
    panel.querySelectorAll('.pos-edit-product-item').forEach(function (row) {
        var id = row.dataset.id;
        if (!id || cambios[id]) return;
        var price = row.querySelector('[data-field="nuevo_precio"]');
        var discount = row.querySelector('[data-field="descuento"]');
        cambios[id] = {
            nuevo_precio: price ? price.value : 0,
            descuento: discount ? discount.value : 0
        };
    });

    var componentRoot = button.closest('[wire\\:id]') || document.querySelector('[wire\\:id]');
    var componentId = componentRoot ? componentRoot.getAttribute('wire:id') : null;
    if (window.Livewire && componentId) {
        Livewire.find(componentId).call('aplicarCambiosModal', cambios);
    }
};
/* END POS edit products save */