<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class PairingCode extends Model
{
    protected $table = 'pairing_codes';

    protected $fillable = [
        'codigo',
        'empresa_id',
        'creado_por',
        'expira_at',
        'usado_at',
        'terminal_nombre',
    ];

    protected $casts = [
        'expira_at' => 'datetime',
        'usado_at' => 'datetime',
    ];

    public static function generarPara(int $empresaId, ?int $creadoPor = null): self
    {
        do {
            $codigo = strtoupper(Str::random(8));
        } while (static::where('codigo', $codigo)->exists());

        return static::create([
            'codigo' => $codigo,
            'empresa_id' => $empresaId,
            'creado_por' => $creadoPor,
            'expira_at' => now()->addMinutes(30),
        ]);
    }

    public function valido(): bool
    {
        return is_null($this->usado_at) && $this->expira_at->isFuture();
    }

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(User::class, 'empresa_id');
    }
}
