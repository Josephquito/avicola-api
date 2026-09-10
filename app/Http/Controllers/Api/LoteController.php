<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Lote;
use Illuminate\Http\Request;

class LoteController extends Controller
{
    public function index(Request $request)
    {
        $query = Lote::with('loteOrigen')->latest('fecha_ingreso');

        if ($request->boolean('solo_activos')) {
            $query->where('activo', true);
        }

        $lotes = $query->get();

        $lotes->transform(function ($lote) {
            $lote->cantidad_total = $lote->cantidadTotal();
            $lote->edad_actual_dias = $lote->edadActualDias();

            return $lote;
        });

        return response()->json($lotes);
    }

    public function corregirSexo(Request $request, Lote $lote)
    {
        $data = $request->validate([
            'cantidad_a_gallo' => ['required', 'integer', 'min:1'],
        ]);

        if ($data['cantidad_a_gallo'] > $lote->cantidad_gallinas) {
            return response()->json(['message' => 'No puedes corregir más gallinas de las que hay disponibles en el lote.'], 422);
        }

        $lote->corregirSexo($data['cantidad_a_gallo']);

        return response()->json($lote->fresh());
    }
}