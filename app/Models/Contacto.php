<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['nombre', 'telefono', 'correo', 'cedula_ruc', 'activo'])]
class Contacto extends Model
{
    use Auditable;

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
            'created' => "{$actor} creó el contacto {$this->nombre}",
            'updated' => "{$actor} editó el contacto {$this->nombre}",
            'deleted' => "{$actor} eliminó el contacto {$this->nombre}",
            default => "{$actor} modificó el contacto {$this->nombre}",
        };
    }
}