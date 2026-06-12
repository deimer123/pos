<?php

namespace App\Exports\Contabilidad;

use App\Models\Actor;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class TercerosContableExport implements FromCollection, ShouldAutoSize, WithHeadings, WithMapping
{
    public function __construct(protected int $empresaId, protected string $tipo = 'todos')
    {
    }

    public function collection(): Collection
    {
        return Actor::query()
            ->where('empresa_id', $this->empresaId)
            ->when($this->tipo !== 'todos', fn ($q) => match ($this->tipo) {
                'cliente' => $q->whereIn('clasificacion', ['cliente', 'cliente_proveedor']),
                'proveedor' => $q->whereIn('clasificacion', ['proveedor', 'cliente_proveedor']),
                default => $q,
            })
            ->orderBy('nombre')
            ->get();
    }

    public function headings(): array
    {
        return ['Documento', 'Nombre', 'Clasificación', 'Teléfono', 'Email'];
    }

    public function map($actor): array
    {
        return [
            $actor->identificacion,
            $actor->nombre,
            $actor->roles,
            $actor->telefono,
            $actor->email,
        ];
    }
}
