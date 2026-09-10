<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['nombre', 'tipo', 'numero_cuenta', 'activo'])]
class CuentaEfectivo extends Model
{
    use Auditable;

    protected $table = 'cuentas_efectivo';

    protected function casts(): array
    {
        return [
            'activo' => 'boolean',
        ];
    }

public function saldoActual(): float
{
    $entradasTipos = ['aporte_capital', 'cobro', 'venta_aves', 'venta_huevos'];
    $salidasTipos = ['retiro_capital', 'pago', 'compra_muebles', 'compra_medicina', 'compra_alimento', 'compra_aves', 'comision_transferencia', 'gasto_operativo'];

    $origen = Movimiento::where('cuenta_efectivo_id', $this->id)
        ->selectRaw(
            'COALESCE(SUM(CASE WHEN tipo IN (\'' . implode("','", $entradasTipos) . '\') THEN monto ELSE 0 END), 0) as entradas,
             COALESCE(SUM(CASE WHEN tipo IN (\'' . implode("','", $salidasTipos) . '\') THEN monto ELSE 0 END), 0) as salidas,
             COALESCE(SUM(CASE WHEN tipo = \'transferencia\' THEN monto ELSE 0 END), 0) as transferencias_salientes'
        )
        ->first();

    $transferenciasEntrantes = Movimiento::where('cuenta_destino_id', $this->id)
        ->where('tipo', 'transferencia')
        ->sum('monto');

    return (float) ($origen->entradas + $transferenciasEntrantes - $origen->salidas - $origen->transferencias_salientes);
}

    protected function activityDescription(string $action): string
    {
        $actor = \Illuminate\Support\Facades\Auth::user()?->name ?? 'Sistema';

        return match ($action) {
            'created' => "{$actor} creó la cuenta {$this->nombre} ({$this->tipo})",
            'updated' => "{$actor} editó la cuenta {$this->nombre}",
            'deleted' => "{$actor} eliminó la cuenta {$this->nombre}",
            default => "{$actor} modificó la cuenta {$this->nombre}",
        };
    }
}