<?php

namespace App\Http\Controllers\Api\Movimientos;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreCompraRequest;
use App\Services\Facturacion\FacturaService;

class CompraController extends Controller
{
    public function __construct(private FacturaService $facturaService) {}

    public function store(StoreCompraRequest $request)
    {
        $movimiento = $this->facturaService->crear('compra', $request->validated());

        return response()->json(
            $movimiento->load(['cuentaEfectivo', 'contacto', 'items.producto']),
            201
        );
    }

    public function show(int $id)
    {
        $movimiento = \App\Models\Movimiento::with(['cuentaEfectivo', 'contacto', 'items.producto'])
            ->where('tipo', 'compra')
            ->findOrFail($id);

        return response()->json($movimiento);
    }

    public function destroy(int $id)
    {
        $movimiento = \App\Models\Movimiento::where('tipo', 'compra')->findOrFail($id);
        $this->facturaService->eliminar($movimiento);

        return response()->json(null, 204);
    }

    public function anular(int $id)
    {
        $movimiento = \App\Models\Movimiento::where('tipo', 'compra')->findOrFail($id);
        $this->facturaService->anular($movimiento);

        return response()->json($movimiento->fresh());
    }
}