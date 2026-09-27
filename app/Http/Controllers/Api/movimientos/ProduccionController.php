<?php

namespace App\Http\Controllers\Api\Movimientos;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreProduccionRequest;
use App\Services\Facturacion\FacturaService;

class ProduccionController extends Controller
{
    public function __construct(private FacturaService $facturaService) {}

    public function store(StoreProduccionRequest $request)
    {
        $movimiento = $this->facturaService->crear('produccion', $request->validated());

        return response()->json(
            $movimiento->load(['items.producto', 'items.lote']),
            201
        );
    }

    public function show(int $id)
    {
        $movimiento = \App\Models\Movimiento::with(['items.producto', 'items.lote'])
            ->where('tipo', 'produccion')
            ->findOrFail($id);

        return response()->json($movimiento);
    }

    public function destroy(int $id)
    {
        $movimiento = \App\Models\Movimiento::where('tipo', 'produccion')->findOrFail($id);
        $this->facturaService->eliminar($movimiento);

        return response()->json(null, 204);
    }

    public function anular(int $id)
    {
        $movimiento = \App\Models\Movimiento::where('tipo', 'produccion')->findOrFail($id);
        $this->facturaService->anular($movimiento);

        return response()->json($movimiento->fresh());
    }
}