<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'tipo',
    'fecha',
    'monto',
    'descripcion',
    'cuenta_efectivo_id',
    'cuenta_destino_id',
    'socio_id',
    'contacto_id',
    'estado',
    'cantidad_huevos',
    'user_id',
])]
class Movimiento extends Model
{
    use Auditable;

    protected function casts(): array
    {
        return [
            'fecha' => 'date',
            'monto' => 'decimal:2',
        ];
    }

    public function cuentaEfectivo()
    {
        return $this->belongsTo(CuentaEfectivo::class, 'cuenta_efectivo_id');
    }

    public function cuentaDestino()
    {
        return $this->belongsTo(CuentaEfectivo::class, 'cuenta_destino_id');
    }

    public function socio()
    {
        return $this->belongsTo(User::class, 'socio_id');
    }

    public function contacto()
    {
        return $this->belongsTo(Contacto::class);
    }

    public function usuario()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    protected function activityDescription(string $action): string
    {
        $actor = \Illuminate\Support\Facades\Auth::user()?->name ?? 'Sistema';

        return match ($action) {
            'created' => "{$actor} registró un movimiento de tipo '{$this->tipo}' por \${$this->monto}",
            'updated' => "{$actor} editó un movimiento de tipo '{$this->tipo}'",
            'deleted' => "{$actor} eliminó un movimiento de tipo '{$this->tipo}'",
            default => "{$actor} modificó un movimiento de tipo '{$this->tipo}'",
        };
    }
}