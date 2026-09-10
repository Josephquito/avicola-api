<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'origen',
    'producto_id',
    'cantidad_gallinas',
    'cantidad_gallos',
    'cantidad_empollando',
    'edad_inicial_dias',
    'fecha_ingreso',
    'costo_total',
    'movimiento_id',
    'lote_origen_id',
    'activo',
])]
class Lote extends Model
{
    use Auditable;

    protected function casts(): array
    {
        return [
            'fecha_ingreso' => 'date',
            'costo_total' => 'decimal:2',
            'activo' => 'boolean',
        ];
    }

    public function producto()
    {
        return $this->belongsTo(Producto::class);
    }

    public function movimiento()
    {
        return $this->belongsTo(Movimiento::class);
    }

    public function loteOrigen()
    {
        return $this->belongsTo(Lote::class, 'lote_origen_id');
    }

    public function lotesHijos()
    {
        return $this->hasMany(Lote::class, 'lote_origen_id');
    }

    public function cantidadTotal(): int
    {
        return $this->cantidad_gallinas + $this->cantidad_gallos;
    }

    public function edadActualDias(): int
    {
        return $this->edad_inicial_dias + $this->fecha_ingreso->diffInDays(now());
    }

    public function corregirSexo(int $cantidadAGallo): void
    {
        $this->update([
            'cantidad_gallinas' => $this->cantidad_gallinas - $cantidadAGallo,
            'cantidad_gallos' => $this->cantidad_gallos + $cantidadAGallo,
        ]);
    }

    public function iniciarEmpolla(int $cantidad): void
    {
        $this->update([
            'cantidad_empollando' => $this->cantidad_empollando + $cantidad,
        ]);
    }

    public function terminarEmpolla(int $cantidad): void
    {
        $this->update([
            'cantidad_empollando' => max(0, $this->cantidad_empollando - $cantidad),
        ]);
    }

    protected function activityDescription(string $action): string
    {
        $actor = \Illuminate\Support\Facades\Auth::user()?->name ?? 'Sistema';

        return match ($action) {
            'created' => "{$actor} registró un lote de {$this->cantidadTotal()} aves (origen: {$this->origen})",
            'updated' => "{$actor} actualizó un lote de aves",
            'deleted' => "{$actor} eliminó un lote de aves",
            default => "{$actor} modificó un lote de aves",
        };
    }
}