<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Producto;
use App\Models\SacoAlimento;
use Illuminate\Http\Request;

class SacoAlimentoController extends Controller
{
    public function index(Request $request)
    {
        $query = SacoAlimento::with(['producto', 'unidad'])->latest('fecha_compra');

        if ($request->has('producto_id')) {
            $query->where('producto_id', $request->input('producto_id'));
        }

        if ($request->has('estado')) {
            $query->where('estado', $request->input('estado'));
        }

        return $query->paginate(30);
    }

    public function resumen(Producto $producto)
    {
        $sacos = SacoAlimento::where('producto_id', $producto->id)->get();

        return response()->json([
            'producto' => $producto,
            'comprados' => $sacos->count(),
            'en_espera' => $sacos->where('estado', 'en_espera')->count(),
            'en_uso' => $sacos->where('estado', 'en_uso')->count(),
            'terminados' => $sacos->where('estado', 'terminado')->count(),
            'peso_en_stock' => $sacos->whereIn('estado', ['en_espera', 'en_uso'])->sum('peso'),
        ]);
    }

    public function iniciarUso(SacoAlimento $saco_alimento)
    {
        if ($saco_alimento->estado !== 'en_espera') {
            return response()->json(['message' => 'Solo se puede iniciar el uso de un saco que está en espera.'], 422);
        }

        $saco_alimento->iniciarUso();

        return response()->json($saco_alimento->fresh()->load(['producto', 'unidad']));
    }

    public function terminar(SacoAlimento $saco_alimento)
    {
        if ($saco_alimento->estado !== 'en_uso') {
            return response()->json(['message' => 'Solo se puede terminar un saco que está en uso.'], 422);
        }

        $saco_alimento->terminar();

        return response()->json($saco_alimento->fresh()->load(['producto', 'unidad']));
    }
}