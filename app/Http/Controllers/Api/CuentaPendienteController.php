<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CuentaPendiente;
use App\Models\Movimiento;
use Illuminate\Http\Request;

class CuentaPendienteController extends Controller
{
    public function index(Request $request)
    {
        $query = CuentaPendiente::with('contacto')->orderBy('fecha', 'asc');

        if ($request->has('tipo')) {
            $query->where('tipo', $request->input('tipo'));
        }

        if ($request->has('contacto_id')) {
            $query->where('contacto_id', $request->input('contacto_id'));
        }

        if ($request->boolean('solo_pendientes')) {
            $query->where('saldo_pendiente', '>', 0);
        }

        return $query->paginate(30);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'tipo' => ['required', 'in:por_cobrar,por_pagar'],
            'contacto_id' => ['required', 'exists:contactos,id'],
            'concepto' => ['required', 'string', 'max:255'],
            'monto_original' => ['required', 'numeric', 'min:0.01'],
            'fecha' => ['required', 'date'],
        ]);

        $cuenta = CuentaPendiente::create([
            ...$data,
            'saldo_pendiente' => $data['monto_original'],
        ]);

        // Toda cuenta arranca como raíz de su propia serie — si más
        // adelante se genera una siguiente vía "recordar pago", esa
        // nueva cuenta hereda este mismo serie_id.
        $cuenta->update(['serie_id' => $cuenta->id]);

        return response()->json($cuenta->load('contacto'), 201);
    }

    public function update(Request $request, CuentaPendiente $cuenta_pendiente)
    {
        $data = $request->validate([
            'concepto' => ['sometimes', 'string', 'max:255'],
            'monto_original' => ['sometimes', 'numeric', 'min:0.01'],
            'fecha' => ['sometimes', 'date'],
        ]);

        // El monto es referencial: si aún no se ha pagado, ajustarlo
        // también actualiza el saldo pendiente mostrado en la lista.
        if (isset($data['monto_original']) && $cuenta_pendiente->saldo_pendiente == $cuenta_pendiente->monto_original) {
            $data['saldo_pendiente'] = $data['monto_original'];
        }

        $cuenta_pendiente->update($data);

        return response()->json($cuenta_pendiente->load('contacto'));
    }

    public function destroy(CuentaPendiente $cuenta_pendiente)
    {
        $tienePagos = Movimiento::where('cuenta_pendiente_id', $cuenta_pendiente->id)->exists();

        if ($tienePagos) {
            return response()->json([
                'message' => 'No se puede eliminar: ya tiene un pago/cobro registrado.',
            ], 422);
        }

        $cuenta_pendiente->delete();

        return response()->json(null, 204);
    }

    /// Toda la cadena de una serie (ej. todos los pagos de "luz" a lo
    /// largo del tiempo), ordenada por fecha, con el total realmente
    /// pagado (sacado de los movimientos, no del monto referencial).
    public function serie(CuentaPendiente $cuenta_pendiente)
    {
        $cuentas = CuentaPendiente::with('contacto')
            ->where('serie_id', $cuenta_pendiente->serie_id)
            ->orderBy('fecha')
            ->get();

        $ids = $cuentas->pluck('id');

        $totalPagado = Movimiento::whereIn('cuenta_pendiente_id', $ids)
            ->whereIn('tipo', ['cobro', 'pago'])
            ->sum('monto');

        return response()->json([
            'cuentas' => $cuentas,
            'total_pagado' => (float) $totalPagado,
            'desde' => $cuentas->first()?->fecha->toDateString(),
        ]);
    }
}