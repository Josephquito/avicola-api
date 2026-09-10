<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'tipo',
    'contacto_id',
    'monto_original',
    'saldo_pendiente',
    'movimiento_origen_id',
    'fecha',
])]
class CuentaPendiente extends Model
{
    use Auditable;

    protected $table = 'cuentas_pendientes';

    protected function casts(): array
    {
        return [
            'fecha' => 'date',
            'monto_original' => 'decimal:2',
            'saldo_pendiente' => 'decimal:2',
        ];
    }

    public function contacto()
    {
        return $this->belongsTo(Contacto::class);
    }

    public function movimientoOrigen()
    {
        return $this->belongsTo(Movimiento::class, 'movimiento_origen_id');
    }

    public function estaSaldada(): bool
    {
        return $this->saldo_pendiente <= 0;
    }

    protected function activityDescription(string $action): string
    {
        $actor = \Illuminate\Support\Facades\Auth::user()?->name ?? 'Sistema';
        $etiqueta = $this->tipo === 'por_cobrar' ? 'cuenta por cobrar' : 'cuenta por pagar';

        return match ($action) {
            'created' => "{$actor} registró una {$etiqueta} de \${$this->monto_original}",
            'updated' => "{$actor} actualizó una {$etiqueta} (saldo: \${$this->saldo_pendiente})",
            'deleted' => "{$actor} eliminó una {$etiqueta}",
            default => "{$actor} modificó una {$etiqueta}",
        };
    }
}