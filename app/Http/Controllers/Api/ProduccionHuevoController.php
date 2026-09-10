<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Movimiento;
use App\Models\ProduccionHuevo;
use Illuminate\Http\Request;

class ProduccionHuevoController extends Controller
{
    public function index()
    {
        return ProduccionHuevo::latest('fecha')->paginate(30);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'fecha' => ['required', 'date'],
            'cantidad' => ['required', 'integer', 'min:1'],
            'descripcion' => ['nullable', 'string'],
        ]);

        $produccion = ProduccionHuevo::create($data);

        return response()->json($produccion, 201);
    }

    public function stockActual()
    {
        $totalProducido = ProduccionHuevo::sum('cantidad');
        $totalVendido = Movimiento::where('tipo', 'venta_huevos')->sum('cantidad_huevos');

        return response()->json([
            'total_producido' => (int) $totalProducido,
            'total_vendido' => (int) $totalVendido,
            'stock_actual' => (int) ($totalProducido - $totalVendido),
        ]);
    }
}