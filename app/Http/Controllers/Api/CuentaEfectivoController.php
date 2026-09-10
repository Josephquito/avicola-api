<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CuentaEfectivo;
use Illuminate\Http\Request;

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
}