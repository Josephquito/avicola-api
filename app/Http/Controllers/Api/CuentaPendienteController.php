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

        return $query->paginate(30);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'tipo' => ['required', 'in:por_cobrar,por_pagar'],
            'contacto_id' => ['required', 'exists:contactos,id'],
            'monto_original' => ['required', 'numeric', 'min:0.01'],
            'fecha' => ['required', 'date'],
        ]);

        $cuenta = CuentaPendiente::create([
            ...$data,
            'saldo_pendiente' => $data['monto_original'],
        ]);

        return response()->json($cuenta->load('contacto'), 201);
    }
}