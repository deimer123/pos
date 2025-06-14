<?php

namespace App\Models;
use App\Models\Product;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AlternateCode extends Model
{
    use HasFactory;

    /**
     * Los atributos que se pueden asignar masivamente.
     *
     * @var array<int,string>
     */
    protected $fillable = [
        'product_id',
        'code',
    ];

    /**
     * Relación inversa con Product.
     */
    public function product()
    {
        return $this->belongsTo(Product::class, 'product_id', 'id_producto');
    }
}
