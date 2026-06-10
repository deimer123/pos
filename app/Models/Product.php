<?php

namespace App\Models;
use App\Models\Actor;
use App\Models\AlternateCode;
use App\Models\CuentaContable;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;



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
    ];

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

public function cuentaContable()
{
    return $this->belongsTo(CuentaContable::class, 'cuenta_contable_id');
}
    
public static function stock(int $pid): float
{
    return cache()->remember("stock_$pid", 4, function () use ($pid) {
        return (float) (\App\Models\Product::where('id', $pid)->value('existencias') ?? 0);
    });
}

}
