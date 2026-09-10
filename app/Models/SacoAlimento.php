<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'producto_id',
    'unidad_id',
    'peso',
    'estado',
    'fecha_compra',
    'fecha_inicio_uso',
    'fecha_fin_uso',
    'movimiento_id',
])]
class SacoAlimento extends Model
{
    use Auditable;

    protected $table = 'sacos_alimento';

    protected function casts(): array
    {
        return [
            'peso' => 'decimal:2',
            'fecha_compra' => 'date',
            'fecha_inicio_uso' => 'date',
            'fecha_fin_uso' => 'date',
        ];
    }

    public function producto()
    {
        return $this->belongsTo(Producto::class);
    }

    public function unidad()
    {
        return $this->belongsTo(UnidadMedida::class, 'unidad_id');
    }

    public function movimiento()
    {
        return $this->belongsTo(Movimiento::class);
    }

    public function iniciarUso(): void
    {
        $this->update([
            'estado' => 'en_uso',
            'fecha_inicio_uso' => now(),
        ]);
    }

    public function terminar(): void
    {
        $this->update([
            'estado' => 'terminado',
            'fecha_fin_uso' => now(),
        ]);
    }

    public function diasDeUso(): ?int
    {
        if (! $this->fecha_inicio_uso) {
            return null;
        }

        $fin = $this->fecha_fin_uso ?? now();

        return (int) $this->fecha_inicio_uso->diffInDays($fin);
    }

    protected function activityDescription(string $action): string
    {
        $actor = \Illuminate\Support\Facades\Auth::user()?->name ?? 'Sistema';

        return match ($action) {
            'created' => "{$actor} registró un saco de alimento de {$this->peso} kg",
            'updated' => "{$actor} actualizó un saco de alimento (estado: {$this->estado})",
            'deleted' => "{$actor} eliminó un saco de alimento",
            default => "{$actor} modificó un saco de alimento",
        };
    }
}