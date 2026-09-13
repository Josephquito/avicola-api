<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

#[Fillable([
    'tipo',
    'contacto_id',
    'concepto',
    'monto_base',
    'frecuencia',
    'fecha_inicio',
    'activa',
])]
class Recurrencia extends Model
{
    use Auditable;

    protected $table = 'recurrencias';

    protected function casts(): array
    {
        return [
            'fecha_inicio' => 'date',
            'monto_base' => 'decimal:2',
            'activa' => 'boolean',
        ];
    }

    public function contacto()
    {
        return $this->belongsTo(Contacto::class);
    }

    public function cuentasPendientes()
    {
        return $this->hasMany(CuentaPendiente::class)->orderBy('fecha');
    }

    public function siguienteFechaDesde(Carbon $fecha): Carbon
    {
        return match ($this->frecuencia) {
            'mensual' => $fecha->copy()->addMonth(),
            'trimestral' => $fecha->copy()->addMonths(3),
            'anual' => $fecha->copy()->addYear(),
            default => $fecha->copy()->addMonth(),
        };
    }

    protected function activityDescription(string $action): string
    {
        $actor = \Illuminate\Support\Facades\Auth::user()?->name ?? 'Sistema';
        $etiqueta = $this->tipo === 'por_cobrar' ? 'cobro recurrente' : 'pago recurrente';

        return match ($action) {
            'created' => "{$actor} creó un {$etiqueta}: {$this->concepto}",
            'updated' => "{$actor} actualizó un {$etiqueta}: {$this->concepto}",
            'deleted' => "{$actor} eliminó un {$etiqueta}: {$this->concepto}",
            default => "{$actor} modificó un {$etiqueta}: {$this->concepto}",
        };
    }
}