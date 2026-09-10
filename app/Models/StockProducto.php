<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'producto_id',
    'unidad_id',
    'cantidad',
    'tipo_movimiento',
    'movimiento_id',
    'fecha',
    'descripcion',
])]
class StockProducto extends Model
{
    use Auditable;

    protected $table = 'stock_productos';

    protected function casts(): array
    {
        return [
            'fecha' => 'date',
            'cantidad' => 'decimal:2',
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

    protected function activityDescription(string $action): string
    {
        $actor = \Illuminate\Support\Facades\Auth::user()?->name ?? 'Sistema';
        $verbo = $this->tipo_movimiento === 'compra' ? 'registró la compra de' : 'registró el consumo de';

        return match ($action) {
            'created' => "{$actor} {$verbo} {$this->cantidad} unidades de producto",
            'updated' => "{$actor} editó un registro de stock",
            'deleted' => "{$actor} eliminó un registro de stock",
            default => "{$actor} modificó un registro de stock",
        };
    }
}