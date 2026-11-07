<x-filament-panels::page>
    @if (session('grupo_error'))
        <div style="padding:.75rem 1rem;border-radius:.5rem;background:#fef2f2;color:#991b1b;border:1px solid #fecaca;">
            {{ session('grupo_error') }}
        </div>
    @endif

    <p style="font-size:.9rem;color:#4b5563;">
        Cada sucursal es una empresa con su propia configuración, productos, empleados y plan.
        Pulsa <strong>Entrar</strong> para trabajar en ella y <strong>Volver a mi empresa</strong> cuando termines.
        Para crear otra sucursal, pídelo al administrador del sistema.
    </p>

    <x-filament::section>
        <div style="overflow-x:auto;">
            <table style="width:100%;border-collapse:collapse;font-size:.9rem;">
                <thead>
                    <tr style="text-align:left;border-bottom:1px solid #e5e7eb;">
                        <th style="padding:.5rem;">Sucursal</th>
                        <th style="padding:.5rem;">Correo</th>
                        <th style="padding:.5rem;">Plan</th>
                        <th style="padding:.5rem;">Vence</th>
                        <th style="padding:.5rem;">Estado</th>
                        <th style="padding:.5rem;"></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($sucursales as $fila)
                        @php $s = $fila['modelo']; @endphp
                        <tr style="border-bottom:1px solid #f3f4f6;">
                            <td style="padding:.5rem;font-weight:600;">{{ $s->name }}</td>
                            <td style="padding:.5rem;">{{ $s->email }}</td>
                            <td style="padding:.5rem;">{{ $s->plan?->nombre ?? '—' }}</td>
                            <td style="padding:.5rem;">{{ $s->plan_ends_at?->format('d/m/Y') ?? '—' }}</td>
                            <td style="padding:.5rem;">
                                @if ($fila['motivo'])
                                    <span style="color:#b91c1c;" title="{{ $fila['motivo'] }}">No disponible</span>
                                @else
                                    <span style="color:#15803d;">Activa</span>
                                @endif
                            </td>
                            <td style="padding:.5rem;text-align:right;">
                                @if ($fila['motivo'])
                                    <span style="font-size:.8rem;color:#6b7280;">{{ $fila['motivo'] }}</span>
                                @else
                                    <form method="POST" action="{{ route('grupo.entrar', $s) }}">
                                        @csrf
                                        <x-filament::button type="submit" size="sm">Entrar</x-filament::button>
                                    </form>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </x-filament::section>
</x-filament-panels::page>
