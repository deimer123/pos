@php
    if (isset($cacheKey) && $cacheKey instanceof \Closure) {
        $cacheKey = $cacheKey();
    }

    if (! isset($cacheKey) || ! is_string($cacheKey) || $cacheKey === '') {
        $cacheKey = 'ajuste_inventario_cache_u_' . (auth()->id() ?? 'guest');
    }
@endphp

<script>
document.addEventListener('alpine:init', () => {
  Alpine.data('persistInventarioData', () => ({
    KEY: @js($cacheKey),

    init() {
      const path = location.pathname
      const IS_CREATE = /\/crear(?:$|\/|\?)/.test(path)

      if (!IS_CREATE) return

      // 🔁 RESTAURAR CACHE
      try {
        const raw = localStorage.getItem(this.KEY)
        if (raw) {
          const cached = JSON.parse(raw)
          if (cached && typeof cached === 'object') {
            this.$wire.$set('data', cached)
          }
        }
      } catch (_) {}

      // 💾 GUARDAR CACHE
      const save = Alpine.debounce(() => {
        try {
          const d = this.$wire.$get('data')
          localStorage.setItem(this.KEY, JSON.stringify(d ?? {}))
        } catch (_) {}
      }, 300)

      if (window.Livewire?.hook) {
        window.Livewire.hook('message.processed', save)
      }

      this.$watch(() => this.$wire.$get('data'), save)
      window.addEventListener('beforeunload', save)

      // ❌ LIMPIAR CACHE
      window.addEventListener('clear-inventario-cache', () => {
        try {
          localStorage.removeItem(this.KEY)
          sessionStorage.removeItem(this.KEY)
        } catch (_) {}
      })
    },
  }))
})



</script>

<script>
document.addEventListener('livewire:load', function () {

    Livewire.hook('message.processed', () => {

        setTimeout(() => {

            let inputs = document.querySelectorAll('[data-codigo-input="true"]');

            if (!inputs.length) return;

            // 🔥 SIEMPRE EL ÚLTIMO
            let last = inputs[inputs.length - 1];

            if (last) {
                last.focus();
                last.select();
                console.log('FOCUS OK → último input');
            }

        }, 120);

    });

});
</script>


<div x-data="persistInventarioData"></div>
