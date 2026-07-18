<?php

namespace App\Exports;

use App\Models\Actor;
use App\Models\Familia;
use App\Models\Subfamilia;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;

class ProductBulkTemplateReferenciaSheet implements FromArray, WithHeadings, WithTitle
{
    public function __construct(protected int $empresaId)
    {
    }

    public function title(): string
    {
        return 'Referencia';
    }

    public function headings(): array
    {
        return [
            'Instrucciones',
            'Proveedores existentes',
            'Departamentos existentes',
            'Subfamilias existentes',
        ];
    }

    public function array(): array
    {
        $instrucciones = [
            'El Codigo del producto NO se pide: se genera solo, en orden, siguiendo el ultimo codigo que ya tienes.',
            'Nombre del producto, Precio Costo y Precio de Venta son obligatorios. Las demas columnas se pueden dejar vacias.',
            'Proveedor / Departamento / Subfamilia: escribe el nombre. Si ya existe (ver las columnas de la derecha) se usa ese mismo. Si no existe, se crea uno nuevo con ese nombre.',
            'Si dejas Proveedor / Departamento / Subfamilia vacios, el producto queda con "PROVEEDOR DE PRUEBA" / "FAMILIA DE PRUEBA" / "SUBFAMILIA DE PRUEBA" (puedes editarlo despues).',
            'Unidad de Medida: Pieza, Kilogramos, Litros, Metros u Horas. Si la dejas vacia, queda en Pieza.',
            'Descuento Comercial, IVA Compra e IVA Venta: numero sin el simbolo %. Ejemplo: 19. Si los dejas vacios, IVA Compra e IVA Venta quedan en 19 y el Descuento en 0.',
        ];

        $proveedores = Actor::where('empresa_id', $this->empresaId)
            ->whereIn('clasificacion', ['proveedor', 'cliente_proveedor'])
            ->orderBy('nombre')
            ->pluck('nombre')
            ->values();

        $familias = Familia::where('empresa_id', $this->empresaId)
            ->orderBy('nombre')
            ->pluck('nombre')
            ->values();

        $subfamilias = Subfamilia::where('empresa_id', $this->empresaId)
            ->with('familia')
            ->orderBy('nombre')
            ->get()
            ->map(fn ($s) => $s->nombre . ' (' . (optional($s->familia)->nombre ?? '-') . ')')
            ->values();

        $filas = max(count($instrucciones), $proveedores->count(), $familias->count(), $subfamilias->count());

        $resultado = [];
        for ($i = 0; $i < $filas; $i++) {
            $resultado[] = [
                $instrucciones[$i] ?? '',
                $proveedores->get($i, ''),
                $familias->get($i, ''),
                $subfamilias->get($i, ''),
            ];
        }

        return $resultado;
    }
}
