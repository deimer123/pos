{{-- Lista de sucursales del dueño con el boton Entrar. Variable: $sucursales [['modelo','motivo']] --}}
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
