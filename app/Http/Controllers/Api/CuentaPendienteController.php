<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CuentaPendiente;
use Illuminate\Http\Request;

class CuentaPendienteController extends Controller
{
    public function index(Request $request)
    {
        $query = CuentaPendiente::with(['contacto', 'movimientoOrigen'])->latest('fecha');

        if ($request->has('tipo')) {
            $query->where('tipo', $request->input('tipo'));
        }

        if ($request->has('contacto_id')) {
            $query->where('contacto_id', $request->input('contacto_id'));
        }

        if ($request->boolean('solo_pendientes')) {
            $query->where('saldo_pendiente', '>', 0);
        }

        // Por defecto, este listado solo muestra cuentas de único concepto
        // (sin recurrencia) — las cuotas de una recurrencia se consultan
        // vía GET /recurrencias/{id}. ?incluir_recurrentes=1 para verlas
        // mezcladas si en algún momento hace falta.
        if (! $request->boolean('incluir_recurrentes')) {
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

        // Sirve tanto para cuentas de único concepto como para editar la
        // fecha/monto de la CUOTA ACTUAL de una recurrencia (ej. "cambió
        // la fecha de pago este mes por tal situación") — no toca la
        // recurrencia en sí ni las cuotas futuras.
        if (isset($data['monto_original']) && $cuenta_pendiente->saldo_pendiente == $cuenta_pendiente->monto_original) {
            $data['saldo_pendiente'] = $data['monto_original'];
        }

        $cuenta_pendiente->update($data);

        return response()->json($cuenta_pendiente->load('contacto'));
    }
}