@php
    use Illuminate\Support\Facades\Storage;

    $isLogin = request()->routeIs('filament.admin.auth.login');
    $user = auth()->user();
    $empresaId = $user && ! $user->hasRole('super_admin')
        ? $user->getEmpresaActualId()
        : null;

    $config = $empresaId
        ? \App\Models\ConfiguracionEmpresa::where('empresa_id', $empresaId)->first()
        : null;
    $nombreEmpresa = strtoupper($config?->nombre_empresa ?: ($user?->hasRole('super_admin') ? 'SISTEMA POS' : 'EMPRESA NO CONFIGURADA'));
    $lema = $config?->lema ?: ($user?->hasRole('super_admin') ? 'Administracion' : 'Configura el nombre de tu empresa');
@endphp

@if($isLogin)
    <div class="brand-mark login-brand-mark">
        <img
            src="{{ asset('images/login-brand-cart.png') }}"
            alt="Sistema POS"
            class="brand-logo-image login-brand-logo-image"
        >

        <div class="brand-copy login-brand-copy">
            <span class="brand-title">SISTEMA POS</span>
        </div>
    </div>
@else
<div class="brand-mark">
    @if($config && $config->logo)
        <img
            src="{{ Storage::url($config->logo) }}"
            alt="{{ $nombreEmpresa }}"
            class="brand-logo-image"
        >
    @else
        <div class="brand-logo-fallback">
            {{ substr($nombreEmpresa, 0, 2) }}
        </div>
    @endif

    <div class="brand-copy">
        <span class="brand-title">{{ $nombreEmpresa }}</span>
        <span class="brand-subtitle">{{ $lema }}</span>
    </div>
</div>
@endif
