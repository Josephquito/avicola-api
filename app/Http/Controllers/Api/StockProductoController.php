<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Producto;
use App\Models\StockProducto;
use Illuminate\Http\Request;

class StockProductoController extends Controller
{
    public function index(Request $request)
    {
        $query = StockProducto::with(['producto', 'unidad'])->latest('fecha');

        if ($request->has('producto_id')) {
            $query->where('producto_id', $request->input('producto_id'));
        }

        return $query->paginate(30);
    }

    public function stockActual(Producto $producto)
    {
        $stock = StockProducto::where('producto_id', $producto->id)->sum('cantidad');

        return response()->json([
            'producto' => $producto,
            'stock_actual' => (float) $stock,
        ]);
    }

    public function registrarConsumo(Request $request)
    {
        $data = $request->validate([
            'producto_id' => ['required', 'exists:productos,id'],
            'cantidad' => ['required', 'numeric', 'min:0.01'],
            'fecha' => ['required', 'date'],
            'descripcion' => ['nullable', 'string'],
        ]);

        $producto = Producto::find($data['producto_id']);
        $stockActual = StockProducto::where('producto_id', $data['producto_id'])->sum('cantidad');

        if ($data['cantidad'] > $stockActual) {
            return response()->json(['message' => 'No hay suficiente stock de este producto para registrar ese consumo.'], 422);
        }

        $registro = StockProducto::create([
            'producto_id' => $data['producto_id'],
            'unidad_id' => $producto->unidad_id,
            'cantidad' => -$data['cantidad'],
            'tipo_movimiento' => 'consumo',
            'fecha' => $data['fecha'],
            'descripcion' => $data['descripcion'] ?? null,
        ]);

        return response()->json($registro->load(['producto', 'unidad']), 201);
    }
}