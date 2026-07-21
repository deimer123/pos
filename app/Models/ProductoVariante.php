<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductoVariante extends Model
{
    use HasFactory;

    protected $table = 'producto_variantes';

    protected $fillable = [
        'empresa_id',
        'product_id',
        'codigo',
        'nombre',
        'atributos',
        'peso',
        'precio_extra',
        'costo_extra',
        'stock',
        'activo',
    ];

    protected $casts = [
        'atributos' => 'array',
        'peso' => 'decimal:3',
        'precio_extra' => 'decimal:2',
        'costo_extra' => 'decimal:2',
        'stock' => 'decimal:3',
        'activo' => 'boolean',
    ];

    // Para que las vistas/JSON que serializan el modelo directamente (ej.
    // AjusteInventarioDetalle::with('variante')) muestren el codigo
    // principal en vez del string crudo "codigo1,codigo2".
    protected $appends = ['codigo_principal'];

    public function getCodigoPrincipalAttribute(): ?string
    {
        return $this->codigoPrincipal();
    }

    public function producto(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    /**
     * El campo "codigo" admite varios codigos separados por coma (el
     * propio de la variante + codigos alternos, ej. de un lector viejo o
     * de un proveedor) -- se normaliza aqui (trim, sin vacios, sin
     * duplicados) cada vez que se guarda, para que las busquedas por
     * codigo exacto (ver scopeConCodigo) sean confiables.
     */
    public function setCodigoAttribute($value): void
    {
        $codigo = trim((string) $value);

        if ($codigo === '') {
            $this->attributes['codigo'] = null;
            return;
        }

        $codigos = collect(explode(',', $codigo))
            ->map(fn ($c) => trim($c))
            ->filter()
            ->unique()
            ->values();

        $this->attributes['codigo'] = $codigos->isEmpty() ? null : $codigos->implode(',');
    }

    public function listaCodigos(): array
    {
        if (blank($this->codigo)) {
            return [];
        }

        return collect(explode(',', $this->codigo))
            ->map(fn ($c) => trim($c))
            ->filter()
            ->values()
            ->all();
    }

    public function codigoPrincipal(): ?string
    {
        return $this->listaCodigos()[0] ?? null;
    }

    /**
     * Busca por CUALQUIERA de los codigos guardados en "codigo" (exacto,
     * no parcial) -- portable entre MySQL y SQLite, sin depender de
     * FIND_IN_SET. Requiere que el valor este normalizado sin espacios
     * extra alrededor de las comas, lo que setCodigoAttribute garantiza.
     */
    public function scopeConCodigo($query, string $codigo)
    {
        $codigo = trim($codigo);

        return $query->where(function ($q) use ($codigo) {
            $q->where('codigo', $codigo)
                ->orWhere('codigo', 'like', $codigo . ',%')
                ->orWhere('codigo', 'like', '%,' . $codigo)
                ->orWhere('codigo', 'like', '%,' . $codigo . ',%');
        });
    }
}
