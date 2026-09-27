<?php

namespace App\Services\Facturacion;

use App\Models\Lote;
use App\Models\MovimientoItem;
use App\Models\SacoAlimento;
use App\Models\StockProducto;
use RuntimeException;

/// Efecto secundario de cada línea de factura sobre inventario/lotes,
/// según la categoría del producto. FacturaService llama a procesar()
/// al crear cada línea, y a revertir() al eliminar/anular una factura
/// que aún no tiene nada "consumido" encima.
class MovimientoItemProcessor
{
    public function procesar(MovimientoItem $item, string $tipoMovimiento): void
    {
        $categoria = $item->categoriaNombre();

        match ($categoria) {
            'Muebles y enseres' => $this->procesarStockSimple($item, $tipoMovimiento),
            'Medicina' => $this->procesarStockSimple($item, $tipoMovimiento),
            'Alimento' => $this->procesarAlimento($item, $tipoMovimiento),
            'Aves' => $this->procesarAve($item, $tipoMovimiento),
            'Huevos' => null, // no toca tabla propia — el stock de huevos se calcula sumando/restando movimiento_items
            default => throw new RuntimeException("Categoría no soportada para facturación: {$categoria}"),
        };
    }

    public function revertir(MovimientoItem $item): void
    {
        $categoria = $item->categoriaNombre();

        match ($categoria) {
            'Muebles y enseres', 'Medicina' => StockProducto::where('movimiento_item_id', $item->id)->delete(),
            'Alimento' => $this->revertirAlimento($item),
            'Aves' => $this->revertirAve($item),
            'Huevos' => null,
            default => null,
        };
    }

    private function procesarStockSimple(MovimientoItem $item, string $tipoMovimiento): void
    {
        StockProducto::create([
            'producto_id' => $item->producto_id,
            'unidad_id' => $item->producto->unidad_id,
            'cantidad' => $item->cantidad,
            'tipo_movimiento' => $tipoMovimiento === 'compra' ? 'compra' : 'consumo',
            'movimiento_item_id' => $item->id,
            'fecha' => $item->movimiento->fecha,
            'descripcion' => $item->descripcion,
        ]);
    }

    private function procesarAlimento(MovimientoItem $item, string $tipoMovimiento): void
    {
        $producto = $item->producto;

        if ($producto->requiere_ciclo_saco) {
            for ($i = 0; $i < $item->cantidad_sacos; $i++) {
                SacoAlimento::create([
                    'producto_id' => $producto->id,
                    'unidad_id' => $producto->unidad_id,
                    'peso' => $item->peso_por_saco,
                    'estado' => 'en_espera',
                    'fecha_compra' => $item->movimiento->fecha,
                    'movimiento_item_id' => $item->id,
                ]);
            }
            return;
        }

        $this->procesarStockSimple($item, $tipoMovimiento);
    }

    private function revertirAlimento(MovimientoItem $item): void
    {
        if ($item->producto->requiere_ciclo_saco) {
            SacoAlimento::where('movimiento_item_id', $item->id)->delete();
            return;
        }

        StockProducto::where('movimiento_item_id', $item->id)->delete();
    }

    private function procesarAve(MovimientoItem $item, string $tipoMovimiento): void
    {
        if ($tipoMovimiento === 'venta') {
            $lote = Lote::findOrFail($item->lote_id);

            $cantidadGallinas = $item->cantidad_gallinas ?? 0;
            $cantidadGallos = $item->cantidad_gallos ?? 0;

            if ($cantidadGallinas > $lote->cantidad_gallinas) {
                throw new RuntimeException('No hay suficientes gallinas disponibles en este lote.');
            }
            if ($cantidadGallos > $lote->cantidad_gallos) {
                throw new RuntimeException('No hay suficientes gallos disponibles en este lote.');
            }

            $lote->update([
                'cantidad_gallinas' => $lote->cantidad_gallinas - $cantidadGallinas,
                'cantidad_gallos' => $lote->cantidad_gallos - $cantidadGallos,
            ]);

            if ($lote->cantidadTotal() === 0) {
                $lote->update(['activo' => false]);
            }

            return;
        }

        // compra o producción (nacimiento) — ambas crean un Lote nuevo
        Lote::create([
            'origen' => $tipoMovimiento === 'produccion' ? 'nacimiento' : 'compra',
            'producto_id' => $item->producto_id,
            'cantidad_gallinas' => $item->cantidad,
            'cantidad_gallos' => 0,
            'edad_inicial_dias' => $item->edad_inicial_dias,
            'fecha_ingreso' => $item->movimiento->fecha,
            'costo_total' => $item->subtotal ?? 0,
            'movimiento_item_id' => $item->id,
        ]);
    }

    private function revertirAve(MovimientoItem $item): void
    {
        // Solo se revierte si es una venta y el lote no fue tocado después,
        // o si es una compra/producción que creó un lote intacto — esa
        // validación de "¿está intacto?" vive en FacturaService, antes de
        // llamar a revertir() en absoluto.
        if ($item->lote_id !== null) {
            // era una venta: devolver las aves al lote
            $lote = Lote::find($item->lote_id);
            $lote?->update([
                'cantidad_gallinas' => $lote->cantidad_gallinas + ($item->cantidad_gallinas ?? 0),
                'cantidad_gallos' => $lote->cantidad_gallos + ($item->cantidad_gallos ?? 0),
                'activo' => true,
            ]);
            return;
        }

        // era compra/producción: borrar el lote que esta línea creó
        Lote::where('movimiento_item_id', $item->id)->delete();
    }
}