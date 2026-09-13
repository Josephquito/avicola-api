<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Movimiento;
use App\Models\Recurrencia;
use Illuminate\Http\Request;

class RecurrenciaController extends Controller
{
    public function index(Request $request)
    {
        $query = Recurrencia::with('contacto')->latest('fecha_inicio');

        if ($request->has('tipo')) {
            $query->where('tipo', $request->input('tipo'));
        }

        // Por defecto solo activas; ?incluir_canceladas=1 para ver también
        // las dadas de baja (nunca se eliminan físicamente).
        if (! $request->boolean('incluir_canceladas')) {
            $query->where('activa', true);
        }

        return $query->paginate(30);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'tipo' => ['required', 'in:por_cobrar,por_pagar'],
            'contacto_id' => ['required', 'exists:contactos,id'],
            'concepto' => ['required', 'string', 'max:255'],
            'monto_base' => ['required', 'numeric', 'min:0.01'],
            'frecuencia' => ['required', 'in:mensual,trimestral,anual'],
            'fecha_inicio' => ['required', 'date'],
        ]);

        $recurrencia = Recurrencia::create($data);

        // Se genera de una vez la primera cuota de la serie.
        $recurrencia->cuentasPendientes()->create([
            'tipo' => $recurrencia->tipo,
            'contacto_id' => $recurrencia->contacto_id,
            'concepto' => $recurrencia->concepto,
            'monto_original' => $recurrencia->monto_base,
            'saldo_pendiente' => $recurrencia->monto_base,
            'fecha' => $recurrencia->fecha_inicio,
        ]);

        return response()->json($recurrencia->load('cuentasPendientes'), 201);
    }

    public function show(Recurrencia $recurrencia)
    {
        $cuotas = $recurrencia->cuentasPendientes()->get();
        $cuotaIds = $cuotas->pluck('id');

        $totalPagado = Movimiento::whereIn('cuenta_pendiente_id', $cuotaIds)
            ->whereIn('tipo', ['cobro', 'pago'])
            ->sum('monto');

        return response()->json([
            'recurrencia' => $recurrencia->load('contacto'),
            'cuotas' => $cuotas,
            'resumen' => [
                'cantidad_cuotas' => $cuotas->count(),
                'total_pagado' => (float) $totalPagado,
                'desde' => $recurrencia->fecha_inicio->toDateString(),
            ],
        ]);
    }

    public function update(Request $request, Recurrencia $recurrencia)
    {
        $data = $request->validate([
            'concepto' => ['sometimes', 'string', 'max:255'],
            'monto_base' => ['sometimes', 'numeric', 'min:0.01'],
            'frecuencia' => ['sometimes', 'in:mensual,trimestral,anual'],
        ]);

        // Solo afecta las cuotas que se generen a futuro. La cuota actual
        // (ya creada) no cambia aquí — para eso está
        // PUT /cuentas-pendientes/{id} directamente sobre esa cuota.
        $recurrencia->update($data);

        return response()->json($recurrencia->load('contacto'));
    }

    public function desactivar(Recurrencia $recurrencia)
    {
        // Dar de baja: no se borra nada. Solo deja de generar la
        // siguiente cuota al saldarse la actual. Historial intacto.
        $recurrencia->update(['activa' => false]);

        return response()->json($recurrencia->load('contacto'));
    }
}