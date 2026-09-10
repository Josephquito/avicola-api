<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['nombre', 'abreviatura', 'tipo_medida', 'activo'])]
class UnidadMedida extends Model
{
    use Auditable;

    protected $table = 'unidades_medida';

    protected function casts(): array
    {
        return [
            'activo' => 'boolean',
        ];
    }

    protected function activityDescription(string $action): string
    {
        $actor = \Illuminate\Support\Facades\Auth::user()?->name ?? 'Sistema';

        return match ($action) {
            'created' => "{$actor} creó la unidad de medida {$this->nombre}",
            'updated' => "{$actor} editó la unidad de medida {$this->nombre}",
            'deleted' => "{$actor} eliminó la unidad de medida {$this->nombre}",
            default => "{$actor} modificó la unidad de medida {$this->nombre}",
        };
    }
}