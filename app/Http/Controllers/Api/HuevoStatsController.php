<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Lote;
use App\Models\MovimientoItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class HuevoStatsController extends Controller
{
    public function resumen(Request $request)
    {
        $desde = $request->input('fecha_desde');
        $hasta = $request->input('fecha_hasta');

        $baseQuery = MovimientoItem::query()
            ->whereHas('producto.categoria', fn ($q) => $q->where('nombre', 'Huevos'))
            ->whereHas('movimiento', function ($q) use ($desde, $hasta) {
                $q->where('anulado', false)
                    ->whereIn('tipo', ['produccion', 'venta']);
                if ($desde) $q->whereDate('fecha', '>=', $desde);
                if ($hasta) $q->whereDate('fecha', '<=', $hasta);
            });

        $totalProducido = (clone $baseQuery)
            ->whereHas('movimiento', fn ($q) => $q->where('tipo', 'produccion'))
            ->sum('cantidad_huevos');

        $totalVendido = (clone $baseQuery)
            ->whereHas('movimiento', fn ($q) => $q->where('tipo', 'venta'))
            ->sum('cantidad_huevos');

        // Stock actual siempre es histórico completo (sin filtro de
        // fechas) — es "cuántos huevos tengo ahora", no algo que
        // cambie según el rango que estés mirando.
        $stockActual = $this->stockActual();

        $gallinasEnProduccion = Lote::where('activo', true)
            ->where('etapa', 'produccion')
            ->sum('cantidad_gallinas');

        $dias = ($desde && $hasta)
            ? now()->parse($desde)->diffInDays(now()->parse($hasta)) + 1
            : 1;

        $tasaProduccionDiaria = $gallinasEnProduccion > 0
            ? round(($totalProducido / max($dias, 1)) / $gallinasEnProduccion, 4)
            : 0;

        $serieDiaria = $this->serieDiaria($desde, $hasta);
        $historial = $this->historial($desde, $hasta);

        return response()->json([
            'stock_actual' => (int) $stockActual,
            'total_producido' => (int) $totalProducido,
            'total_vendido' => (int) $totalVendido,
            'gallinas_en_produccion' => (int) $gallinasEnProduccion,
            'tasa_produccion_diaria' => $tasaProduccionDiaria,
            'serie_diaria' => $serieDiaria,
            'historial' => $historial,
        ]);
    }

    private function stockActual(): int
    {
        $producido = MovimientoItem::whereHas('producto.categoria', fn ($q) => $q->where('nombre', 'Huevos'))
            ->whereHas('movimiento', fn ($q) => $q->where('tipo', 'produccion')->where('anulado', false))
            ->sum('cantidad_huevos');

        $vendido = MovimientoItem::whereHas('producto.categoria', fn ($q) => $q->where('nombre', 'Huevos'))
            ->whereHas('movimiento', fn ($q) => $q->where('tipo', 'venta')->where('anulado', false))
            ->sum('cantidad_huevos');

        return (int) ($producido - $vendido);
    }

    private function serieDiaria(?string $desde, ?string $hasta): array
    {
        $query = DB::table('movimiento_items')
            ->join('movimientos', 'movimientos.id', '=', 'movimiento_items.movimiento_id')
            ->join('productos', 'productos.id', '=', 'movimiento_items.producto_id')
            ->join('categorias', 'categorias.id', '=', 'productos.categoria_id')
            ->where('categorias.nombre', 'Huevos')
            ->where('movimientos.anulado', false)
            ->whereIn('movimientos.tipo', ['produccion', 'venta']);

        if ($desde) $query->whereDate('movimientos.fecha', '>=', $desde);
        if ($hasta) $query->whereDate('movimientos.fecha', '<=', $hasta);

        $filas = $query->select(
            'movimientos.fecha',
            'movimientos.tipo',
            DB::raw('SUM(movimiento_items.cantidad_huevos) as total')
        )
            ->groupBy('movimientos.fecha', 'movimientos.tipo')
            ->orderBy('movimientos.fecha')
            ->get();

        $porFecha = [];
        foreach ($filas as $fila) {
            $fecha = $fila->fecha;
            $porFecha[$fecha] ??= ['fecha' => $fecha, 'producido' => 0, 'vendido' => 0];
            $porFecha[$fecha][$fila->tipo === 'produccion' ? 'producido' : 'vendido'] = (int) $fila->total;
        }

        return array_values($porFecha);
    }

    private function historial(?string $desde, ?string $hasta): array
    {
        $query = MovimientoItem::with(['movimiento.contacto'])
            ->whereHas('producto.categoria', fn ($q) => $q->where('nombre', 'Huevos'))
            ->whereHas('movimiento', function ($q) use ($desde, $hasta) {
                $q->where('anulado', false)->whereIn('tipo', ['produccion', 'venta']);
                if ($desde) $q->whereDate('fecha', '>=', $desde);
                if ($hasta) $q->whereDate('fecha', '<=', $hasta);
            });

        return $query->get()->map(fn ($item) => [
            'fecha' => $item->movimiento->fecha->toDateString(),
            'tipo' => $item->movimiento->tipo,
            'cantidad' => (int) $item->cantidad_huevos,
            'contacto' => $item->movimiento->contacto?->nombre,
        ])->sortByDesc('fecha')->values()->toArray();
    }
}