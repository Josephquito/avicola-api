<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CuentaEfectivo;
use Illuminate\Http\Request;
use App\Models\Movimiento;

class CuentaEfectivoController extends Controller
{
    public function index()
    {
        return CuentaEfectivo::orderBy('nombre')->get();
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'nombre' => ['required', 'string', 'max:255'],
            'tipo' => ['required', 'in:caja,banco'],
            'numero_cuenta' => ['required_if:tipo,banco', 'nullable', 'string', 'max:50'],
        ]);

        $existente = null;

        if ($data['tipo'] === 'banco') {
            $existente = CuentaEfectivo::where('numero_cuenta', $data['numero_cuenta'])->first();
        } else {
            $existente = CuentaEfectivo::where('tipo', 'caja')->where('nombre', $data['nombre'])->first();
        }

        if ($existente) {
            if ($existente->activo) {
                return response()->json([
                    'message' => 'Ya existe una cuenta activa con ese identificador.',
                ], 422);
            }

            $existente->update(array_merge($data, ['activo' => true]));

            return response()->json($existente);
        }

        $cuenta = CuentaEfectivo::create($data);

        return response()->json($cuenta, 201);
    }

    public function update(Request $request, CuentaEfectivo $cuentas_efectivo)
    {
        $data = $request->validate([
            'nombre' => ['sometimes', 'string', 'max:255'],
            'tipo' => ['sometimes', 'in:caja,banco'],
            'numero_cuenta' => ['nullable', 'string', 'max:50'],
            'activo' => ['sometimes', 'boolean'],
        ]);

        $cuentas_efectivo->update($data);

        return response()->json($cuentas_efectivo);
    }

    public function destroy(CuentaEfectivo $cuentas_efectivo)
    {
        $cuentas_efectivo->update(['activo' => false]);

        return response()->json(['message' => 'Cuenta desactivada.']);
    }

    public function saldo(CuentaEfectivo $cuentas_efectivo)
    {
    return response()->json([
        'cuenta' => $cuentas_efectivo,
        'saldo_actual' => $cuentas_efectivo->saldoActual(),
    ]);
    }

    public function movimientos(CuentaEfectivo $cuentas_efectivo)
{
    $entradas = ['aporte_capital', 'cobro', 'venta_aves', 'venta_huevos'];
    $salidas = ['retiro_capital', 'pago', 'compra_muebles', 'compra_medicina', 'compra_alimento', 'compra_aves'];

    $movimientos = Movimiento::where(function ($q) use ($cuentas_efectivo) {
            $q->where('cuenta_efectivo_id', $cuentas_efectivo->id)
              ->orWhere('cuenta_destino_id', $cuentas_efectivo->id);
        })
        ->with(['contacto:id,nombre', 'socio:id,name'])
        ->orderBy('fecha')
        ->orderBy('id')
        ->get();

    $saldo = 0;

    $resultado = $movimientos->map(function ($mov) use (&$saldo, $entradas, $salidas, $cuentas_efectivo) {
        $monto = (float) $mov->monto;
        $signo = 0;

        if ($mov->tipo === 'transferencia') {
            if ($mov->cuenta_efectivo_id === $cuentas_efectivo->id) {
                $signo = -1;
            } elseif ($mov->cuenta_destino_id === $cuentas_efectivo->id) {
                $signo = 1;
            }
        } elseif (in_array($mov->tipo, $entradas)) {
            $signo = 1;
        } elseif (in_array($mov->tipo, $salidas)) {
            $signo = -1;
        }

        $saldo += $signo * $monto;

        return [
            'id' => $mov->id,
            'tipo' => $mov->tipo,
            'fecha' => $mov->fecha,
            'monto' => $monto,
            'signo' => $signo,
            'descripcion' => $mov->descripcion,
            'contacto' => $mov->contacto?->nombre,
            'socio' => $mov->socio?->name,
            'saldo_despues' => round($saldo, 2),
        ];
    });

    return response()->json($resultado->reverse()->values());
}
}