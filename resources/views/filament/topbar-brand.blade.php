@php
    use Illuminate\Support\Facades\Storage;

    $user = auth()->user();

    if (!$user) {
        return;
    }

    $empresaId = $user->hasRole('super_admin')
        ? null
        : $user->getEmpresaActualId();

    $config = $empresaId
        ? \App\Models\ConfiguracionEmpresa::where('empresa_id', $empresaId)->first()
        : null;
    $nombreEmpresa = strtoupper($config?->nombre_empresa ?: ($user->hasRole('super_admin') ? 'SISTEMA POS' : 'EMPRESA NO CONFIGURADA'));
    $lema = $config?->lema ?: ($user->hasRole('super_admin') ? 'Panel administrativo' : 'Configura el nombre de tu empresa');
@endphp

<div class="topbar-company-pill">
    <div class="topbar-company-copy">
        <strong>{{ $nombreEmpresa }}</strong>
        <span>{{ $lema }}</span>
    </div>
</div>
