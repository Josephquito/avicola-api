<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'movimiento_id',
    'producto_id',
    'lote_id',
    'cantidad',
    'cantidad_sacos',
    'peso_por_saco',
    'edad_inicial_dias',
    'cantidad_gallinas',
    'cantidad_gallos',
    'cantidad_huevos',
    'precio_unitario',
    'subtotal',
    'descripcion',
])]
class MovimientoItem extends Model
{
    use Auditable;

    protected function casts(): array
    {
        return [
            'cantidad' => 'decimal:2',
            'peso_por_saco' => 'decimal:2',
            'precio_unitario' => 'decimal:2',
            'subtotal' => 'decimal:2',
        ];
    }

    public function movimiento()
    {
        return $this->belongsTo(Movimiento::class);
    }

    public function producto()
    {
        return $this->belongsTo(Producto::class);
    }

    public function lote()
    {
        return $this->belongsTo(Lote::class);
    }

    /// La categoría del producto es lo que decide qué comportamiento
    /// (Mueble/Medicina/Alimento/Ave/Huevo) maneja esta línea — no
    /// hay un campo propio de "tipo_item", se deriva del producto.
    public function categoriaNombre(): ?string
    {
        return $this->producto?->categoria?->nombre;
    }

    protected function activityDescription(string $action): string
    {
        $actor = \Illuminate\Support\Facades\Auth::user()?->name ?? 'Sistema';

        return match ($action) {
            'created' => "{$actor} agregó una línea de \${$this->subtotal} al movimiento #{$this->movimiento_id}",
            'updated' => "{$actor} editó una línea del movimiento #{$this->movimiento_id}",
            'deleted' => "{$actor} eliminó una línea del movimiento #{$this->movimiento_id}",
            default => "{$actor} modificó una línea del movimiento #{$this->movimiento_id}",
        };
    }
}