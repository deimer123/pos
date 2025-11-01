<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PrefacturaProducto extends Model
{
    use HasFactory;

    protected $fillable = [
        'prefactura_id',
        'producto_id',
        'descripcion_larga',
        'cantidad',
        'precio_unitario',
        'subtotal',
        'descuento',
        'empresa_id', // Asegúrate de que este campo exista en la base de datos
    ];

    public function prefactura()
    {
        return $this->belongsTo(Prefactura::class);
    }

    public function producto()
    {
        return $this->belongsTo(Product::class, 'producto_id', 'id_producto');
    }
}
