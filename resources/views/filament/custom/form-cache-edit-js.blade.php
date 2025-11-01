@php
    // Si llega como Closure, evalúalo:
    if (isset($cacheKey) && $cacheKey instanceof \Closure) {
        $cacheKey = $cacheKey();
    }

    // Si no es string, usa un fallback seguro:
    if (! isset($cacheKey) || ! is_string($cacheKey) || $cacheKey === '') {
        $cacheKey = 'compra_form_cache_edit_u_' . (auth()->id() ?? 'guest');
    }
@endphp

<script>
document.addEventListener('alpine:init', () => {
  Alpine.data('persistFilamentData', () => ({
    KEY: @js($cacheKey),

    init() {
      const path     = location.pathname;
      const IS_EDIT = /\/edit(?:$|\/|\?)/.test(path);
      const params    = new URLSearchParams(location.search);
      const HAS_DRAFT = params.has('draft');

      // Solo opera en Edit (por si esta vista se llega a colar):
      if (!IS_EDIT) return;

      try {
        if (!HAS_DRAFT) {
          const raw = localStorage.getItem(this.KEY);
          if (raw) {
            const cached = JSON.parse(raw);
            if (cached && typeof cached === 'object' && Object.keys(cached).length) {
              this.$wire.$set('data', cached);
            }
          }
        } else {
          localStorage.removeItem(this.KEY);
          sessionStorage.removeItem(this.KEY);
        }
      } catch (_) {}

      const save = Alpine.debounce(() => {
        try {
          const d = this.$wire.$get('data');
          localStorage.setItem(this.KEY, JSON.stringify(d ?? {}));
        } catch (_) {}
      }, 300);

      if (window.Livewire?.hook) {
        window.Livewire.hook('message.processed', () => save());
      }
      this.$watch(() => this.$wire.$get('data'), () => save());
      window.addEventListener('beforeunload', save);

      window.addEventListener('clear-form-cache', () => {
        try {
          localStorage.removeItem(this.KEY);
          sessionStorage.removeItem(this.KEY);
        } catch (_) {}
      });
    },
  }));
});
</script>

<div x-data="persistFilamentData"></div>
