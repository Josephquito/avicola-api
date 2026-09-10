<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['nombre', 'tipos_medida_permitidos', 'activo'])]
class Categoria extends Model
{
    use Auditable;

    protected function casts(): array
    {
        return [
            'tipos_medida_permitidos' => 'array',
            'activo' => 'boolean',
        ];
    }

    protected function activityDescription(string $action): string
    {
        $actor = \Illuminate\Support\Facades\Auth::user()?->name ?? 'Sistema';

        return match ($action) {
            'created' => "{$actor} creó la categoría {$this->nombre}",
            'updated' => "{$actor} editó la categoría {$this->nombre}",
            'deleted' => "{$actor} eliminó la categoría {$this->nombre}",
            default => "{$actor} modificó la categoría {$this->nombre}",
        };
    }
}