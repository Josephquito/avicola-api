<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['fecha', 'cantidad', 'descripcion'])]
class ProduccionHuevo extends Model
{
    use Auditable;

    protected $table = 'producciones_huevos';

    protected function casts(): array
    {
        return [
            'fecha' => 'date',
        ];
    }

    protected function activityDescription(string $action): string
    {
        $actor = \Illuminate\Support\Facades\Auth::user()?->name ?? 'Sistema';

        return match ($action) {
            'created' => "{$actor} registró {$this->cantidad} huevos producidos el {$this->fecha->format('Y-m-d')}",
            'updated' => "{$actor} editó un registro de producción de huevos",
            'deleted' => "{$actor} eliminó un registro de producción de huevos",
            default => "{$actor} modificó un registro de producción de huevos",
        };
    }
}