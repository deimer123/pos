<?php

namespace App\Models;
use App\Models\Actor;
use App\Models\AlternateCode;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;



use Illuminate\Database\Eloquent\Model;

class Product extends Model
{


     protected static function booted()
{
    static::saving(function ($product) {
        if ($product->exists) {
            if ($product->isDirty('precio_costo')) {
                $product->precio_costo_anterior = $product->getOriginal('precio_costo');
            }

            if ($product->isDirty('precio_venta1')) {
                $product->precio_venta_anterior = $product->getOriginal('precio_venta1');
            }
        }
    });
}

    protected $table = 'products';
    protected $primaryKey = 'id'; // Clave primaria técnica autoincremental
    public $incrementing = true;   // Autoincremental
    protected $keyType = 'int';

    protected $fillable = [
        'id_producto',
        
        'id_familia1',
        'id_familia2',
        'empresa_id',
        'cuenta_contable_id',
        'descripcion_larga',
        'tipo_producto',
        'tipo_servicio',
        'mecanico_id',
        'tercero_nombre',
        'porcentaje_empresa',
        'vende_por',
        'maneja_inventario',
        'permite_fraccion',
        'requiere_cocina',
        'peso_base',
        'id_proveedor', // ✅ CAMPO CORRECTO
        'iva_compra',
        'iva_venta',
        'existencias',
        'precio_costo',
        'precio_venta1',
        'utilidad1',
        'id_unidad_de_medida',
        'foto',
        'descuento_comercial',
        'costo_iva',
        'precio_con_descuento',
        'precio_costo_anterior',
        'precio_venta_anterior',
    ];

    protected $casts = [
        'foto' => 'string',
        'existencias' => 'decimal:2',
        'maneja_inventario' => 'boolean',
        'permite_fraccion' => 'boolean',
        'requiere_cocina' => 'boolean',
        'peso_base' => 'decimal:3',
        'porcentaje_empresa' => 'decimal:2',
    ];

    public function mecanico()
    {
        return $this->belongsTo(Mecanico::class, 'mecanico_id');
    }

    public function proveedor()
    {
        return $this->belongsTo(Actor::class, 'id_proveedor', 'id_clip_pro');
    }
    public function proveedorActor()
{
    return $this->belongsTo(Actor::class, 'id_proveedor', 'id_clip_pro');
}

 public function alternateCodes()
    {
        return $this->hasMany(AlternateCode::class, 'product_id', 'id'); // Usa 'id' técnico
    }
    

    public function familia1(): BelongsTo
    {
        return $this->belongsTo(
            Familia::class,
            'id_familia1',  // FK en products
            'id'            // PK real en familias
        );
    }

    public function subfamilia(): BelongsTo
    {
        return $this->belongsTo(
            Subfamilia::class,
            'id_familia2',   // FK en products
            'id_familia2'    // PK en subfamilias
        );
    }
public function empresa()
{
    return $this->belongsTo(User::class, 'empresa_id');
}

public function cuentaContable(): BelongsTo
    {
        return $this->belongsTo(CuentaContable::class, 'cuenta_contable_id');
    }

    public function recetas(): HasMany
    {
        return $this->hasMany(Receta::class, 'product_id', 'id');
    }

    public function recetaPrincipal(): HasOne
    {
        return $this->hasOne(Receta::class, 'product_id', 'id');
    }

    public function combos(): HasMany
    {
        return $this->hasMany(ProductCombo::class, 'product_id', 'id')
            ->where('activo', true)
            ->orderBy('cantidad_minima');
    }

    public function variantes(): HasMany
    {
        return $this->hasMany(ProductoVariante::class, 'product_id', 'id');
    }
    
    public function permiteCantidadDecimal(): bool
    {
        $vendePor = strtolower(trim((string) ($this->vende_por ?? '')));

        if ($vendePor !== '') {
            return in_array($vendePor, ['peso', 'porcion', 'litro', 'metro', 'hora'], true);
        }

        if (! is_null($this->permite_fraccion)) {
            return (bool) $this->permite_fraccion;
        }

        return (int) ($this->id_unidad_de_medida ?? 1) !== 1;
    }

public static function stock(int $pid): float
{
    return cache()->remember("stock_$pid", 4, function () use ($pid) {
        return (float) (\App\Models\Product::where('id', $pid)->value('existencias') ?? 0);
    });
}

}

// appended
