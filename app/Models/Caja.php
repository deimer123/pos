<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

class Caja extends Model
{
    protected $fillable = [
        'user_id','empresa_id','monto_apertura','monto_cierre',
        'opened_at','closed_at','estado'
    ];

    protected $casts = [
        'opened_at' => 'datetime',
        'closed_at' => 'datetime',
    ];

    public function isOpen(): bool
    {
        return $this->estado === 'abierta' && $this->closed_at === null;
    }

    public function isOpenToday(): bool
    {
        if (! $this->isOpen() || ! $this->opened_at) return false;
        return $this->opened_at->toDateString() === now()->toDateString();
    }
}