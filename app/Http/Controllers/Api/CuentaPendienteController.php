<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CuentaPendiente;
use Illuminate\Http\Request;

class CuentaPendienteController extends Controller
{
    public function index(Request $request)
    {
        $query = CuentaPendiente::with(['contacto', 'movimientoOrigen'])
            ->orderBy('fecha', 'asc');

        if ($request->has('tipo')) {
            $query->where('tipo', $request->input('tipo'));
        }

        if ($request->has('contacto_id')) {
            $query->where('contacto_id', $request->input('contacto_id'));
        }

        if ($request->boolean('solo_pendientes')) {
            $query->where('saldo_pendiente', '>', 0);
        }

        if ($request->boolean('incluir_recurrentes')) {
            // Cuentas de único concepto, o cuotas de una recurrencia
            // que sigue ACTIVA. Las cuotas de recurrencias dadas de
            // baja quedan fuera del listado principal (solo se ven
            // desde el detalle de esa recurrencia).
            $query->where(function ($q) {
                $q->whereNull('recurrencia_id')
                    ->orWhereHas('recurrencia', fn ($r) => $r->where('activa', true));
            });
        } else {
            $query->whereNull('recurrencia_id');
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

        return response()->json($cuenta->load('contacto'), 201);
    }

    public function update(Request $request, CuentaPendiente $cuenta_pendiente)
    {
        $data = $request->validate([
            'concepto' => ['sometimes', 'string', 'max:255'],
            'monto_original' => ['sometimes', 'numeric', 'min:0.01'],
            'fecha' => ['sometimes', 'date'],
        ]);

        if (isset($data['monto_original']) && $cuenta_pendiente->saldo_pendiente == $cuenta_pendiente->monto_original) {
            $data['saldo_pendiente'] = $data['monto_original'];
        }

        $cuenta_pendiente->update($data);

        return response()->json($cuenta_pendiente->load('contacto'));
    }

        public function destroy(CuentaPendiente $cuenta_pendiente)
    {
        if ($cuenta_pendiente->recurrencia_id !== null) {
            return response()->json([
                'message' => 'Esta cuota pertenece a una recurrencia. Elimina o suspende la recurrencia completa desde su detalle.',
            ], 422);
        }

        if (bccomp($cuenta_pendiente->saldo_pendiente, $cuenta_pendiente->monto_original, 2) !== 0) {
            return response()->json([
                'message' => 'No se puede eliminar: ya tiene abonos registrados.',
            ], 422);
        }

        $cuenta_pendiente->delete();

        return response()->json(null, 204);
    }
}