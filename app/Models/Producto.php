<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['nombre', 'categoria_id', 'unidad_id', 'requiere_ciclo_saco', 'activo'])]
class Producto extends Model
{
    use Auditable;

    protected function casts(): array
    {
        return [
            'requiere_ciclo_saco' => 'boolean',
            'activo' => 'boolean',
        ];
    }

    public function categoria()
    {
        return $this->belongsTo(Categoria::class);
    }

    public function unidad()
    {
        return $this->belongsTo(UnidadMedida::class, 'unidad_id');
    }

    protected function activityDescription(string $action): string
    {
        $actor = \Illuminate\Support\Facades\Auth::user()?->name ?? 'Sistema';

        return match ($action) {
            'created' => "{$actor} creó el producto {$this->nombre}",
            'updated' => "{$actor} editó el producto {$this->nombre}",
            'deleted' => "{$actor} eliminó el producto {$this->nombre}",
            default => "{$actor} modificó el producto {$this->nombre}",
        };
    }
}